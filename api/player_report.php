<?php
// File: api/player_report.php
// Proyek: 21: Ruang Lilin
// Fungsi: Sistem Laporan Pemain -- pemain melaporkan pemain lain yang
// dicurigai curang/melanggar (kategori, deskripsi kejadian, screenshot
// bukti, konteks ruangan), admin/support meninjau & mengambil tindakan
// (peringatan/ban), terintegrasi dengan sistem ban yang sudah ada (ban.php).
require 'db.php';
require 'report_common.php';

const PLAYER_REPORT_CATEGORIES = [
    'Cheat / Hack (program luar)',
    'Eksploitasi Bug untuk Menang',
    'Toxic / Ujaran Kebencian',
    'Tidak Sportif (AFK sengaja / Ragequit)',
    'Nama / Profil Tidak Pantas',
    'Penipuan (Scam) Sesama Pemain',
    'Lainnya',
];
const PLAYER_REPORT_STATUSES = ['baru', 'ditinjau', 'ditindak', 'ditolak'];
const PLAYER_REPORT_ACTIONS = ['Tidak ada tindakan', 'Peringatan', 'Ban Sementara', 'Ban Permanen'];

function reportStatusLabel($s) {
    $map = ['baru' => 'Baru', 'ditinjau' => 'Ditinjau', 'ditindak' => 'Ditindak', 'ditolak' => 'Ditolak'];
    return $map[$s] ?? $s;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ================================================================
// PLAYER: laporkan pemain lain
// ================================================================
if ($action === 'create') {
    requireAuth();
    $reporterId = (int)$_SESSION['user_id'];
    requireNotBannedForReport($pdo, $reporterId);

    $reportedUsername = trim($_POST['reported_username'] ?? '');
    $roomCode = trim($_POST['room_code'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $gameContext = trim($_POST['game_context'] ?? '');
    $screenshotsRaw = $_POST['screenshots'] ?? null;

    if ($reportedUsername === '') {
        jsonResponse(['ok' => false, 'error' => 'Masukkan username pemain yang ingin dilaporkan.']);
    }
    if (!in_array($category, PLAYER_REPORT_CATEGORIES, true)) {
        jsonResponse(['ok' => false, 'error' => 'Pilih kategori laporan dari daftar yang tersedia.']);
    }
    if (strlen($description) < 20) {
        jsonResponse(['ok' => false, 'error' => 'Ceritakan kejadiannya lebih detail (minimal 20 karakter) -- kapan, di ronde/ruangan mana, apa yang terjadi.']);
    }
    if (strlen($description) > 4000) $description = substr($description, 0, 4000);
    if (strlen($gameContext) > 255) $gameContext = substr($gameContext, 0, 255);
    if (strlen($roomCode) > 6) $roomCode = substr($roomCode, 0, 6);

    $stmtReporter = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $stmtReporter->execute([$reporterId]);
    $reporter = $stmtReporter->fetch();
    if (!$reporter) jsonResponse(['ok' => false, 'error' => 'Sesi tidak valid, silakan login ulang.']);

    if (strcasecmp($reporter['username'], $reportedUsername) === 0) {
        jsonResponse(['ok' => false, 'error' => 'Kamu tidak bisa melaporkan akunmu sendiri.']);
    }

    // Cari user_id dari username yang dilaporkan (kalau ada di database).
    $stmtTarget = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmtTarget->execute([$reportedUsername]);
    $target = $stmtTarget->fetch();
    $reportedUserId = $target ? (int)$target['id'] : null;

    try {
        enforceReportRateLimit($pdo, $reporterId, 'player_reports', 10, 25);
    } catch (Exception $e) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO player_reports (reporter_id, reporter_username, reported_username, reported_user_id, room_code, category, description, game_context, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'baru\')');
        $stmt->execute([$reporterId, $reporter['username'], $reportedUsername, $reportedUserId, $roomCode ?: null, $category, $description, $gameContext ?: null]);
        $reportId = (int)$pdo->lastInsertId();
        saveReportAttachments($pdo, 'player', $reportId, $screenshotsRaw);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => $e->getMessage() ?: 'Gagal menyimpan laporan.']);
    }

    jsonResponse(['ok' => true, 'message' => 'Laporan pemain berhasil dikirim. Tim admin akan meninjau laporan ini.', 'report_id' => $reportId]);
}

