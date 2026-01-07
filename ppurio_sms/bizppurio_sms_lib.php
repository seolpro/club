<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * 공통: JSON 응답 헬퍼
 */
function json_response(array $data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/**
 * 전화번호에서 숫자만 남기기
 */
function only_digits(string $s): string {
  return preg_replace('/\D+/', '', $s);
}

/**
 * SMS 90byte(한글 약 45자) 기준을 대략적으로 판별하기 위해
 * EUC-KR 바이트 길이로 계산(실무에서 가장 무난)
 */
function euckr_bytes(string $utf8): int {
  $euckr = @iconv('UTF-8', 'EUC-KR//IGNORE', $utf8);
  if ($euckr === false) return strlen($utf8);
  return strlen($euckr);
}

/**
 * 토큰 캐시 읽기
 */
function load_token_cache(): ?array {
  if (!is_file(BIZPPURIO_TOKEN_CACHE)) return null;
  $raw = @file_get_contents(BIZPPURIO_TOKEN_CACHE);
  if ($raw === false) return null;
  $j = json_decode($raw, true);
  return is_array($j) ? $j : null;
}

/**
 * 토큰 캐시 저장(간단 락 포함)
 */
function save_token_cache(array $data): void {
  $fp = @fopen(BIZPPURIO_TOKEN_CACHE, 'c+');
  if (!$fp) return;
  @flock($fp, LOCK_EX);
  ftruncate($fp, 0);
  fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  fflush($fp);
  @flock($fp, LOCK_UN);
  fclose($fp);
}

/**
 * expired (YYYYMMDDhhmmss) 파싱 :contentReference[oaicite:2]{index=2}
 */
function parse_expired(string $expired): ?DateTimeImmutable {
  $dt = DateTimeImmutable::createFromFormat('YmdHis', $expired, new DateTimeZone('Asia/Seoul'));
  return $dt ?: null;
}

/**
 * API 호출(cURL)
 */
function curl_json(string $url, array $headers, ?array $body, int $timeoutSec = 10): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => $timeoutSec,
    // 운영에서는 SSL 검증 유지 권장
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);

  if ($body !== null) {
    $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
  } else {
    curl_setopt($ch, CURLOPT_POSTFIELDS, ''); // 빈 바디
  }

  $resp = curl_exec($ch);
  $errno = curl_errno($ch);
  $err   = curl_error($ch);
  $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($errno) {
    return ['ok' => false, 'http' => $http, 'error' => "cURL($errno): $err", 'raw' => $resp];
  }

  $decoded = json_decode((string)$resp, true);
  return ['ok' => true, 'http' => $http, 'data' => $decoded, 'raw' => $resp];
}

/**
 * 토큰 발급: POST /v1/token, Authorization: Basic base64(account:password) :contentReference[oaicite:3]{index=3}
 */
function issue_new_token(): array {
  $basic = base64_encode(BIZPPURIO_ACCOUNT . ':' . BIZPPURIO_PASSWORD);

  $r = curl_json(
    BIZPPURIO_API_BASE . '/v1/token',
    [
      'Content-Type: application/json; charset=utf-8',
      'Authorization: Basic ' . $basic,
    ],
    null,
    10
  );

  if (!$r['ok'] || $r['http'] !== 200 || !is_array($r['data'])) {
    return ['ok' => false, 'error' => '토큰 발급 실패', 'detail' => $r];
  }

  // 응답: accesstoken / type / expired :contentReference[oaicite:4]{index=4}
  $accesstoken = $r['data']['accesstoken'] ?? null;
  $type        = $r['data']['type'] ?? 'Bearer';
  $expired     = $r['data']['expired'] ?? null;

  if (!$accesstoken || !$expired) {
    return ['ok' => false, 'error' => '토큰 응답 필드 누락', 'detail' => $r['data']];
  }

  $cache = [
    'accesstoken' => $accesstoken,
    'type'        => $type,
    'expired'     => $expired,
    'issued_at'   => date('Y-m-d H:i:s'),
  ];
  save_token_cache($cache);

  return ['ok' => true, 'token' => $cache];
}

/**
 * 유효 토큰 가져오기(만료 2분 전이면 재발급)
 */
function get_access_token(): array {
  $cache = load_token_cache();
  if (is_array($cache) && !empty($cache['accesstoken']) && !empty($cache['expired'])) {
    $exp = parse_expired((string)$cache['expired']);
    if ($exp) {
      $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
      if ($exp->getTimestamp() - $now->getTimestamp() > 120) {
        return ['ok' => true, 'token' => $cache];
      }
    }
  }
  return issue_new_token();
}

/**
 * 문자 발송: POST /v3/message (sms/lms) :contentReference[oaicite:5]{index=5}
 */
function bizppurio_send_sms(string $to, string $message, ?string $subject = null, ?string $refkey = null): array {
  $to = only_digits($to);
  if ($to === '' || strlen($to) < 9) return ['ok' => false, 'error' => '수신번호가 올바르지 않습니다.'];

  $message = trim($message);
  if ($message === '') return ['ok' => false, 'error' => '메시지 내용이 비었습니다.'];

  // 메시지 길이에 따라 sms / lms 자동 선택
  $bytes = euckr_bytes($message);
  $type  = ($bytes <= 90) ? 'sms' : 'lms';

  $tokenR = get_access_token();
  if (!$tokenR['ok']) return $tokenR;

  $token = $tokenR['token'];
  $bearer = ($token['type'] ?? 'Bearer') . ' ' . ($token['accesstoken'] ?? '');

  if (!$refkey) {
    $refkey = 'sms_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)); // 32바이트 내 권장 :contentReference[oaicite:6]{index=6}
  }

  $content = [];
  if ($type === 'sms') {
    $content = ['sms' => ['message' => $message]]; // :contentReference[oaicite:7]{index=7}
  } else {
    $content = ['lms' => ['message' => $message, 'subject' => ($subject ?? '안내')]]; // :contentReference[oaicite:8]{index=8}
  }

  $payload = [
    'account' => BIZPPURIO_ACCOUNT,
    'refkey'  => $refkey,
    'type'    => $type,
    'from'    => only_digits(BIZPPURIO_FROM),
    'to'      => $to,
    'content' => $content,
  ];

  $r = curl_json(
    BIZPPURIO_API_BASE . '/v3/message',
    [
      'Content-Type: application/json; charset=utf-8',
      'Authorization: ' . $bearer, // Bearer 토큰 :contentReference[oaicite:9]{index=9}
    ],
    $payload,
    10
  );

  // 성공 응답 예: code=1000, messagekey, refkey :contentReference[oaicite:10]{index=10}
  if (!$r['ok']) return ['ok' => false, 'error' => 'API 호출 실패', 'detail' => $r];

  return [
    'ok'    => ($r['http'] === 200),
    'http'  => $r['http'],
    'type'  => $type,
    'bytes' => $bytes,
    'resp'  => $r['data'],
    'raw'   => $r['raw'],
    'sent'  => $payload,
  ];
}
