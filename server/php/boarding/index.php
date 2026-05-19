<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>승선자 등록</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{--brand:#2563eb;--soft:#eef4ff;--ink:#0f172a}
    body{min-height:100vh;background:linear-gradient(135deg,#eef4ff 0%,#f8fafc 45%,#ecfeff 100%);color:var(--ink)}
    .hero{max-width:760px;margin:0 auto}.glass{background:rgba(255,255,255,.88);backdrop-filter:blur(14px);border:1px solid rgba(148,163,184,.25);box-shadow:0 20px 55px rgba(15,23,42,.12);border-radius:28px}.badge-soft{background:var(--soft);color:var(--brand)}
    .form-control,.form-select{border-radius:14px;padding:.78rem .9rem}.btn{border-radius:16px;padding:.78rem 1rem;font-weight:700}.passenger-card{border:1px solid #e2e8f0;border-radius:20px;padding:1rem;background:#fff}.sticky-submit{position:sticky;bottom:12px;z-index:3}.small-help{font-size:.88rem;color:#64748b}
  </style>
</head>
<body>
<div class="container py-4 py-md-5">
  <div class="hero">
    <div class="text-center mb-4">
      <span class="badge rounded-pill badge-soft px-3 py-2 mb-3">🚢 승선자 명단 제출</span>
      <h2 class="fw-bold mb-2">트래킹 참여 승선자 등록</h2>
      <p class="text-secondary mb-0">대표자 정보와 승선자 명단을 정확히 입력해 주세요.</p>
    </div>

    <form id="boardingForm" class="glass p-3 p-md-4" novalidate>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">대표자 성명 <span class="text-danger">*</span></label>
          <input type="text" class="form-control" placeholder="회원 또는 동행인 성명" id="leaderName" required oninput="syncLeaderToFirstPassenger()">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">대표자 연락처 <span class="text-danger">*</span></label>
          <input type="tel" class="form-control" id="leaderContact" placeholder="01012345678" inputmode="numeric" required>
          <div class="small-help mt-1">숫자만 입력해 주세요.</div>
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
        <div>
          <h5 class="fw-bold mb-1">👥 승선자 명단</h5>
          <div class="small-help">첫 번째 승선자 성명은 대표자 성명과 자동 동기화됩니다.</div>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPassenger()">➕ 추가</button>
      </div>
      <div id="passengerList" class="d-grid gap-2"></div>

      <div class="mt-4">
        <label class="form-label fw-semibold">기타 코멘트</label>
        <textarea class="form-control" id="comment" rows="2" placeholder="선택 입력"></textarea>
      </div>

      <div class="mt-4">
        <button class="btn btn-outline-warning w-100" type="button" data-bs-toggle="collapse" data-bs-target="#consentDetails">
          📌 개인정보 수집 및 활용 동의 내용 보기/닫기
        </button>
        <div class="collapse mt-2" id="consentDetails">
          <div class="card card-body bg-light border-0 rounded-4">
            본 신청서에서는 승선자 명단 제출을 위해 다음의 개인정보를 수집·활용 및 제3자에게 제공합니다.
            <ul class="mt-2 mb-1">
              <li>성명, 연락처, 생년월일, 성별</li>
              <li>개인정보 제3자 제공: 유한회사 대부해운</li>
            </ul>
            수집된 정보는 해당 목적 외에는 활용되지 않으며, 목적 달성 후 파기합니다.
          </div>
        </div>
      </div>

      <div class="form-check mt-3 mb-4">
        <input class="form-check-input" type="checkbox" id="consentCheck" required>
        <label class="form-check-label" for="consentCheck">(필수) 승선명단 제출 관련 개인정보 수집·활용 및 제3자 제공에 동의합니다.</label>
      </div>

      <div class="sticky-submit">
        <button type="submit" class="btn btn-primary w-100 shadow" id="submitBtn">📤 제출하기</button>
      </div>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const form = document.getElementById('boardingForm');
const submitBtn = document.getElementById('submitBtn');
const passengerList = document.getElementById('passengerList');
let passengerSeq = 0;

function escapeHtml(str){return String(str).replace(/[&<>'"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function resetPassengersToDefault(){passengerList.innerHTML=''; passengerSeq=0; addPassenger(); syncLeaderToFirstPassenger();}
function addPassenger(name='', birth='', gender=''){
  passengerSeq++;
  const group=document.createElement('div');
  group.className='passenger-card passenger-group';
  group.innerHTML=`
    <div class="d-flex justify-content-between align-items-center mb-2">
      <strong>승선자 ${passengerSeq}</strong>
      ${passengerSeq>1 ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.passenger-group').remove()">삭제</button>` : `<span class="badge text-bg-primary">대표자</span>`}
    </div>
    <div class="row g-2">
      <div class="col-12 col-md-4"><input type="text" class="form-control passenger-name" placeholder="성명" value="${escapeHtml(name)}"></div>
      <div class="col-6 col-md-4"><input type="text" class="form-control birth" placeholder="생년월일 6자리" maxlength="6" pattern="\\d{6}" inputmode="numeric" value="${escapeHtml(birth)}"></div>
      <div class="col-6 col-md-4"><select class="form-select gender"><option value="">성별</option><option value="남" ${gender==='남'?'selected':''}>남</option><option value="여" ${gender==='여'?'selected':''}>여</option></select></div>
    </div>`;
  passengerList.appendChild(group);
}
function syncLeaderToFirstPassenger(){const v=document.getElementById('leaderName').value.trim(); const first=passengerList.querySelector('.passenger-group:first-child .passenger-name'); if(first) first.value=v;}
function onlyDigits(v){return String(v).replace(/\D/g,'');}

document.getElementById('leaderContact').addEventListener('input', e => { e.target.value = onlyDigits(e.target.value).slice(0,11); });

document.addEventListener('input', e => { if(e.target.classList.contains('birth')) e.target.value = onlyDigits(e.target.value).slice(0,6); });

form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const leaderName=document.getElementById('leaderName').value.trim();
  const leaderContact=onlyDigits(document.getElementById('leaderContact').value);
  const comment=document.getElementById('comment').value.trim();
  const consent=document.getElementById('consentCheck').checked;

  if(!leaderName || !leaderContact){alert('대표자 성명과 연락처를 입력해 주세요.'); return;}
  if(!/^\d{10,11}$/.test(leaderContact)){alert('연락처는 숫자 10~11자리로 입력해 주세요.'); return;}
  if(!consent){alert('개인정보 수집·활용 동의에 체크해 주세요.'); return;}

  const passengers=[]; let summary=''; let invalid=false;
  document.querySelectorAll('.passenger-group').forEach((group,idx)=>{
    const nameEl=group.querySelector('.passenger-name'); const birthEl=group.querySelector('.birth'); const genderEl=group.querySelector('.gender');
    const name=nameEl.value.trim(); const birth=onlyDigits(birthEl.value); const gender=genderEl.value;
    [nameEl,birthEl,genderEl].forEach(el=>el.classList.remove('is-invalid'));
    if(!name){nameEl.classList.add('is-invalid'); invalid=true;}
    if(!/^\d{6}$/.test(birth)){birthEl.classList.add('is-invalid'); invalid=true;}
    if(!['남','여'].includes(gender)){genderEl.classList.add('is-invalid'); invalid=true;}
    if(name && /^\d{6}$/.test(birth) && ['남','여'].includes(gender)){
      passengers.push({name,birth,gender}); summary += `- ${name} (${birth}, ${gender})\n`;
    }
  });
  if(invalid || passengers.length<1){alert('승선자 정보를 정확히 입력해 주세요.'); return;}
  if(!confirm(`입력된 승선자 명단을 확인해 주세요.\n\n${summary}\n제출하시겠습니까?`)) return;

  submitBtn.disabled=true; submitBtn.innerHTML='⏳ 제출중...';
  try{
    const res=await fetch('api_submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({leaderName,leaderContact,passengers,comment,consent:true})});
    const json=await res.json();
    if(!json.ok) throw new Error(json.msg || '저장 실패');
    alert('✅ 제출이 완료되었습니다.'); form.reset(); resetPassengersToDefault();
  }catch(err){alert('⚠ 오류 발생: '+err.message);} finally{submitBtn.disabled=false; submitBtn.innerHTML='📤 제출하기';}
});
document.addEventListener('DOMContentLoaded', resetPassengersToDefault);
</script>
</body>
</html>
