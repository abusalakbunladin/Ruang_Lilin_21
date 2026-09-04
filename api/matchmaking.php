<?php
// File: api/matchmaking.php
// Proyek: 21: Ruang Lilin
// Fungsi: Sistem pencocokan lawan otomatis ("Cari Lawan") + jelajah Lobi
// Publik. Sengaja dibangun DI ATAS tabel `rooms` yang sama dipakai sistem
// kode privat (room.php), bukan tabel antrian terpisah -- supaya satu kolam
// yang sama menyatukan dua pintu masuk:
//   1) Pemain yang menekan "Cari Lawan" akan otomatis dicocokkan ke lobi
//      publik siapa pun yang sedang terbuka (baik yang dibuat manual lewat
//      "Lobi Publik" maupun lewat "Cari Lawan" pemain lain sebelumnya).
//   2) Pemain yang menjelajah "Lobi Publik" bisa menemukan & bergabung ke
//      lobi siapa pun yang sedang dibuat, termasuk yang dibuat otomatis oleh
//      pemain lain yang menekan "Cari Lawan" (dan belum ada yang cocok).
// Jadi "mencari lawan" dan "memasang lobi publik" adalah dua cara masuk ke
// satu sistem pencocokan yang sama, seperti yang diminta.
//
// Ruangan kode privat (room.php, visibility='private') SAMA SEKALI tidak
// disentuh oleh file ini -- query di sini selalu memfilter visibility='public'.
require 'db.php';

// Duplikat kecil dari generateCode() di room.php. Sengaja TIDAK require
// room.php dari sini (dan sebaliknya) -- pola di proyek ini adalah tiap file
// endpoint di api/ berdiri sendiri, karena tiap file punya dispatcher action
// sendiri yang berakhir dengan jsonResponse()+exit di baris terakhir; kalau
// saling require, dispatcher file yang di-require akan ikut jalan duluan
// dengan $_POST['action'] milik request ini dan salah merespons.
function mmGenerateCode($pdo) {
    for ($i = 0; $i < 10; $i++) {
        $code = substr(strtoupper(base_convert(bin2hex(random_bytes(4)), 16, 36)), 0, 6);
        $stmt = $pdo->prepare('SELECT id FROM rooms WHERE room_code = ?');
        $stmt->execute([$code]);
        if (!$stmt->fetch()) return $code;
    }
    return null;
}

