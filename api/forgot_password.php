<?php
// File: api/forgot_password.php
// Proyek: 21: Ruang Lilin
// Fungsi: Endpoint permintaan reset password dengan kode email.
require 'db.php';
require 'mail.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'request';

function generateCode() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

if ($action === 'request') {
    $username = trim($_POST['username'] ?? '');
    if (!$username) jsonResponse(['ok' => false, 'error' => 'Masukkan username.']);

    $stmt = $pdo->prepare('SELECT id, email, email_verified FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['ok' => false, 'error' => 'Username tidak ditemukan.']);
    if (!$user['email'] || !$user['email_verified']) {
        jsonResponse(['ok' => false, 'error' => 'Akun ini belum memiliki email terverifikasi. Hubungi support jika lupa password.']);
    }

    $code = generateCode();
    $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, email, code, type, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
    $stmt->execute([$user['id'], $user['email'], $code, 'reset']);

    $subject = 'Kode Reset Password - 21: Ruang Lilin';
    $body = "Hai,\n\nKode reset passwordmu adalah: $code\nKode berlaku 10 menit.\n\nJika bukan kamu, abaikan email ini.";
    $mailResult = sendMail($user['email'], $subject, $body);
    $sent = $mailResult['success'];

    $resp = ['ok' => true, 'email_sent' => $sent, 'message' => $sent ? 'Kode reset password dikirim ke email.' : 'Gagal mengirim email. Hubungi admin untuk reset manual.'];
    if (!$sent) $resp['send_error'] = $mailResult['error'];
    jsonResponse($resp);
}

if ($action === 'reset') {
    $username = trim($_POST['username'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    if (!$username || !$code || strlen($newPassword) < 6) {
        jsonResponse(['ok' => false, 'error' => 'Username, kode, dan password baru (min 6 karakter) wajib diisi.']);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['ok' => false, 'error' => 'User tidak ditemukan.']);

    $stmt = $pdo->prepare('SELECT id, email FROM email_verifications WHERE user_id = ? AND code = ? AND type = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$user['id'], $code, 'reset']);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['ok' => false, 'error' => 'Kode salah atau sudah expired.']);

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $user['id']]);
    $pdo->prepare('UPDATE email_verifications SET used = 1 WHERE id = ?')->execute([$row['id']]);

    jsonResponse(['ok' => true, 'message' => 'Password berhasil diubah. Silakan login.']);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
