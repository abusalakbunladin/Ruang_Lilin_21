<?php
// File: api/bug_report.php
// Proyek: 21: Ruang Lilin
// Fungsi: Sistem Laporan Bug -- pemain mengirim laporan detail (kategori,
// tingkat keparahan, deskripsi, langkah reproduksi, screenshot bukti),
// admin/support meninjau & menindaklanjuti lewat panel admin.
require 'db.php';
require 'report_common.php';

// Kategori bug yang valid (dropdown di klien HARUS sinkron dengan daftar ini).
const BUG_CATEGORIES = [
    'Gameplay / Aturan Kartu',
    'Kartu Spesial / Trump',
    'Multiplayer / Koneksi',
    'Tampilan / UI Rusak',
    'Audio / Musik',
    'Akun / Login / Password',
    'Papan Peringkat / Rating',
    'Mode Kampanye',
    'Lainnya',
];
const BUG_SEVERITIES = ['rendah', 'sedang', 'tinggi', 'kritis'];
const BUG_STATUSES = ['baru', 'dilihat', 'diproses', 'selesai', 'ditolak'];

function bugStatusLabel($s) {
    $map = ['baru' => 'Baru', 'dilihat' => 'Dilihat', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'];
    return $map[$s] ?? $s;
}
function bugSeverityLabel($s) {
    $map = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi', 'kritis' => 'Kritis'];
    return $map[$s] ?? $s;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ================================================================
// PLAYER: kirim laporan bug baru
// ================================================================
if ($action === 'create') {
    requireAuth();
    $userId = (int)$_SESSION['user_id'];
    requireNotBannedForReport($pdo, $userId);

    $category = trim($_POST['category'] ?? '');
    $severity = trim($_POST['severity'] ?? 'sedang');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $steps = trim($_POST['steps'] ?? '');
    $deviceInfo = trim($_POST['device_info'] ?? '');
    $gameContext = trim($_POST['game_context'] ?? '');
    $screenshotsRaw = $_POST['screenshots'] ?? null;

    if (!in_array($category, BUG_CATEGORIES, true)) {
        jsonResponse(['ok' => false, 'error' => 'Pilih kategori bug dari daftar yang tersedia.']);
    }
    if (!in_array($severity, BUG_SEVERITIES, true)) $severity = 'sedang';
    if (strlen($title) < 5 || strlen($title) > 150) {
        jsonResponse(['ok' => false, 'error' => 'Judul singkat wajib diisi (5-150 karakter).']);
    }
    if (strlen($description) < 15) {
        jsonResponse(['ok' => false, 'error' => 'Jelaskan bug-nya lebih detail (minimal 15 karakter).']);
    }
    if (strlen($description) > 4000) $description = substr($description, 0, 4000);
    if (strlen($steps) > 2000) $steps = substr($steps, 0, 2000);
    if (strlen($deviceInfo) > 255) $deviceInfo = substr($deviceInfo, 0, 255);
    if (strlen($gameContext) > 255) $gameContext = substr($gameContext, 0, 255);

    try {
        enforceReportRateLimit($pdo, $userId, 'bug_reports', 15, 20);
    } catch (Exception $e) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }

    $stmtUser = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $stmtUser->execute([$userId]);
    $u = $stmtUser->fetch();
    if (!$u) jsonResponse(['ok' => false, 'error' => 'Sesi tidak valid, silakan login ulang.']);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO bug_reports (user_id, username, category, severity, title, description, steps, device_info, game_context, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'baru\')');
        $stmt->execute([$userId, $u['username'], $category, $severity, $title, $description, $steps ?: null, $deviceInfo ?: null, $gameContext ?: null]);
        $reportId = (int)$pdo->lastInsertId();
        saveReportAttachments($pdo, 'bug', $reportId, $screenshotsRaw);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => $e->getMessage() ?: 'Gagal menyimpan laporan.']);
    }

    jsonResponse(['ok' => true, 'message' => 'Laporan bug berhasil dikirim. Terima kasih sudah membantu memperbaiki 21: Ruang Lilin!', 'report_id' => $reportId]);
}

// ================================================================
// PLAYER: lihat riwayat laporan bug miliknya sendiri
// ================================================================
if ($action === 'my_reports') {
    requireAuth();
    $userId = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT br.id, br.category, br.severity, br.title, br.description, br.status, br.admin_note, br.created_at, br.updated_at,
        (SELECT COUNT(*) FROM report_attachments WHERE report_type = "bug" AND report_id = br.id) AS attachment_count
        FROM bug_reports br WHERE br.user_id = ? ORDER BY br.id DESC LIMIT 100');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['status_label'] = bugStatusLabel($r['status']);
        $r['severity_label'] = bugSeverityLabel($r['severity']);
    }
    jsonResponse(['ok' => true, 'reports' => $rows]);
}

