<?php
declare(strict_types=1);

/**
 * gift_club 계좌 제출 안내 LMS
 * Bizppurio /v1/token, /v1/message
 * 개선: 일괄발송 토큰 재사용 + rate limit 자동 재시도
 */

function gift_sms_require_config(): void
{
    foreach (['PPURIO_ACCOUNT','PPURIO_AUTH_KEY'] as $k) {
        if (!defined($k) || trim((string)constant($k)) === '') {
            throw new RuntimeException($k . ' 설정값이 config.php에 없습니다.');
        }
    }
    if (
        (!defined('PPURIO_SMS_FROM') || trim((string)PPURIO_SMS_FROM) === '')
        && (!defined('PPURIO_FROM') || trim((string)PPURIO_FROM) === '')
    ) {
        throw new RuntimeException('PPURIO_SMS_FROM 또는 PPURIO_FROM 발신번호가 config.php에 없습니다.');
    }
}

function gift_sms_from(): string
{
    $from = defined('PPURIO_SMS_FROM') ? (string)PPURIO_SMS_FROM : (string)PPURIO_FROM;
    return preg_replace('/\D+/', '', $from) ?? '';
}

function gift_sms_http(string $url, array $headers, string $body): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) throw new RuntimeException('뿌리오 문자 통신 오류: '.$error);

    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('뿌리오 문자 응답 JSON 오류 (HTTP '.$http.'): '.mb_substr((string)$raw,0,500,'UTF-8'));
    }
    return ['http'=>$http,'json'=>$json,'raw'=>(string)$raw];
}

function gift_sms_token(): string
{
    gift_sms_require_config();
    $basic = base64_encode((string)PPURIO_ACCOUNT . ':' . (string)PPURIO_AUTH_KEY);
    $r = gift_sms_http(
        'https://message.ppurio.com/v1/token',
        ['Authorization: Basic '.$basic, 'Content-Type: application/json; charset=utf-8'],
        '{}'
    );
    $token = (string)($r['json']['token'] ?? '');
    if ($token === '') {
        throw new RuntimeException((string)($r['json']['description'] ?? $r['json']['message'] ?? '토큰 발급 실패'));
    }
    return $token;
}

function gift_sms_ref_key(string $sourceKey, int $memberId): string
{
    return 'GAR'.date('ymdHis').substr($sourceKey,0,2).$memberId.random_int(10,99);
}

function gift_account_deadline(): string
{
    return defined('GIFT_ACCOUNT_SUBMIT_DEADLINE') ? trim((string)GIFT_ACCOUNT_SUBMIT_DEADLINE) : '';
}

function gift_account_deadline_label(): string
{
    $raw = gift_account_deadline();
    if ($raw === '') return '관리자 안내 기한 내';
    $ts = strtotime($raw);
    return $ts ? date('Y.m.d H:i', $ts) : $raw;
}

function gift_account_sms_content(string $memberName, string $sourceLabel): string
{
    $url = 'https://seolhopro.mycafe24.com/gift_club/';
    return "[아주대의료원신협]\n\n"
        . $memberName . "님, " . $sourceLabel . " 명절선물 지급을 위한 계좌정보가 아직 제출되지 않았습니다.\n\n"
        . "■ 제출마감: " . gift_account_deadline_label() . "\n"
        . "■ 제출하기\n" . $url . "\n\n"
        . "기한 내 계좌정보를 제출해 주시기 바랍니다.\n"
        . "이미 제출하신 경우 본 문자를 무시해 주세요.";
}

function gift_sms_is_rate_limited(array $r): bool
{
    $j = $r['json'] ?? [];
    $http = (int)($r['http'] ?? 0);
    $code = strtolower(trim((string)($j['code'] ?? $j['resultCode'] ?? '')));
    $desc = strtolower(trim((string)($j['description'] ?? $j['message'] ?? '')));

    return $http === 429
        || str_contains($desc, 'too many request')
        || str_contains($desc, 'too many requests')
        || $code === '429';
}

/**
 * $token은 일괄발송 시작 시 1회 발급한 토큰을 전달합니다.
 * rate limit 시 1초 → 2초 → 4초 대기 후 최대 3회 재시도합니다.
 */
function gift_send_account_lms(
    string $token,
    string $sourceKey,
    int $memberId,
    string $sourceLabel,
    string $memberName,
    string $phone
): array {
    gift_sms_require_config();

    $to = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($to) < 10) throw new RuntimeException('연락처를 확인해 주세요.');

    $content = gift_account_sms_content($memberName, $sourceLabel);
    $refKey = gift_sms_ref_key($sourceKey, $memberId);

    $payload = [
        'account' => (string)PPURIO_ACCOUNT,
        'messageType' => 'LMS',
        'content' => $content,
        'from' => gift_sms_from(),
        'duplicateFlag' => 'N',
        'targetCount' => 1,
        'targets' => [['to'=>$to, 'name'=>trim($memberName)]],
        'refKey' => $refKey,
        'subject' => '명절선물 계좌정보 제출 안내',
    ];

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if ($body === false) throw new RuntimeException('문자 JSON 생성 실패');

    $maxRetry = 3;
    $attempt = 0;

    while (true) {
        $r = gift_sms_http(
            'https://message.ppurio.com/v1/message',
            ['Authorization: Bearer '.$token, 'Content-Type: application/json; charset=utf-8'],
            $body
        );

        if (!gift_sms_is_rate_limited($r) || $attempt >= $maxRetry) break;

        // 1초, 2초, 4초 backoff
        sleep(2 ** $attempt);
        $attempt++;
    }

    $j = $r['json'];
    $code = (string)($j['code'] ?? $j['resultCode'] ?? '');
    $desc = (string)($j['description'] ?? $j['message'] ?? '');
    $requestKey = (string)($j['messageKey'] ?? $j['requestKey'] ?? $refKey);

    $ok = ($r['http'] >= 200 && $r['http'] < 300);
    if ($code !== '' && !in_array($code, ['1000','200','0','OK','SUCCESS'], true)) $ok = false;

    return [
        'ok'=>$ok, 'http'=>$r['http'], 'code'=>$code, 'description'=>$desc,
        'request_key'=>$requestKey, 'ref_key'=>$refKey, 'content'=>$content,
        'retry_count'=>$attempt
    ];
}
