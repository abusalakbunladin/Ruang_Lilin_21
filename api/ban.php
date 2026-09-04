<?php
// File: api/ban.php
// Proyek: 21: Ruang Lilin
// Fungsi: Endpoint ban/unban user dan daftar banned (hanya admin).
require 'db.php';

function requireRole($pdo, $roles) {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        jsonResponse(['ok' => false, 'error' => 'Not authenticated']);
    }
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        jsonResponse(['ok' => false, 'error' => 'Forbidden']);
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'list_banned') {
    requireRole($pdo, ['admin']);
    $stmt = $pdo->query('SELECT id, username, email, ban_reason, ban_description, created_at FROM users WHERE banned = 1 ORDER BY id DESC');
    jsonResponse(['ok' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'ban') {
    requireRole($pdo, ['admin']);
    $username = trim($_POST['username'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (!$username) jsonResponse(['ok' => false, 'error' => 'Username wajib diisi.']);
    $allowed = ['Toxic / Ujaran kebencian', 'Cheating / Eksploitasi bug', 'Spam / Iklan', 'Nama / Foto tidak pantas', 'Tidak sportif / AFK berulang', 'Lainnya'];
    if (!$reason || !in_array($reason, $allowed, true)) jsonResponse(['ok' => false, 'error' => 'Pilih alasan dari dropdown.']);

    $stmt = $pdo->prepare('UPDATE users SET banned = 1, ban_reason = ?, ban_description = ? WHERE username = ?');
    $stmt->execute([$reason, $description ?: null, $username]);
    if ($stmt->rowCount() === 0) jsonResponse(['ok' => false, 'error' => 'User tidak ditemukan.']);
    jsonResponse(['ok' => true, 'message' => 'User ' . $username . ' berhasil dibanned.']);
}

if ($action === 'unban') {
    requireRole($pdo, ['admin']);
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) jsonResponse(['ok' => false, 'error' => 'User ID tidak valid.']);
    $pdo->prepare('UPDATE users SET banned = 0, ban_reason = NULL, ban_description = NULL WHERE id = ?')->execute([$userId]);
    jsonResponse(['ok' => true, 'message' => 'User di-unban.']);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
