<?php
require_once dirname(__DIR__) . '/config.php';
unset($_SESSION['gift_admin_id'], $_SESSION['gift_admin_name']);
session_regenerate_id(true);
redirect('login.php');
