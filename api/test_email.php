<?php
require 'db.php';

function requireAuthAdmin($pdo) {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        jsonResponse(['ok' => false, 'error' => 'Not authenticated']);
    }
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !in_array($user['role'], ['admin', 'support'], true)) {
        http_response_code(403);
        jsonResponse(['ok' => false, 'error' => 'Forbidden']);
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'test') {
    requireAuthAdmin($pdo);
    $to = trim($_POST['to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'error' => 'Email tujuan tidak valid.']);
    }
    $subject = 'Tes Email - 21: Ruang Lilin';
    $body = "Ini adalah email uji coba dari 21: Ruang Lilin.\n\nJika kamu menerima ini, konfigurasi SMTP berhasil.";
    $result = sendMail($to, $subject, $body);
    if ($result['success']) {
        jsonResponse(['ok' => true, 'message' => 'Email tes berhasil dikirim ke ' . $to]);
    }
    jsonResponse(['ok' => false, 'error' => $result['error'] ?? 'Gagal mengirim email.']);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
