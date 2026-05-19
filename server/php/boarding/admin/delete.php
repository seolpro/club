<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_admin();
$id=(int)($_GET['id']??0);
if($id>0){$stmt=db()->prepare('DELETE FROM '.tb('applications').' WHERE id=?');$stmt->execute([$id]);}
header('Location: index.php');
