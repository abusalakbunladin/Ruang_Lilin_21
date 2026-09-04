<?php
require 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'send') {
    requireAuth();
    $from = (int)$_SESSION['user_id'];
    $roomId = (int)($_POST['room_id'] ?? 0);
    $to = (int)($_POST['to_user_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    if ($body === '') jsonResponse(['ok' => false, 'error' => 'Pesan kosong.']);
    if ($roomId <= 0 && $to <= 0) jsonResponse(['ok' => false, 'error' => 'Pilih tujuan pesan.']);
    if ($to === $from) jsonResponse(['ok' => false, 'error' => 'Tidak bisa mengirim ke diri sendiri.']);

    try {
        $stmt = $pdo->prepare('INSERT INTO messages (room_id, from_user_id, to_user_id, body) VALUES (?, ?, ?, ?)');
        $stmt->execute([$roomId > 0 ? $roomId : null, $from, $to > 0 ? $to : null, $body]);
        jsonResponse(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal mengirim pesan.']);
    }
}

if ($action === 'list') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $roomId = (int)($_GET['room_id'] ?? 0);
    $to = (int)($_GET['to_user_id'] ?? 0);
    $since = (int)($_GET['since'] ?? 0);

    try {
        if ($roomId > 0) {
            $stmt = $pdo->prepare('SELECT m.*, u.username as from_username FROM messages m JOIN users u ON u.id=m.from_user_id WHERE m.room_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 100');
            $stmt->execute([$roomId, $since]);
        } elseif ($to > 0) {
            // Membuka percakapan ini = menandai semua pesan MASUK dari teman ini sebagai terbaca.
            $mark = $pdo->prepare('UPDATE messages SET read_at = NOW() WHERE from_user_id = ? AND to_user_id = ? AND read_at IS NULL');
            $mark->execute([$to, $uid]);

            $stmt = $pdo->prepare('SELECT m.*, u.username as from_username FROM messages m JOIN users u ON u.id=m.from_user_id WHERE ((m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)) AND m.id > ? ORDER BY m.id ASC LIMIT 100');
            $stmt->execute([$uid, $to, $to, $uid, $since]);
        } else {
            // recent messages for this user (friend chat summary)
            $stmt = $pdo->prepare('SELECT m.*, u.username as from_username FROM messages m JOIN users u ON u.id=m.from_user_id WHERE (m.to_user_id = ? OR m.from_user_id = ?) AND m.room_id IS NULL AND m.id > ? ORDER BY m.id DESC LIMIT 50');
            $stmt->execute([$uid, $uid, $since]);
        }
        $messages = $stmt->fetchAll();

        // Untuk pesan tantangan bermain (berisi kode ruangan), lampirkan status ruangan
        // saat ini supaya frontend bisa menandai tantangan itu masih aktif atau sudah kadaluarsa.
        $codes = [];
        foreach ($messages as $m) {
            if (preg_match('/Kode ruanganku:\s*([A-Z0-9]{6})/i', $m['body'], $mm)) {
                $codes[] = strtoupper($mm[1]);
            }
        }
        $roomStatusByCode = [];
        if (!empty($codes)) {
            $codes = array_values(array_unique($codes));
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $rstmt = $pdo->prepare("SELECT room_code, status FROM rooms WHERE room_code IN ($placeholders)");
            $rstmt->execute($codes);
            foreach ($rstmt->fetchAll() as $r) { $roomStatusByCode[$r['room_code']] = $r['status']; }
        }
        foreach ($messages as &$m) {
            if (preg_match('/Kode ruanganku:\s*([A-Z0-9]{6})/i', $m['body'], $mm)) {
                $code = strtoupper($mm[1]);
                // 'waiting' = masih bisa digabung. Kalau kodenya tidak ditemukan sama sekali
                // di tabel rooms (sudah dibersihkan/lama), anggap juga sudah kadaluarsa.
                $m['challenge_code'] = $code;
                $m['challenge_active'] = isset($roomStatusByCode[$code]) && $roomStatusByCode[$code] === 'waiting';
            }
        }
        unset($m);

        jsonResponse(['ok' => true, 'messages' => $messages]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal memuat pesan.']);
    }
}

// ============================================================================
// Tambahan: Inbox (KHUSUS pesan dari SISTEM/ADMIN ke user -- bukan chat teman)
// ============================================================================
// Konsepnya sengaja dipisah total dari chat teman (action 'send'/'list' di atas,
// dipakai overlay-chat lewat mpOpenFriendChat). Inbox hanya menampilkan baris
// `messages` yang pengirimnya (from_user_id) adalah akun dengan role admin/
// support -- dicek lewat JOIN ke `users.role` saat baca, bukan ditebak dari isi
// pesan, supaya selalu pasti (role bisa berubah; jika akun didemote, pesan lama
// otomatis tidak lagi tampil sebagai "resmi"). Pengiriman pesan tetap lewat
// action 'send' yang sudah ada (admin login lalu kirim ke to_user_id user
// tujuan) -- tidak perlu endpoint kirim baru.

if ($action === 'inbox') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $filter = $_GET['filter'] ?? 'all'; // all | unread | read

    try {
        $sql = 'SELECT m.id, m.body, m.created_at, m.read_at, m.from_user_id,
                       u.username AS from_username, u.role AS from_role
                FROM messages m
                JOIN users u ON u.id = m.from_user_id
                WHERE m.to_user_id = ? AND m.room_id IS NULL AND u.role IN (\'admin\',\'support\')';
        if ($filter === 'unread') $sql .= ' AND m.read_at IS NULL';
        if ($filter === 'read') $sql .= ' AND m.read_at IS NOT NULL';
        $sql .= ' ORDER BY m.id DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) { $r['read'] = $r['read_at'] !== null; }
        unset($r);

        $cstmt = $pdo->prepare('SELECT COUNT(*) AS c FROM messages m JOIN users u ON u.id = m.from_user_id WHERE m.to_user_id = ? AND m.room_id IS NULL AND u.role IN (\'admin\',\'support\') AND m.read_at IS NULL');
        $cstmt->execute([$uid]);
        $unreadTotal = (int)$cstmt->fetch()['c'];

        jsonResponse(['ok' => true, 'messages' => $rows, 'unread_count' => $unreadTotal]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal memuat inbox.']);
    }
}

if ($action === 'inbox_mark_read') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['ok' => false, 'error' => 'ID pesan tidak valid.']);
    try {
        $stmt = $pdo->prepare('UPDATE messages m JOIN users u ON u.id = m.from_user_id
                SET m.read_at = NOW()
                WHERE m.id = ? AND m.to_user_id = ? AND m.read_at IS NULL AND u.role IN (\'admin\',\'support\')');
        $stmt->execute([$id, $uid]);
        jsonResponse(['ok' => true]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal menandai pesan.']);
    }
}

if ($action === 'inbox_mark_all_read') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare('UPDATE messages m JOIN users u ON u.id = m.from_user_id
                SET m.read_at = NOW()
                WHERE m.to_user_id = ? AND m.read_at IS NULL AND u.role IN (\'admin\',\'support\')');
        $stmt->execute([$uid]);
        jsonResponse(['ok' => true]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal menandai semua pesan.']);
    }
}

// ============================================================================
// Tambahan: jumlah pesan belum dibaca PER TEMAN (bukan dari sistem/admin).
// Dipakai untuk bouble merah di tombol "Chat" pada daftar teman.
// ============================================================================
if ($action === 'unread_counts') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare('SELECT from_user_id, COUNT(*) AS cnt FROM messages WHERE to_user_id = ? AND room_id IS NULL AND read_at IS NULL GROUP BY from_user_id');
        $stmt->execute([$uid]);
        $map = [];
        foreach ($stmt->fetchAll() as $r) { $map[(int)$r['from_user_id']] = (int)$r['cnt']; }
        jsonResponse(['ok' => true, 'unread' => $map]);
    } catch (PDOException $e) {
        jsonResponse(['ok' => false, 'error' => 'Gagal memuat status belum dibaca.']);
    }
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
