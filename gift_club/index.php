<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!is_installed()) {
    redirect('install.php');
}

$pdo = db();

$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM gift_settings") as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$clubs = $pdo->query("
    SELECT id, name
    FROM gift_clubs
    WHERE is_active = 1
    ORDER BY sort_order, id
")->fetchAll();

$closed = ($settings['closed'] ?? '0') === '1';
$pageTitle = $settings['title'] ?? APP_NAME;
$notice = $settings['notice'] ?? '';
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#6c63ff">
    <title><?= e($pageTitle) ?></title>

    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/member.css">
</head>

<body class="member-page">

<main class="member-wrap">

    <!-- 상단 히어로 -->
    <section class="member-hero">
        <div class="hero-glow hero-glow-a"></div>
        <div class="hero-glow hero-glow-b"></div>

        <div class="hero-top">
            <span class="season-chip">🌕 2026 추석 선물</span>
            <span class="secure-chip">🔒 안전한 정보등록</span>
        </div>

        <div class="hero-main">
            <div class="gift-visual" aria-hidden="true">
                <span class="gift-main">🎁</span>
                <span class="spark spark-1">✨</span>
                <span class="spark spark-2">⭐</span>
                <span class="spark spark-3">💫</span>
            </div>

            <div class="hero-copy">
                <span class="eyebrow">동아리 회원 전용</span>
                <h1><?= e($pageTitle) ?></h1>
                <p><?= nl2br(e($notice)) ?></p>
            </div>
        </div>

        <div class="hero-points">
            <div>
                <span class="point-icon">📝</span>
                <span><b>간편 입력</b><small>1분이면 완료</small></span>
            </div>
            <div>
                <span class="point-icon">🔁</span>
                <span><b>재제출 가능</b><small>최종 1건만 보관</small></span>
            </div>
            <div>
                <span class="point-icon">✅</span>
                <span><b>최종 확인</b><small>제출 전 한 번 더</small></span>
            </div>
        </div>
    </section>

    <?php if ($closed): ?>

        <section class="member-card state-card">
            <div class="state-emoji">🌙</div>
            <h2>제출이 마감되었습니다</h2>
            <p>입력 기간이 종료되었습니다.<br>추가 문의가 필요한 경우 관리자에게 문의해 주세요.</p>
        </section>

    <?php elseif (!$clubs): ?>

        <section class="member-card state-card">
            <div class="state-emoji">📭</div>
            <h2>등록된 동아리가 없습니다</h2>
            <p>현재 선택 가능한 동아리가 없습니다.<br>관리자에게 문의해 주세요.</p>
        </section>

    <?php else: ?>

        <section class="member-card form-card">

            <div class="section-head modern-head">
                <div>
                    <span class="step-label">STEP 01</span>
                    <h2>💌 입금계좌 정보를 알려주세요</h2>
                    <p>선물 입금을 위해 꼭 필요한 정보만 받고 있어요.</p>
                </div>

                <!-- <span class="final-badge">
                    <span>🔄</span>
                    최종 제출 1건
                </span> -->
            </div>

            <form
                id="giftForm"
                action="submit.php"
                method="post"
                autocomplete="off"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <div class="cute-form-grid">

                    <!-- 동아리 -->
                    <label class="field-card span-2">
                        <span class="field-title">
                            <span class="field-icon purple">🎯</span>
                            <span>
                                <b>동아리</b>
                                <small>소속 동아리를 선택해 주세요.</small>
                            </span>
                            <em>*</em>
                        </span>

                        <select
                            name="club_id"
                            id="club_id"
                            required
                        >
                            <option value="">동아리를 선택해 주세요 👇</option>

                            <?php foreach ($clubs as $c): ?>
                                <option value="<?= e((string)$c['id']) ?>">
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <!-- 성명 -->
                    <label class="field-card">
                        <span class="field-title">
                            <span class="field-icon blue">👤</span>
                            <span>
                                <b>성명</b>
                                <small>회원 본인 성명</small>
                            </span>
                            <em>*</em>
                        </span>

                        <input
                            name="member_name"
                            id="member_name"
                            maxlength="80"
                            required
                            placeholder="예: 홍길동"
                            autocomplete="name"
                        >
                    </label>

                    <!-- 연락처 -->
                    <label class="field-card">
                        <span class="field-title">
                            <span class="field-icon green">📱</span>
                            <span>
                                <b>연락처</b>
                                <small>숫자만 입력하세요</small>
                            </span>
                            <em>*</em>
                        </span>

                        <input
                            name="phone"
                            id="phone"
                            type="tel"
                            inputmode="tel"
                            maxlength="20"
                            required
                            placeholder="010-1234-5678"
                            autocomplete="tel"
                        >
                    </label>

                    <!-- 은행명 -->
                    <label class="field-card">
                        <span class="field-title">
                            <span class="field-icon yellow">🏦</span>
                            <span>
                                <b>은행명</b>
                                <small>입금받을 금융기관(✨신협우대)</small>
                            </span>
                            <em>*</em>
                        </span>

                        <input
                            name="bank_name"
                            id="bank_name"
                            maxlength="80"
                            required
                            placeholder="예: 신협, 국민은행"
                        >
                    </label>

                    <!-- 계좌번호 -->
                    <label class="field-card">
                        <span class="field-title">
                            <span class="field-icon pink">💳</span>
                            <span>
                                <b>계좌번호</b>
                                <small>숫자 위주로 입력</small>
                            </span>
                            <em>*</em>
                        </span>

                        <input
                            name="account_no"
                            id="account_no"
                            inputmode="numeric"
                            maxlength="100"
                            required
                            placeholder="계좌번호를 입력해 주세요"
                            autocomplete="off"
                        >
                    </label>

                    <!-- 기타 코멘트 -->
                    <label class="field-card span-2 comment-card">
                        <span class="field-title">
                            <span class="field-icon orange">📝</span>
                            <span>
                                <b>기타 코멘트</b>
                                <small>필요한 전달사항이 있을 때만 작성해 주세요.</small>
                            </span>
                        </span>

                        <textarea
                            name="comment"
                            id="comment"
                            rows="2"
                            maxlength="300"
                            placeholder="예: 입금 관련 참고사항이 있습니다."
                        ></textarea>

                        <span class="counter">
                            <b id="commentCount">0</b>/300
                        </span>
                    </label>

                </div>

                <!-- 안내 -->
                <div class="privacy-box smart-privacy">
                    <div class="privacy-icon">🛡️</div>
                    <div>
                        <b>입력정보 이용 안내</b>
                        <p>
                            입금 업무를 위해 동아리, 성명, 연락처, 은행명,
                            계좌번호 및 코멘트를 수집합니다.
                            입력정보는 해당 업무 목적 범위에서만 확인합니다.
                        </p>
                    </div>
                </div>

                <!-- 동의 -->
                <label class="check cute-check">
                    <input
                        type="checkbox"
                        id="agree"
                        required
                    >
                    <span class="check-ui"></span>
                    <span>
                        입력한 내용을 다시 확인했으며
                        <b>정확한 정보임에 동의합니다.</b>
                    </span>
                </label>

                <!-- 제출 -->
                <button
                    class="btn full large cute-submit"
                    type="submit"
                >
                    <span>입력내용 확인하기</span>
                    <span class="submit-arrow">→</span>
                </button>

                <p class="submit-note">
                    🔁 같은 <b>성명 + 연락처</b>로 다시 제출하면 이전 내용 대신
                    가장 최근 제출내용만 보관됩니다.
                </p>

            </form>
        </section>

    <?php endif; ?>

    <footer class="member-footer">
        <span>🎁 즐거운 한가위 보내세요</span>
        <a href="admin/login.php">관리자</a>
    </footer>

</main>


<!-- 최종 확인 모달 -->
<div
    class="modal member-modal"
    id="confirmModal"
    aria-hidden="true"
>
    <div class="modal-card modern-modal">

        <div class="modal-character">🎁</div>

        <div class="modal-head">
            <div>
                <span class="step-label">FINAL CHECK</span>
                <h3>제출 전 한 번만 확인해 주세요</h3>
            </div>

            <button
                type="button"
                class="icon-btn"
                id="closeModal"
                aria-label="닫기"
            >×</button>
        </div>

        <p class="muted modal-desc">
            아래 내용이 맞는지 확인해 주세요.<br>
            재제출하는 경우 이전 내용은 삭제되고
            <b>이번 내용만 최종 저장</b>됩니다.
        </p>

        <dl id="confirmList" class="confirm-list"></dl>

        <div class="modal-actions">
            <button
                type="button"
                class="btn secondary"
                id="editBtn"
            >
                ✏️ 수정하기
            </button>

            <button
                type="button"
                class="btn final-btn"
                id="finalSubmit"
            >
                ✅ 확인하고 제출
            </button>
        </div>

    </div>
</div>

<script src="assets/app.js"></script>

</body>
</html>
