<?php
require 'db.php';

requireAuth();

$uid = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$uid]);
$me = $stmt->fetch();
if (!$me || !in_array($me['role'], ['admin', 'support'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Akses ditolak. Hanya admin/support.']);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'reset';

if ($action === 'reset') {
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    if (!$username || strlen($newPassword) < 6) {
        jsonResponse(['ok' => false, 'error' => 'Username dan password baru (min 6 karakter) wajib diisi.']);
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['ok' => false, 'error' => 'User tidak ditemukan.']);

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hash, $user['id']]);
    jsonResponse(['ok' => true, 'message' => 'Password ' . $username . ' berhasil diubah.']);
}

if ($action === 'list') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') jsonResponse(['ok' => true, 'users' => []]);
    $stmt = $pdo->prepare('SELECT id, username, role, wins, losses, `rank` FROM users WHERE username LIKE ? LIMIT 20');
    $stmt->execute(['%' . $q . '%']);
    jsonResponse(['ok' => true, 'users' => $stmt->fetchAll()]);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
