<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_admin();
$id=(int)($_GET['id']??0); if($id<1){header('Location:index.php');exit;}
$pdo=db();
$stmt=$pdo->prepare('SELECT * FROM '.tb('applications').' WHERE id=?'); $stmt->execute([$id]); $app=$stmt->fetch(); if(!$app){header('Location:index.php');exit;}
$stmt=$pdo->prepare('SELECT * FROM '.tb('passengers').' WHERE application_id=? ORDER BY sort_order,id'); $stmt->execute([$id]); $passengers=$stmt->fetchAll();
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>접수 상세</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f8fafc}.box{background:#fff;border-radius:24px;box-shadow:0 12px 30px rgba(15,23,42,.08);padding:1.5rem}</style></head><body><div class="container py-4"><a href="index.php" class="btn btn-outline-secondary mb-3">← 목록</a><div class="box"><h3 class="fw-bold mb-3">접수 상세 #<?=e($app['id'])?></h3><div class="row g-3 mb-4"><div class="col-md-3"><b>접수일시</b><div><?=e($app['created_at'])?></div></div><div class="col-md-3"><b>대표자</b><div><?=e($app['leader_name'])?></div></div><div class="col-md-3"><b>연락처</b><div><?=e($app['leader_contact'])?></div></div><div class="col-md-3"><b>승선자수</b><div><?=e($app['passenger_count'])?>명</div></div><div class="col-12"><b>코멘트</b><div class="border rounded p-3 bg-light"><?=nl2br(e($app['comment']))?></div></div></div><h5 class="fw-bold">승선자 명단</h5><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>순번</th><th>성명</th><th>생년월일</th><th>성별</th></tr></thead><tbody><?php foreach($passengers as $p): ?><tr><td><?=e($p['sort_order'])?></td><td><?=e($p['passenger_name'])?></td><td><?=e($p['birth6'])?></td><td><?=e($p['gender'])?></td></tr><?php endforeach; ?></tbody></table></div></div></div></body></html>
