<?php
// File: api/report_common.php
// Proyek: 21: Ruang Lilin
// Fungsi: Helper bersama untuk sistem Laporan Bug & Laporan Pemain --
// validasi/penyimpanan screenshot, rate limiting anti-spam, dan cek banned.
// require 'db.php'; HARUS sudah dipanggil sebelum file ini di-include.

// Batas ukuran per screenshot (setelah di-encode base64) supaya tidak
// membebani database gratis InfinityFree. ~900KB base64 =~ 650KB gambar asli.
// Klien (JSS_REPORTS.js) sudah mengecilkan gambar sebelum kirim, ini cuma
// jaring pengaman kedua di server.
define('REPORT_MAX_SCREENSHOTS', 3);
define('REPORT_MAX_B64_LEN', 900000);

/**
 * Decode & validasi JSON array of data-URI images (dikirim dari klien),
 * lalu simpan tiap gambar valid ke tabel report_attachments.
 * Mengembalikan jumlah gambar yang berhasil disimpan.
 * Melempar Exception(string) kalau ada gambar yang gagal validasi (format/ukuran).
 */
function saveReportAttachments($pdo, $reportType, $reportId, $screenshotsRaw) {
    if ($screenshotsRaw === null || $screenshotsRaw === '') return 0;
    $list = json_decode($screenshotsRaw, true);
    if (!is_array($list) || empty($list)) return 0;
    if (count($list) > REPORT_MAX_SCREENSHOTS) {
        throw new Exception('Maksimal ' . REPORT_MAX_SCREENSHOTS . ' screenshot per laporan.');
    }
    $saved = 0;
    $stmt = $pdo->prepare('INSERT INTO report_attachments (report_type, report_id, image_data, mime_type) VALUES (?, ?, ?, ?)');
    foreach ($list as $dataUri) {
        if (!is_string($dataUri) || $dataUri === '') continue;
        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+\/=]+)$/', $dataUri, $m)) {
            throw new Exception('Format screenshot tidak valid. Gunakan JPG, PNG, atau WEBP.');
        }
        $mime = 'image/' . ($m[1] === 'jpg' ? 'jpeg' : $m[1]);
        $b64 = $m[2];
        if (strlen($b64) > REPORT_MAX_B64_LEN) {
            throw new Exception('Ukuran salah satu screenshot terlalu besar. Coba kompres/perkecil lagi.');
        }
        $stmt->execute([$reportType, $reportId, $b64, $mime]);
        $saved++;
    }
    return $saved;
}

/** Ambil semua lampiran screenshot milik satu laporan, sebagai data-URI siap pakai di <img src>. */
function getReportAttachments($pdo, $reportType, $reportId) {
    $stmt = $pdo->prepare('SELECT id, image_data, mime_type, created_at FROM report_attachments WHERE report_type = ? AND report_id = ? ORDER BY id ASC');
    $stmt->execute([$reportType, $reportId]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)$r['id'],
            'data_uri' => 'data:' . $r['mime_type'] . ';base64,' . $r['image_data'],
        ];
    }
    return $out;
}

/** Hapus semua lampiran screenshot milik satu laporan (dipakai saat laporan dihapus admin). */
function deleteReportAttachments($pdo, $reportType, $reportId) {
    $stmt = $pdo->prepare('DELETE FROM report_attachments WHERE report_type = ? AND report_id = ?');
    $stmt->execute([$reportType, $reportId]);
}

/**
 * Cegah spam: batasi jumlah laporan per hari + jeda minimum antar laporan.
 * Melempar Exception(string pesan ramah) kalau melanggar batas.
 */
function enforceReportRateLimit($pdo, $userId, $tableName, $maxPerDay = 15, $cooldownSeconds = 25) {
    // bug_reports pakai kolom user_id, player_reports pakai kolom reporter_id.
    $idColumn = ($tableName === 'player_reports') ? 'reporter_id' : 'user_id';
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c, MAX(created_at) AS last_at FROM `$tableName` WHERE `$idColumn` = ? AND created_at > (NOW() - INTERVAL 1 DAY)");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $count = (int)($row['c'] ?? 0);
    if ($count >= $maxPerDay) {
        throw new Exception('Kamu sudah mengirim terlalu banyak laporan hari ini. Coba lagi besok, atau tunggu laporan sebelumnya ditinjau admin.');
    }
    if (!empty($row['last_at'])) {
        $lastTs = strtotime($row['last_at']);
        if ($lastTs !== false && (time() - $lastTs) < $cooldownSeconds) {
            $wait = $cooldownSeconds - (time() - $lastTs);
            throw new Exception('Mohon tunggu ' . max(1, $wait) . ' detik lagi sebelum mengirim laporan baru.');
        }
    }
}

/** Pastikan akun tidak sedang dibanned sebelum boleh mengirim laporan baru. */
function requireNotBannedForReport($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT banned FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if ($u && (int)$u['banned'] === 1) {
        jsonResponse(['ok' => false, 'error' => 'Akun yang sedang dibanned tidak dapat mengirim laporan.']);
    }
}
