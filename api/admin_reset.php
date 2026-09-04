<?php
require_once 'db.php';

$action = $_POST['action'] ?? '';

switch ($action) {
  case 'reset':
    // Admin directly resets a user's password
    requireAdmin();
    $username = trim($_POST['username'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    
    if (empty($username)) {
      echo json_encode(['ok' => false, 'error' => 'Username wajib diisi.']);
      exit;
    }
    
    if (strlen($newPassword) < 6) {
      echo json_encode(['ok' => false, 'error' => 'Password minimal 6 karakter.']);
      exit;
    }
    
    // Get user by username
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
      echo json_encode(['ok' => false, 'error' => 'User tidak ditemukan.']);
      exit;
    }
    
    // Update password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hashedPassword, $user['id']]);
    
    echo json_encode(['ok' => true, 'message' => 'Password berhasil direset untuk ' . $user['username']]);
    break;
    
  default:
    echo json_encode(['ok' => false, 'error' => 'Action tidak valid.']);
}
