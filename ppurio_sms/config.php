<?php
declare(strict_types=1);

// 운영 API 호스트
const BIZPPURIO_API_BASE = 'https://api.bizppurio.com';

// ✅ 비즈뿌리오 계정/암호(= API 인증용 키/비밀번호 성격)
// 토큰 발급 시 Basic Base64(account:password) 로 사용 :contentReference[oaicite:1]{index=1}
const BIZPPURIO_ACCOUNT  = 'aj9770';
const BIZPPURIO_PASSWORD = '08868d27d42a13b10954f7c9705063152e03d948b824bf336ff611be225957b9';

// ✅ 사전 등록된 발신번호(숫자만 권장)
const BIZPPURIO_FROM     = '01071186639';

// 토큰 캐시 파일(웹에서 직접 접근 불가한 위치 권장)
const BIZPPURIO_TOKEN_CACHE = __DIR__ . '/.bizppurio_token.json';

// 기본 타임존
date_default_timezone_set('Asia/Seoul');
