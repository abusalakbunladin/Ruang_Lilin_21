<?php
// File: api/auth.php
// Proyek: 21: Ruang Lilin
// Fungsi: Endpoint autentikasi: register, login, logout, session, dan banned check.
require 'db.php';
require 'mail.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'status';

function generateCode() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendVerificationEmail($pdo, $userId, $email, $type = 'register') {
    $code = generateCode();
    $expires = ($type === 'reset') ? '10' : '15';
    $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, email, code, type, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))');
    $stmt->execute([$userId, $email, $code, $type, $expires]);
    if ($type === 'register') {
        $subject = 'Kode Verifikasi - 21: Ruang Lilin';
        $body = "Hai,\n\nKode verifikasi pendaftaranmu adalah: $code\nKode berlaku 15 menit.\n\nJika bukan kamu, abaikan email ini.";
    } else {
        $subject = 'Kode Reset Password - 21: Ruang Lilin';
        $body = "Hai,\n\nKode reset passwordmu adalah: $code\nKode berlaku 10 menit.\n\nJika bukan kamu, abaikan email ini.";
    }
    $result = sendMail($email, $subject, $body);
    return [$result['success'], $code, $result['error']];
}

if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if (strlen($username) < 3 || strlen($username) > 32 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        jsonResponse(['ok' => false, 'error' => 'Username 3-32 karakter alphanumeric.']);
    }
    if (strlen($password) < 6) {
        jsonResponse(['ok' => false, 'error' => 'Password minimal 6 karakter.']);
    }
    // Email OPSIONAL dan tidak lagi berpengaruh apapun ke akses bermain --
    // kolom ini dipertahankan hanya untuk kompatibilitas akun lama & fitur lupa
    // password (opsional) lewat email, bukan syarat untuk mendaftar/login/main.
    $hasEmail = ($email !== '');
    if ($hasEmail && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'error' => 'Email tidak valid.']);
    }
    $emailToStore = $hasEmail ? $email : null;

    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $emailToStore, $hash, 'user']);
        $uid = $pdo->lastInsertId();
        $_SESSION['user_id'] = $uid;
        $_SESSION['username'] = $username;

        $sent = false; $code = null; $sendError = null;
        if ($hasEmail) {
            list($sent, $code, $sendError) = sendVerificationEmail($pdo, $uid, $email, 'register');
        }

        $resp = ['ok' => true, 'email_sent' => $sent, 'has_email' => $hasEmail, 'user' => [
            'id' => (int)$uid,
            'username' => $username,
            'email' => $emailToStore,
            'email_verified' => false,
            'wins' => 0,
            'losses' => 0,
            'rank' => 'Jiwa Tersesat',
            'rating' => 1000,
            'rank_points' => 0,
            'hard_clear' => false,
            'campaign_completed' => false,
            'campaign_best_mode' => null,
            'role' => 'user',
            'banned' => false,
            'ban_reason' => null
        ]];
        if ($hasEmail && !$sent) $resp['send_error'] = $sendError;
        jsonResponse($resp);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            if (stripos($e->getMessage(), 'idx_email') !== false || stripos($e->getMessage(), 'email') !== false) {
                jsonResponse(['ok' => false, 'error' => 'Email sudah dipakai.']);
            }
            jsonResponse(['ok' => false, 'error' => 'Username sudah dipakai.']);
        }
        jsonResponse(['ok' => false, 'error' => 'Gagal registrasi.']);
    }
}

if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, username, email, email_verified, password, wins, losses, `rank`, rating, rank_points, hard_clear, campaign_completed, campaign_best_mode, `role`, banned, ban_reason FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        if ($user['banned']) {
            jsonResponse(['ok' => false, 'error' => 'Akun dibanned: ' . ($user['ban_reason'] ?: 'melanggar aturan.'), 'banned' => true]);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['email_verified'] = $user['email_verified'];
        $_SESSION['rating'] = $user['rating'];
        $_SESSION['rank_points'] = $user['rank_points'];
        $_SESSION['wins'] = $user['wins'];
        $_SESSION['losses'] = $user['losses'];
        $_SESSION['rank'] = rankTitleForRating($user['rating']);
        $_SESSION['hard_clear'] = $user['hard_clear'];
        $_SESSION['campaign_completed'] = $user['campaign_completed'];
        $_SESSION['campaign_best_mode'] = $user['campaign_best_mode'];
        $_SESSION['role'] = $user['role'];
        jsonResponse(['ok' => true, 'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'email_verified' => (bool)$user['email_verified'],
            'wins' => (int)$user['wins'],
            'losses' => (int)$user['losses'],
            'rank' => rankTitleForRating($user['rating']),
            'rating' => (int)$user['rating'],
            'rank_points' => (int)$user['rank_points'],
            'hard_clear' => (bool)$user['hard_clear'],
            'campaign_completed' => (bool)$user['campaign_completed'],
            'campaign_best_mode' => $user['campaign_best_mode'],
            'role' => $user['role'],
            'banned' => (bool)$user['banned'],
            'ban_reason' => $user['ban_reason']
        ]]);
    }
    jsonResponse(['ok' => false, 'error' => 'Username atau password salah.']);
}

if ($action === 'logout') {
    session_destroy();
    jsonResponse(['ok' => true]);
}

