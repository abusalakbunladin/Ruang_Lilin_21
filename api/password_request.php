<?php
// File: api/password_request.php
// Proyek: 21: Ruang Lilin
// Fungsi: API untuk permintaan ganti password (user request, admin approve/reject, cancel/edit)

// Clean any previous output and start fresh
while (ob_get_level()) { ob_end_clean(); }
ob_start();

// Suppress all errors and warnings to ensure clean JSON output
@ini_set('display_errors', '0');
@error_reporting(E_ERROR);

try {
  require_once 'db.php';
  require_once 'mail.php';
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
  exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Store response in variable instead of echoing
$json_output = '';
$should_exit = false;

try {
  switch ($action) {
    case 'create':
      requireAuth();
      $userId = $_SESSION['user_id'];
      $reason = trim($_POST['reason'] ?? '');

      if (empty($reason)) {
        $json_output = json_encode(['ok' => false, 'error' => 'Mohon berikan alasan untuk permintaan ganti password.']);
        $should_exit = true;
      } else {
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
          $json_output = json_encode(['ok' => false, 'error' => 'User tidak ditemukan.']);
          $should_exit = true;
        } else {
          $stmt = $pdo->prepare("SELECT id FROM password_requests WHERE user_id = ? AND status = 'pending'");
          $stmt->execute([$userId]);
          if ($stmt->fetch()) {
            $json_output = json_encode(['ok' => false, 'error' => 'Anda sudah memiliki permintaan yang sedang diproses. Tunggu sampai selesai.']);
            $should_exit = true;
          } else {
            $stmt = $pdo->prepare("INSERT INTO password_requests (user_id, username, email, reason, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$userId, $user['username'], $user['email'], $reason]);
            $json_output = json_encode(['ok' => true, 'message' => 'Permintaan ganti password berhasil dikirim. Admin akan memproses permintaan Anda.']);
            $should_exit = true;
          }
        }
      }
      break;

    case 'list':
      requireAdmin();
      $status = $_GET['status'] ?? 'pending';

      $stmt = $pdo->prepare("
        SELECT pr.*,
          CASE
            WHEN pr.status = 'pending' THEN 'Menunggu'
            WHEN pr.status = 'approved' THEN 'Disetujui'
            WHEN pr.status = 'rejected' THEN 'Ditolak'
          END as status_label,
          a.username as admin_name
        FROM password_requests pr
        LEFT JOIN users a ON pr.admin_id = a.id
        WHERE pr.status = ?
        ORDER BY pr.created_at DESC
        LIMIT 50
      ");
      $stmt->execute([$status]);
      $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $json_output = json_encode(['ok' => true, 'requests' => $requests]);
      $should_exit = true;
      break;

    case 'approve':
      requireAdmin();
      $requestId = intval($_POST['request_id'] ?? 0);
      $adminNote = trim($_POST['admin_note'] ?? '');

      if ($requestId <= 0) {
        $json_output = json_encode(['ok' => false, 'error' => 'ID permintaan tidak valid.']);
        $should_exit = true;
      } else {
        $stmt = $pdo->prepare("SELECT * FROM password_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
          $json_output = json_encode(['ok' => false, 'error' => 'Permintaan tidak ditemukan atau sudah diproses.']);
          $should_exit = true;
        } else {
          // Mark as approved
          $stmt = $pdo->prepare("UPDATE password_requests SET status = 'approved', admin_id = ?, admin_note = ? WHERE id = ?");
          $stmt->execute([$_SESSION['user_id'], $adminNote, $requestId]);

          // Kirim notifikasi ke Inbox user (pesan sistem/admin) supaya user tahu
          // permintaannya disetujui walau tidak sedang membuka email. Dibungkus
          // try/catch sendiri: kalau gagal, proses approve utama tetap dianggap
          // berhasil (tidak mengubah $json_output di atas/bawah baris ini).
          try {
              $inboxBody = 'Permintaan ganti password kamu telah DISETUJUI.';
              if ($adminNote !== '') $inboxBody .= "\nCatatan admin: " . $adminNote;
              $inboxBody .= "\nCek email terdaftar untuk kode verifikasi, lalu buka menu \"Lupa Password\" di halaman login.";
              $insMsg = $pdo->prepare('INSERT INTO messages (room_id, from_user_id, to_user_id, body) VALUES (NULL, ?, ?, ?)');
              $insMsg->execute([(int)$_SESSION['user_id'], (int)$request['user_id'], $inboxBody]);
          } catch (PDOException $eMsg) { /* notifikasi inbox gagal tidak menggagalkan approve */ }

          // Ambil data user pemilik permintaan untuk pengiriman kode verifikasi
          $stmtU = $pdo->prepare('SELECT id, username, email, email_verified FROM users WHERE id = ?');
          $stmtU->execute([$request['user_id']]);
          $userRow = $stmtU->fetch(PDO::FETCH_ASSOC);

          if ($userRow && !empty($userRow['email']) && !empty($userRow['email_verified'])) {
            // Buat kode verifikasi asli (6 digit, berlaku 30 menit) dan benar-benar kirim lewat email
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $insCode = $pdo->prepare('INSERT INTO email_verifications (user_id, email, code, type, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
            $insCode->execute([$userRow['id'], $userRow['email'], $code, 'reset']);

            $subject = 'Kode Ganti Password Disetujui - 21: Ruang Lilin';
            $body = "Hai " . $userRow['username'] . ",\n\nPermintaan ganti passwordmu telah disetujui admin.\n\nKode verifikasi: " . $code . "\nKode berlaku 30 menit.\n\nBuka menu \"Lupa Password\" di halaman login, masukkan username dan kode ini untuk membuat password baru.\n\nJika bukan kamu yang meminta ini, abaikan email ini.";
            $mailResult = sendMail($userRow['email'], $subject, $body);

            if ($mailResult['success']) {
              $json_output = json_encode(['ok' => true, 'message' => 'Permintaan disetujui. Kode verifikasi sudah dikirim ke email user (' . $userRow['email'] . '). User bisa memakainya lewat menu "Lupa Password" untuk membuat password baru.']);
            } else {
              $json_output = json_encode(['ok' => true, 'message' => 'Permintaan disetujui, tapi pengiriman email GAGAL (' . $mailResult['error'] . '). Sampaikan kode ini ke user secara manual: ' . $code . ' (berlaku 30 menit, dipakai lewat menu "Lupa Password").']);
            }
          } else {
            $json_output = json_encode(['ok' => true, 'message' => 'Permintaan disetujui, tapi akun user ini belum punya email terverifikasi sehingga kode tidak bisa dikirim. Gunakan menu "Reset Langsung" di Manajemen Password untuk mengatur password user ini secara manual.']);
          }
          $should_exit = true;
        }
      }
      break;

    case 'reject':
      requireAdmin();
      $requestId = intval($_POST['request_id'] ?? 0);
      $adminNote = trim($_POST['admin_note'] ?? '');

      if ($requestId <= 0) {
        $json_output = json_encode(['ok' => false, 'error' => 'ID permintaan tidak valid.']);
        $should_exit = true;
      } else {
        $stmt = $pdo->prepare("SELECT * FROM password_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
          $json_output = json_encode(['ok' => false, 'error' => 'Permintaan tidak ditemukan atau sudah diproses.']);
          $should_exit = true;
        } else {
          $stmt = $pdo->prepare("UPDATE password_requests SET status = 'rejected', admin_id = ?, admin_note = ? WHERE id = ?");
          $stmt->execute([$_SESSION['user_id'], $adminNote, $requestId]);

          // Sama seperti approve: kabari user lewat Inbox. Ini satu-satunya cara
          // user tahu permintaannya ditolak -- sebelumnya tidak ada notifikasi
          // sama sekali untuk kasus ini selain mengecek status secara manual.
          try {
              $inboxBody = 'Permintaan ganti password kamu DITOLAK.';
              if ($adminNote !== '') $inboxBody .= "\nCatatan admin: " . $adminNote;
              $insMsg = $pdo->prepare('INSERT INTO messages (room_id, from_user_id, to_user_id, body) VALUES (NULL, ?, ?, ?)');
              $insMsg->execute([(int)$_SESSION['user_id'], (int)$request['user_id'], $inboxBody]);
          } catch (PDOException $eMsg) { /* notifikasi inbox gagal tidak menggagalkan reject */ }

          $json_output = json_encode(['ok' => true, 'message' => 'Permintaan ditolak.']);
          $should_exit = true;
        }
      }
      break;

    case 'my_requests':
      requireAuth();
      $userId = $_SESSION['user_id'];

      $stmt = $pdo->prepare("
        SELECT pr.*,
          CASE
            WHEN pr.status = 'pending' THEN 'Menunggu'
            WHEN pr.status = 'approved' THEN 'Disetujui'
            WHEN pr.status = 'rejected' THEN 'Ditolak'
          END as status_label,
          a.username as admin_name
        FROM password_requests pr
        LEFT JOIN users a ON pr.admin_id = a.id
        WHERE pr.user_id = ?
        ORDER BY pr.created_at DESC
        LIMIT 10
      ");
      $stmt->execute([$userId]);
      $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $json_output = json_encode(['ok' => true, 'requests' => $requests]);
      $should_exit = true;
      break;

    case 'cancel':
      requireAuth();
      $userId = $_SESSION['user_id'];
      $requestId = intval($_POST['request_id'] ?? 0);

      if ($requestId <= 0) {
        $json_output = json_encode(['ok' => false, 'error' => 'ID permintaan tidak valid.']);
        $should_exit = true;
      } else {
        $stmt = $pdo->prepare("SELECT id FROM password_requests WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$requestId, $userId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
          $json_output = json_encode(['ok' => false, 'error' => 'Permintaan tidak ditemukan atau sudah diproses.']);
          $should_exit = true;
        } else {
          $stmt = $pdo->prepare("DELETE FROM password_requests WHERE id = ? AND user_id = ? AND status = 'pending'");
          $stmt->execute([$requestId, $userId]);
          $json_output = json_encode(['ok' => true, 'message' => 'Permintaan berhasil dibatalkan.']);
          $should_exit = true;
        }
      }
      break;

    case 'edit':
      requireAuth();
      $userId = $_SESSION['user_id'];
      $requestId = intval($_POST['request_id'] ?? 0);
      $newReason = trim($_POST['reason'] ?? '');

      if ($requestId <= 0 || empty($newReason)) {
        $json_output = json_encode(['ok' => false, 'error' => 'Data tidak valid.']);
        $should_exit = true;
      } else {
        $stmt = $pdo->prepare("SELECT id FROM password_requests WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$requestId, $userId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
          $json_output = json_encode(['ok' => false, 'error' => 'Permintaan tidak ditemukan atau sudah diproses.']);
          $should_exit = true;
        } else {
          $stmt = $pdo->prepare("UPDATE password_requests SET reason = ? WHERE id = ? AND user_id = ? AND status = 'pending'");
          $stmt->execute([$newReason, $requestId, $userId]);
          $json_output = json_encode(['ok' => true, 'message' => 'Alasan permintaan berhasil diperbarui.']);
          $should_exit = true;
        }
      }
      break;

    default:
      $json_output = json_encode(['ok' => false, 'error' => 'Action tidak valid.']);
      $should_exit = true;
  }
} catch (Exception $e) {
  error_log('Password request API error: ' . $e->getMessage());
  $json_output = json_encode(['ok' => false, 'error' => 'Server error']);
  $should_exit = true;
}

// Clean buffer and output ONLY the JSON
$buffer = ob_get_clean();
if ($buffer) {
  error_log('Unexpected output: ' . substr($buffer, 0, 200));
}

ob_start();
echo $json_output;
ob_end_flush();