// ================================================================
// ADMIN/SUPPORT: daftar laporan bug (filter status opsional)
// ================================================================
if ($action === 'list') {
    requireAdmin();
    $status = trim($_GET['status'] ?? $_POST['status'] ?? 'baru');
    if ($status === 'all' || $status === '') {
        $stmt = $pdo->prepare('SELECT br.*, (SELECT COUNT(*) FROM report_attachments WHERE report_type = "bug" AND report_id = br.id) AS attachment_count
            FROM bug_reports br ORDER BY FIELD(br.status,"baru","dilihat","diproses","ditolak","selesai"), br.id DESC LIMIT 200');
        $stmt->execute();
    } else {
        if (!in_array($status, BUG_STATUSES, true)) $status = 'baru';
        $stmt = $pdo->prepare('SELECT br.*, (SELECT COUNT(*) FROM report_attachments WHERE report_type = "bug" AND report_id = br.id) AS attachment_count
            FROM bug_reports br WHERE br.status = ? ORDER BY br.id DESC LIMIT 200');
        $stmt->execute([$status]);
    }
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['status_label'] = bugStatusLabel($r['status']);
        $r['severity_label'] = bugSeverityLabel($r['severity']);
    }
    $countStmt = $pdo->query("SELECT COUNT(*) AS c FROM bug_reports WHERE status = 'baru'");
    $newCount = (int)($countStmt->fetch()['c'] ?? 0);
    jsonResponse(['ok' => true, 'reports' => $rows, 'new_count' => $newCount]);
}

// ================================================================
// ADMIN/SUPPORT atau PEMILIK LAPORAN: detail lengkap + screenshot
// ================================================================
if ($action === 'detail') {
    requireAuth();
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['ok' => false, 'error' => 'ID laporan tidak valid.']);
    $stmt = $pdo->prepare('SELECT * FROM bug_reports WHERE id = ?');
    $stmt->execute([$id]);
    $report = $stmt->fetch();
    if (!$report) jsonResponse(['ok' => false, 'error' => 'Laporan tidak ditemukan.']);

    $isOwner = ((int)$report['user_id'] === (int)$_SESSION['user_id']);
    $isStaff = !empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'support'], true);
    if (!$isStaff) {
        // Cek role dari DB kalau belum ada di session (mirror logic requireAdmin()).
        $stmtRole = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmtRole->execute([$_SESSION['user_id']]);
        $roleRow = $stmtRole->fetch();
        $isStaff = $roleRow && in_array($roleRow['role'], ['admin', 'support'], true);
    }
    if (!$isOwner && !$isStaff) {
        http_response_code(403);
        jsonResponse(['ok' => false, 'error' => 'Kamu tidak berhak melihat laporan ini.']);
    }

    $report['status_label'] = bugStatusLabel($report['status']);
    $report['severity_label'] = bugSeverityLabel($report['severity']);
    $report['screenshots'] = getReportAttachments($pdo, 'bug', $id);
    jsonResponse(['ok' => true, 'report' => $report]);
}

// ================================================================
// ADMIN/SUPPORT: update status + catatan admin
// ================================================================
if ($action === 'update_status') {
    requireAdmin();
    $id = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $adminNote = trim($_POST['admin_note'] ?? '');
    if ($id <= 0) jsonResponse(['ok' => false, 'error' => 'ID laporan tidak valid.']);
    if (!in_array($status, BUG_STATUSES, true)) jsonResponse(['ok' => false, 'error' => 'Status tidak valid.']);
    if (strlen($adminNote) > 2000) $adminNote = substr($adminNote, 0, 2000);

    $stmt = $pdo->prepare('UPDATE bug_reports SET status = ?, admin_note = ?, admin_id = ? WHERE id = ?');
    $stmt->execute([$status, $adminNote ?: null, $_SESSION['user_id'], $id]);
    if ($stmt->rowCount() === 0) {
        // Bisa saja rowCount 0 karena nilai sama persis -- cek keberadaan baris.
        $check = $pdo->prepare('SELECT id FROM bug_reports WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetch()) jsonResponse(['ok' => false, 'error' => 'Laporan tidak ditemukan.']);
    }
    jsonResponse(['ok' => true, 'message' => 'Status laporan bug diperbarui.']);
}

// ================================================================
// ADMIN: hapus laporan (dan screenshot-nya) -- untuk kebersihan data
// ================================================================
if ($action === 'delete') {
    requireAdmin();
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        jsonResponse(['ok' => false, 'error' => 'Hanya admin penuh yang boleh menghapus laporan.']);
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['ok' => false, 'error' => 'ID laporan tidak valid.']);
    deleteReportAttachments($pdo, 'bug', $id);
    $pdo->prepare('DELETE FROM bug_reports WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true, 'message' => 'Laporan bug dihapus.']);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
