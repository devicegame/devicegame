<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_admin(); // login + is_admin kontrol
$user = current_user($pdo);
