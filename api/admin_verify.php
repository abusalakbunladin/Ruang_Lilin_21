<?php
// File: api/admin_verify.php
// Proyek: 21: Ruang Lilin
// Fungsi: Endpoint admin/support untuk verifikasi email dan reset password pemain lain.
require 'db.php';
require 'mail.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'list_unverified') {
    requireAdmin();
    $stmt = $pdo->query('SELECT id, username, email, created_at FROM users WHERE email_verified = 0 ORDER BY id DESC');
    jsonResponse(['ok' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'verify_email') {
    requireAdmin();
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid <= 0) jsonResponse(['ok' => false, 'error' => 'User ID tidak valid.']);
    $stmt = $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = ?');
    $stmt->execute([$uid]);
    jsonResponse(['ok' => true, 'message' => 'Email terverifikasi.']);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