// Buang lobi publik yang ditinggal begitu saja (host menutup tab/koneksi
// putus sebelum ada yang gabung) supaya daftar Lobi Publik & pencarian cepat
// tidak tersumbat entri mati selamanya. HANYA menyentuh ruangan PUBLIK yang
// masih 'waiting' tanpa tamu -- tidak pernah menyentuh ruangan kode privat,
// jadi alur "Buat/Gabung Ruangan" yang sudah ada tidak berubah sama sekali.
function mmCleanupStale($pdo) {
    try {
        $pdo->exec("DELETE FROM rooms WHERE visibility = 'public' AND status = 'waiting' AND guest_id IS NULL AND last_updated < (NOW() - INTERVAL 5 MINUTE)");
    } catch (PDOException $e) {
        // Kegagalan cleanup tidak boleh menggagalkan request utama.
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================
// CARI LAWAN (quick match): cocokkan ke lobi publik yang sudah ada, atau
// kalau tidak ada, jadi host lobi publik baru supaya bisa ditemukan pemain
// lain (baik lewat Cari Lawan mereka, maupun lewat jelajah Lobi Publik).
// ============================================================
if ($action === 'find') {
    requireAuth();
    $uid = $_SESSION['user_id'];
    $myRating = (int)($_SESSION['rating'] ?? 1000);
    mmCleanupStale($pdo);

    // 0) Aman dipanggil berulang: kalau ternyata sudah punya ruangan aktif
    // (mis. reload halaman waktu masih menunggu, atau koneksi sempat putus
    // tepat setelah cocok), kembalikan ruangan itu lagi daripada membuat
    // ruangan kedua yang menumpuk.
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE (host_id = ? OR guest_id = ?) AND status IN ('waiting','playing') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$uid, $uid]);
    $existing = $stmt->fetch();
    if ($existing) {
        jsonResponse([
            'ok' => true,
            'matched' => $existing['status'] === 'playing',
            'room_id' => (int)$existing['id'],
            'room_code' => $existing['room_code'],
            'status' => $existing['status'],
            'is_host' => $existing['host_id'] == $uid,
        ]);
    }

    $pdo->beginTransaction();
    try {
        // 1) Cari lobi publik yang masih menunggu tamu. Diurutkan dari yang
        // ratingnya paling dekat (biar pertandingan lebih seimbang), lalu
        // yang paling lama menunggu (fairness -- yang sudah lama nunggu
        // duluan yang kebagian).
        $stmt = $pdo->prepare("SELECT r.* FROM rooms r
            JOIN users u ON u.id = r.host_id
            WHERE r.status = 'waiting' AND r.guest_id IS NULL AND r.visibility = 'public'
              AND r.host_id != ? AND u.banned = 0
            ORDER BY ABS(u.rating - ?) ASC, r.last_updated ASC
            LIMIT 1 FOR UPDATE");
        $stmt->execute([$uid, $myRating]);
        $room = $stmt->fetch();

        if ($room && $room['status'] === 'waiting' && empty($room['guest_id'])) {
            $stmt = $pdo->prepare('UPDATE rooms SET guest_id = ?, status = "playing", last_updated = NOW() WHERE id = ?');
            $stmt->execute([$uid, $room['id']]);
            $pdo->commit();
            jsonResponse([
                'ok' => true,
                'matched' => true,
                'room_id' => (int)$room['id'],
                'room_code' => $room['room_code'],
                'status' => 'playing',
                'is_host' => false,
            ]);
        }

        // 2) Tidak ada lobi yang bisa langsung digabung -> jadi host lobi
        // publik baru (tanpa nama custom) supaya pencari lawan lain, atau
        // penjelajah Lobi Publik, bisa menemukanku.
        $code = mmGenerateCode($pdo);
        if (!$code) {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'error' => 'Tidak bisa membuat kode ruangan. Coba lagi.']);
        }
        $stmt = $pdo->prepare('INSERT INTO rooms (room_code, host_id, status, visibility, lobby_name, game_state, last_updated) VALUES (?, ?, "waiting", "public", NULL, NULL, NOW())');
        $stmt->execute([$code, $uid]);
        $roomId = (int)$pdo->lastInsertId();
        $pdo->commit();
        jsonResponse([
            'ok' => true,
            'matched' => false,
            'room_id' => $roomId,
            'room_code' => $code,
            'status' => 'waiting',
            'is_host' => true,
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Gagal mencari lawan. Coba lagi.']);
    }
}

// ============================================================
// LOBI PUBLIK: daftar lobi yang sedang terbuka & menunggu (utk dijelajah
// manual). Dipakai jelas berbeda dari 'find' -- ini tidak mengubah apa pun,
// cuma membaca daftar.
// ============================================================
if ($action === 'list') {
    requireAuth();
    $uid = $_SESSION['user_id'];
    mmCleanupStale($pdo);
    $stmt = $pdo->prepare("SELECT r.id, r.room_code, r.lobby_name, r.last_updated, u.username AS host_name, u.rating AS host_rating
        FROM rooms r JOIN users u ON u.id = r.host_id
        WHERE r.status = 'waiting' AND r.guest_id IS NULL AND r.visibility = 'public'
          AND r.host_id != ? AND u.banned = 0
        ORDER BY r.last_updated ASC
        LIMIT 30");
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll();
    $lobbies = [];
    foreach ($rows as $row) {
        $lobbies[] = [
            'room_id' => (int)$row['id'],
            'lobby_name' => $row['lobby_name'],
            'host_name' => $row['host_name'],
            'host_rank' => rankTitleForRating($row['host_rating']),
            'waiting_seconds' => max(0, time() - strtotime($row['last_updated'])),
        ];
    }
    jsonResponse(['ok' => true, 'lobbies' => $lobbies]);
}

// ============================================================
// Gabung ke lobi publik tertentu (dipilih dari daftar 'list' di atas), by id
// -- bukan by kode, karena lobi publik ditemukan lewat jelajah, bukan lewat
// kode rahasia.
// ============================================================
if ($action === 'join_public') {
    requireAuth();
    $roomId = (int)($_POST['room_id'] ?? 0);
    $uid = $_SESSION['user_id'];
    if (!$roomId) jsonResponse(['ok' => false, 'error' => 'Lobi tidak valid.']);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ? FOR UPDATE');
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
        if (!$room || $room['visibility'] !== 'public') {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'error' => 'Lobi tidak ditemukan.']);
        }
        if ($room['status'] !== 'waiting' || !empty($room['guest_id'])) {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'error' => 'Lobi ini sudah penuh atau sudah dimulai.']);
        }
        if ($room['host_id'] == $uid) {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'error' => 'Kamu adalah pemilik lobi ini.']);
        }
        $stmt = $pdo->prepare('UPDATE rooms SET guest_id = ?, status = "playing", last_updated = NOW() WHERE id = ?');
        $stmt->execute([$uid, $room['id']]);
        $pdo->commit();
        jsonResponse([
            'ok' => true,
            'matched' => true,
            'room_id' => (int)$room['id'],
            'room_code' => $room['room_code'],
            'status' => 'playing',
            'is_host' => false,
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Gagal bergabung ke lobi.']);
    }
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