if ($action === 'status') {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT id, username, email, email_verified, wins, losses, `rank`, rating, rank_points, hard_clear, campaign_completed, campaign_best_mode, `role`, banned, ban_reason FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            if ($user['banned']) {
                session_destroy();
                jsonResponse(['ok' => false, 'error' => 'Akun dibanned: ' . ($user['ban_reason'] ?: 'melanggar aturan.'), 'banned' => true]);
            }
            // Update session with latest data
            $_SESSION['email'] = $user['email'];
            $_SESSION['email_verified'] = $user['email_verified'];
            $_SESSION['rating'] = $user['rating'];
            $_SESSION['rank_points'] = $user['rank_points'];
            $_SESSION['wins'] = $user['wins'];
            $_SESSION['losses'] = $user['losses'];
            $_SESSION['rank'] = rankTitleForRating($user['rating']);
            $_SESSION['hard_clear'] = $user['hard_clear'];
            $_SESSION['campaign_completed'] = $user['campaign_completed'];
            $_SESSION['campaign_best_mode'] = $user['campaign_best_mode'];
            $_SESSION['role'] = $user['role'];
            
            jsonResponse(['ok' => true, 'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'email_verified' => (bool)$user['email_verified'],
                'wins' => (int)$user['wins'],
                'losses' => (int)$user['losses'],
                'rank' => rankTitleForRating($user['rating']),
                'rating' => (int)$user['rating'],
                'rank_points' => (int)$user['rank_points'],
                'hard_clear' => (bool)$user['hard_clear'],
                'campaign_completed' => (bool)$user['campaign_completed'],
                'campaign_best_mode' => $user['campaign_best_mode'],
                'role' => $user['role'],
                'banned' => (bool)$user['banned'],
                'ban_reason' => $user['ban_reason']
            ]]);
        }
    }
    jsonResponse(['ok' => false]);
}

if ($action === 'verify_email') {
    requireAuth();
    $code = trim($_POST['code'] ?? '');
    if (!$code) jsonResponse(['ok' => false, 'error' => 'Masukkan kode verifikasi.']);
    $uid = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT id, email FROM email_verifications WHERE user_id = ? AND code = ? AND type = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$uid, $code, 'register']);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['ok' => false, 'error' => 'Kode salah atau sudah expired.']);

    $pdo->prepare('UPDATE email_verifications SET used = 1 WHERE id = ?')->execute([$row['id']]);
    // PERBAIKAN: sebelumnya ada syarat "AND rating = 0" yang keliru -- akun baru justru
    // dibuat dengan rating default 1000 (bukan 0), jadi kondisi itu TIDAK PERNAH terpenuhi
    // dan email_verified tidak pernah benar-benar ter-set meski kode yang dimasukkan benar.
    $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = ?')->execute([$uid]);

    $stmt = $pdo->prepare('SELECT id, username, email, email_verified, wins, losses, `rank`, rating, rank_points, `role`, banned, ban_reason FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    
    // Update session with new data
    $_SESSION['email'] = $user['email'];
    $_SESSION['email_verified'] = $user['email_verified'];
    $_SESSION['rating'] = $user['rating'];
    $_SESSION['rank_points'] = $user['rank_points'];
    $_SESSION['wins'] = $user['wins'];
    $_SESSION['losses'] = $user['losses'];
    $_SESSION['rank'] = rankTitleForRating($user['rating']);
    
    jsonResponse(['ok' => true, 'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'email_verified' => (bool)$user['email_verified'],
        'wins' => (int)$user['wins'],
        'losses' => (int)$user['losses'],
        'rank' => rankTitleForRating($user['rating']),
        'rating' => (int)$user['rating'],
        'rank_points' => (int)$user['rank_points'],
        'role' => $user['role'],
        'banned' => (bool)$user['banned'],
        'ban_reason' => $user['ban_reason']
    ]]);
}

if ($action === 'update_email') {
    requireAuth();
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['ok' => false, 'error' => 'Email tidak valid.']);
    $uid = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $uid]);
    if ($stmt->fetch()) jsonResponse(['ok' => false, 'error' => 'Email sudah dipakai akun lain.']);

    $pdo->prepare('UPDATE users SET email = ?, email_verified = 0 WHERE id = ?')->execute([$email, $uid]);
    list($sent, $code, $sendError) = sendVerificationEmail($pdo, $uid, $email, 'register');
    $resp = ['ok' => true, 'email_sent' => $sent, 'message' => $sent ? 'Kode verifikasi dikirim ke email.' : 'Email diupdate, tapi gagal mengirim kode. Hubungi admin untuk verifikasi manual.'];
    if (!$sent) $resp['send_error'] = $sendError;
    jsonResponse($resp);
}

if ($action === 'resend_verify') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT email, email_verified FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['ok' => false, 'error' => 'User tidak ditemukan.']);
    if ($user['email_verified']) jsonResponse(['ok' => true, 'email_sent' => true, 'message' => 'Email sudah terverifikasi.']);
    if (!$user['email']) jsonResponse(['ok' => false, 'error' => 'Email belum diisi.']);

    list($sent, $code, $sendError) = sendVerificationEmail($pdo, $uid, $user['email'], 'register');
    $resp = ['ok' => true, 'email_sent' => $sent, 'message' => $sent ? 'Kode verifikasi telah dikirim ulang.' : 'Gagal mengirim email. Hubungi admin untuk verifikasi manual.'];
    if (!$sent) $resp['send_error'] = $sendError;
    jsonResponse($resp);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