// ================================================================
// PLAYER: riwayat laporan pemain yang pernah dia kirim
// ================================================================
if ($action === 'my_reports') {
    requireAuth();
    $reporterId = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT pr.id, pr.reported_username, pr.category, pr.description, pr.status, pr.action_taken, pr.admin_note, pr.created_at, pr.updated_at,
        (SELECT COUNT(*) FROM report_attachments WHERE report_type = "player" AND report_id = pr.id) AS attachment_count
        FROM player_reports pr WHERE pr.reporter_id = ? ORDER BY pr.id DESC LIMIT 100');
    $stmt->execute([$reporterId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) $r['status_label'] = reportStatusLabel($r['status']);
    jsonResponse(['ok' => true, 'reports' => $rows]);
}

// ================================================================
// ADMIN/SUPPORT: daftar laporan pemain (filter status opsional)
// ================================================================
if ($action === 'list') {
    requireAdmin();
    $status = trim($_GET['status'] ?? $_POST['status'] ?? 'baru');
    if ($status === 'all' || $status === '') {
        $stmt = $pdo->prepare('SELECT pr.*, (SELECT COUNT(*) FROM report_attachments WHERE report_type = "player" AND report_id = pr.id) AS attachment_count,
            (SELECT banned FROM users WHERE id = pr.reported_user_id) AS reported_currently_banned
            FROM player_reports pr ORDER BY FIELD(pr.status,"baru","ditinjau","ditolak","ditindak"), pr.id DESC LIMIT 200');
        $stmt->execute();
    } else {
        if (!in_array($status, PLAYER_REPORT_STATUSES, true)) $status = 'baru';
        $stmt = $pdo->prepare('SELECT pr.*, (SELECT COUNT(*) FROM report_attachments WHERE report_type = "player" AND report_id = pr.id) AS attachment_count,
            (SELECT banned FROM users WHERE id = pr.reported_user_id) AS reported_currently_banned
            FROM player_reports pr WHERE pr.status = ? ORDER BY pr.id DESC LIMIT 200');
        $stmt->execute([$status]);
    }
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) $r['status_label'] = reportStatusLabel($r['status']);

    // Ringkasan berapa kali tiap "reported_username" sudah dilaporkan (total, semua status) --
    // membantu admin melihat pola pemain yang berulang kali dilaporkan.
    foreach ($rows as &$r) {
        $cStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM player_reports WHERE reported_username = ?');
        $cStmt->execute([$r['reported_username']]);
        $r['total_reports_on_user'] = (int)($cStmt->fetch()['c'] ?? 1);
    }

    $countStmt = $pdo->query("SELECT COUNT(*) AS c FROM player_reports WHERE status = 'baru'");
    $newCount = (int)($countStmt->fetch()['c'] ?? 0);
    jsonResponse(['ok' => true, 'reports' => $rows, 'new_count' => $newCount]);
}

// ================================================================
// ADMIN/SUPPORT atau PELAPOR: detail lengkap + screenshot
// ================================================================
if ($action === 'detail') {
    requireAuth();
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['ok' => false, 'error' => 'ID laporan tidak valid.']);
    $stmt = $pdo->prepare('SELECT * FROM player_reports WHERE id = ?');
    $stmt->execute([$id]);
    $report = $stmt->fetch();
    if (!$report) jsonResponse(['ok' => false, 'error' => 'Laporan tidak ditemukan.']);

    $isOwner = ((int)$report['reporter_id'] === (int)$_SESSION['user_id']);
    $stmtRole = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmtRole->execute([$_SESSION['user_id']]);
    $roleRow = $stmtRole->fetch();
    $isStaff = $roleRow && in_array($roleRow['role'], ['admin', 'support'], true);
    if (!$isOwner && !$isStaff) {
        http_response_code(403);
        jsonResponse(['ok' => false, 'error' => 'Kamu tidak berhak melihat laporan ini.']);
    }

    $report['status_label'] = reportStatusLabel($report['status']);
    $report['screenshots'] = getReportAttachments($pdo, 'player', $id);
    jsonResponse(['ok' => true, 'report' => $report]);
}

// ================================================================
// ADMIN/SUPPORT: update status + tindakan + catatan admin
// ================================================================
if ($action === 'update_status') {
    requireAdmin();
    $id = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $actionTaken = trim($_POST['action_taken'] ?? '');
    $adminNote = trim($_POST['admin_note'] ?? '');
    if ($id <= 0) jsonResponse(['ok' => false, 'error' => 'ID laporan tidak valid.']);
    if (!in_array($status, PLAYER_REPORT_STATUSES, true)) jsonResponse(['ok' => false, 'error' => 'Status tidak valid.']);
    if ($actionTaken !== '' && !in_array($actionTaken, PLAYER_REPORT_ACTIONS, true)) {
        jsonResponse(['ok' => false, 'error' => 'Tindakan tidak valid.']);
    }
    if (strlen($adminNote) > 2000) $adminNote = substr($adminNote, 0, 2000);

    $stmt = $pdo->prepare('UPDATE player_reports SET status = ?, action_taken = ?, admin_note = ?, admin_id = ? WHERE id = ?');
    $stmt->execute([$status, $actionTaken ?: null, $adminNote ?: null, $_SESSION['user_id'], $id]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM player_reports WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) jsonResponse(['ok' => false, 'error' => 'Laporan tidak ditemukan.']);
    }
    jsonResponse(['ok' => true, 'message' => 'Status laporan pemain diperbarui.']);
}

// ================================================================
// ADMIN: hapus laporan (dan screenshot-nya)
// ================================================================
if ($action === 'delete') {
    requireAdmin();
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        jsonResponse(['ok' => false, 'error' => 'Hanya admin penuh yang boleh menghapus laporan.']);
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['ok' => false, 'error' => 'ID laporan tidak valid.']);
    deleteReportAttachments($pdo, 'player', $id);
    $pdo->prepare('DELETE FROM player_reports WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true, 'message' => 'Laporan pemain dihapus.']);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
