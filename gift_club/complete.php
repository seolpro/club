<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (!is_installed()) redirect('install.php');

$result = $_SESSION['gift_submit_result'] ?? null;
if (!is_array($result)) redirect('index.php');

$club = (string)($result['club'] ?? '');
$name = (string)($result['name'] ?? '');
$bank = (string)($result['bank'] ?? '');
$account = (string)($result['account'] ?? '');
$replaced = (bool)($result['replaced'] ?? false);
$memberSync = is_array($result['member_sync'] ?? null) ? $result['member_sync'] : [];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#5448a8">
<title>제출 완료 - <?=e(APP_NAME)?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;overflow-x:hidden;color:#252d48;background:radial-gradient(circle at 50% 0,#fff4bc66,transparent 25rem),linear-gradient(180deg,#f8f7ff,#f4f6fb)}
.done-wrap{width:min(720px,calc(100% - 22px));margin:auto;padding:20px 0 34px}
.festival{position:relative;overflow:hidden;min-height:290px;border-radius:30px;background:linear-gradient(155deg,#28245c,#554aa5 52%,#8273d0);box-shadow:0 22px 55px #3d35752b;color:#fff}
.festival:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 50% 28%,#fff7b42a,transparent 13rem)}
.moon{position:absolute;left:50%;top:34px;width:116px;height:116px;border-radius:50%;transform:translateX(-50%);background:linear-gradient(145deg,#fffbdc,#ffe58a);box-shadow:0 0 18px #fff3b9aa,0 0 68px #ffe99c66;animation:moon 1.2s cubic-bezier(.2,.75,.25,1) both}
.moon:after{content:"🌕";position:absolute;inset:0;display:grid;place-items:center;font-size:52px;filter:drop-shadow(0 5px 5px #8a6f2c33)}
.rabbit{position:absolute;left:calc(50% + 35px);top:118px;font-size:31px;opacity:0;animation:rabbit .8s .9s ease forwards}
.star,.leaf{position:absolute;user-select:none;pointer-events:none}
.star{animation:twinkle 2.8s ease-in-out infinite}
.s1{left:14%;top:36px}.s2{right:15%;top:68px;animation-delay:.7s}.s3{left:25%;top:118px;animation-delay:1.3s}.s4{right:25%;top:128px;animation-delay:1.9s}
.leaf{top:-35px;animation:fall 7s linear infinite}.l1{left:8%}.l2{left:27%;animation-delay:2s}.l3{right:24%;animation-delay:1s}.l4{right:7%;animation-delay:3.2s}
.wish{position:absolute;left:18px;right:18px;bottom:25px;text-align:center;opacity:0;animation:up .8s .55s ease forwards}
.wish small{font-weight:900;letter-spacing:.12em;color:#ffffffb8}.wish h1{margin:6px 0 7px;font-size:clamp(25px,5vw,35px);letter-spacing:-.04em}.wish p{margin:0;color:#ffffffd4;font-size:13px}
.result{margin-top:16px;padding:24px;border:1px solid #e9ebf3;border-radius:26px;background:#fffffffa;box-shadow:0 16px 45px #29334f16}
.head{display:flex;gap:13px;align-items:flex-start;margin-bottom:18px}.ok{display:grid;place-items:center;width:50px;height:50px;flex:0 0 50px;border-radius:16px;background:#eaf8f0;font-size:24px}.head h2{margin:0;font-size:21px;letter-spacing:-.03em}.head p{margin:5px 0 0;color:#80889b;font-size:13px}
.info{overflow:hidden;margin:0;border:1px solid #e8eaf2;border-radius:18px;background:#fbfcff}.row{display:grid;grid-template-columns:105px 1fr;gap:12px;padding:13px 15px;border-bottom:1px solid #edf0f5}.row:last-child{border:0}.row dt,.row dd{margin:0}.row dt{color:#8a91a3;font-size:12px;font-weight:800}.row dd{font-size:14px;font-weight:900;word-break:break-all}
.note{margin-top:14px;padding:13px 15px;border-radius:15px;background:#f4f2ff;border:1px solid #e1ddff;color:#5d58a1;font-size:12px;line-height:1.6}
.sync{margin-top:12px;padding:13px 15px;border-radius:15px;background:#f7f9fc;border:1px solid #e6eaf1;color:#56627a;font-size:12px;line-height:1.65}.sync span{display:block}
.actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}.actions .btn{display:flex;align-items:center;justify-content:center;min-height:50px;border-radius:15px;font-weight:900}.primary{background:linear-gradient(135deg,#695fd7,#584dc3)!important;box-shadow:0 10px 23px #584dc333}
.foot{text-align:center;margin-top:15px;color:#949aab;font-size:11px;line-height:1.7}.foot b{color:#675fc0}
@keyframes moon{0%{opacity:0;transform:translate(-50%,65px) scale(.65)}70%{opacity:1;transform:translate(-50%,-5px) scale(1.05)}100%{opacity:1;transform:translate(-50%,0) scale(1)}}
@keyframes rabbit{0%{opacity:0;transform:translateY(25px) rotate(12deg)}100%{opacity:1;transform:none}}
@keyframes twinkle{0%,100%{opacity:.25;transform:scale(.7)}50%{opacity:1;transform:scale(1.2) rotate(10deg)}}
@keyframes fall{0%{opacity:0;transform:translateY(-25px) rotate(0)}10%{opacity:.9}50%{transform:translate(25px,155px) rotate(160deg)}100%{opacity:0;transform:translate(-18px,340px) rotate(330deg)}}
@keyframes up{from{opacity:0;transform:translateY(17px)}to{opacity:1;transform:none}}
@media(max-width:560px){.done-wrap{padding-top:9px}.festival{min-height:265px;border-radius:24px}.moon{width:100px;height:100px}.moon:after{font-size:43px}.rabbit{top:104px}.result{padding:18px 15px;border-radius:22px}.row{grid-template-columns:88px 1fr;padding:12px}.actions{grid-template-columns:1fr}}
.payment-date{
    display:flex;
    align-items:center;
    gap:12px;
    margin:0 0 18px;
    padding:14px 16px;
    border-radius:16px;
    background:linear-gradient(135deg,#fff8df,#fff3c4);
    border:1px solid #f4dfa0;
}

.payment-icon{
    display:grid;
    place-items:center;
    width:42px;
    height:42px;
    flex:0 0 42px;
    border-radius:13px;
    background:#fff;
    font-size:22px;
}

.payment-date small{
    display:block;
    margin-bottom:2px;
    color:#8c7330;
    font-size:11px;
    font-weight:800;
}

.payment-date strong{
    display:block;
    color:#5f4915;
    font-size:18px;
    font-weight:900;
    letter-spacing:-.03em;
}
</style>
</head>
<body>
<main class="done-wrap">
<section class="festival">
  <span class="star s1">✨</span><span class="star s2">⭐</span><span class="star s3">✨</span><span class="star s4">💫</span>
  <span class="leaf l1">🍂</span><span class="leaf l2">🍁</span><span class="leaf l3">🍂</span><span class="leaf l4">🍁</span>
  <div class="moon"></div><span class="rabbit">🐇</span>
  <div class="wish">
    <small>HAPPY CHUSEOK</small>
    <h1>풍성하고 행복한 한가위 보내세요</h1>
    <p>소중한 분들과 따뜻하고 즐거운 추석 명절 되시길 바랍니다. 🎁</p>
  </div>
</section>

<section class="result">
  <div class="head">
    <div class="ok">✅</div>
    <div><h2>계좌정보 제출이 완료되었습니다</h2><p><?=$replaced?'기존 제출내용은 새 정보로 변경되었습니다.':'입력하신 정보가 정상적으로 접수되었습니다.'?></p></div>
  </div>

  <div class="payment-date">
    <span class="payment-icon">💰</span>
    <div>
        <small>추석선물 입금 예정일</small>
        <strong>9월 17일(목)</strong>
    </div>
</div>

  <dl class="info">
    <div class="row"><dt>🎯 동아리</dt><dd><?=e($club)?></dd></div>
    <div class="row"><dt>👤 성명</dt><dd><?=e($name)?></dd></div>
    <div class="row"><dt>🏦 은행명</dt><dd><?=e($bank)?></dd></div>
    <div class="row"><dt>💳 계좌번호</dt><dd><?=e($account)?></dd></div>
  </dl>

  <?php if($replaced): ?>
    <div class="note">🔄 같은 성명과 연락처의 기존 제출자료는 삭제되고 <b>이번 최신 정보만 저장</b>되었습니다.</div>
  <?php endif; ?>

  <?php
  $syncLines=[];
  if(array_key_exists('cul',$memberSync) && $memberSync['cul']!==null) $syncLines[]=[(bool)$memberSync['cul'],'문화탐방 회원명부'];
  if(array_key_exists('mt',$memberSync) && $memberSync['mt']!==null) $syncLines[]=[(bool)$memberSync['mt'],'산악회 회원명부'];
  ?>
  <?php if($syncLines): ?>
    <div class="sync"><b>📌 회원명부 반영 상태</b>
    <?php foreach($syncLines as [$ok,$label]): ?><span><?=$ok?'✅':'⚠️'?> <?=e($label)?> <?=$ok?'반영완료':'미매칭'?></span><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="actions">
    <a class="btn primary" href="index.php">🎁 처음 화면으로</a>
    <button class="btn secondary" type="button" onclick="window.close()">창 닫기</button>
  </div>
  <div class="foot">🌕 <b>즐거운 추석 보내세요.</b><br>함께해 주셔서 감사합니다.</div>
</section>
</main>
</body>
</html>
