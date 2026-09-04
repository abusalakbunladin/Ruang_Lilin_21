<?php
// File: api/match_history.php
// Proyek: 21: Ruang Lilin
// Fungsi: Riwayat pertandingan online per pemain -- lawan siapa, menang/kalah,
// dan berapa rating/rank points yang didapat/hilang di tiap pertandingan.
// Juga dipakai untuk menampilkan +/- poin di layar akhir pertandingan (action=match_result).
require 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ================================================================
// Riwayat pertandingan milik pemain yang sedang login (dengan paginasi)
// ================================================================
if ($action === 'list') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 15);
    if ($limit < 1 || $limit > 50) $limit = 15;
    $offset = (int)($_GET['offset'] ?? $_POST['offset'] ?? 0);
    if ($offset < 0) $offset = 0;

    $stmt = $pdo->prepare('SELECT mh.id, mh.played_at, mh.room_code, mh.winner_candles_left, mh.ranked,
        CASE WHEN mh.winner_id = :uid1 THEN 1 ELSE 0 END AS won,
        CASE WHEN mh.winner_id = :uid2 THEN mh.loser_id ELSE mh.winner_id END AS opponent_id,
        CASE WHEN mh.winner_id = :uid3 THEN uloser.username ELSE uwinner.username END AS opponent_username,
        CASE WHEN mh.winner_id = :uid4 THEN mh.winner_rating_change ELSE mh.loser_rating_change END AS my_rating_change,
        CASE WHEN mh.winner_id = :uid5 THEN mh.winner_rank_points_change ELSE mh.loser_rank_points_change END AS my_rank_points_change
        FROM match_history mh
        JOIN users uwinner ON uwinner.id = mh.winner_id
        JOIN users uloser ON uloser.id = mh.loser_id
        WHERE mh.winner_id = :uid6 OR mh.loser_id = :uid7
        ORDER BY mh.played_at DESC, mh.id DESC
        LIMIT ' . $limit . ' OFFSET ' . $offset);
    $stmt->execute(['uid1' => $uid, 'uid2' => $uid, 'uid3' => $uid, 'uid4' => $uid, 'uid5' => $uid, 'uid6' => $uid, 'uid7' => $uid]);
    $rows = $stmt->fetchAll();
    // [PERINGKAT VS LATIHAN] V.0.9.1.0 -- `ranked` (dari kolom match_history.ranked,
    // default 1 utk baris lama) dipakai klien (JSS_2/JSS_HISTORY.js) utk menandai
    // tiap baris riwayat sebagai "Peringkat" (publik/Cari Lawan) atau "Latihan"
    // (ruangan privat) -- lihat api/game_action.php utk logika penentuannya.
    foreach ($rows as &$r) {
        $r['won'] = (bool)$r['won'];
        $r['opponent_id'] = (int)$r['opponent_id'];
        $r['my_rating_change'] = $r['my_rating_change'] !== null ? (int)$r['my_rating_change'] : null;
        $r['my_rank_points_change'] = $r['my_rank_points_change'] !== null ? (int)$r['my_rank_points_change'] : null;
        $r['winner_candles_left'] = (int)$r['winner_candles_left'];
        $r['ranked'] = (bool)$r['ranked'];
    }
    jsonResponse(['ok' => true, 'matches' => $rows, 'has_more' => count($rows) === $limit]);
}

// ================================================================
// Hasil satu pertandingan spesifik berdasarkan kode ruangan -- dipakai
// klien untuk menampilkan banner "+X Rating / -Y Rank Points" tepat saat
// layar akhir pertandingan online muncul. Selalu ambil baris TERBARU untuk
// kode ruangan itu (karena fitur "Main Lagi" bisa memakai kode yang sama
// berkali-kali untuk pertandingan rematch berikutnya).
// ================================================================
if ($action === 'match_result') {
    requireAuth();
    $uid = (int)$_SESSION['user_id'];
    $roomCode = trim(strtoupper($_GET['room_code'] ?? $_POST['room_code'] ?? ''));
    if ($roomCode === '') jsonResponse(['ok' => false, 'error' => 'Kode ruangan diperlukan.']);

    $stmt = $pdo->prepare('SELECT mh.id, mh.played_at, mh.room_code, mh.winner_candles_left, mh.ranked,
        CASE WHEN mh.winner_id = :uid1 THEN 1 ELSE 0 END AS won,
        CASE WHEN mh.winner_id = :uid2 THEN mh.loser_id ELSE mh.winner_id END AS opponent_id,
        CASE WHEN mh.winner_id = :uid3 THEN uloser.username ELSE uwinner.username END AS opponent_username,
        CASE WHEN mh.winner_id = :uid4 THEN mh.winner_rating_change ELSE mh.loser_rating_change END AS my_rating_change,
        CASE WHEN mh.winner_id = :uid5 THEN mh.winner_rank_points_change ELSE mh.loser_rank_points_change END AS my_rank_points_change
        FROM match_history mh
        JOIN users uwinner ON uwinner.id = mh.winner_id
        JOIN users uloser ON uloser.id = mh.loser_id
        WHERE mh.room_code = :room AND (mh.winner_id = :uid6 OR mh.loser_id = :uid7)
        ORDER BY mh.id DESC LIMIT 1');
    $stmt->execute(['uid1' => $uid, 'uid2' => $uid, 'uid3' => $uid, 'uid4' => $uid, 'uid5' => $uid, 'room' => $roomCode, 'uid6' => $uid, 'uid7' => $uid]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['ok' => false, 'error' => 'Belum ada hasil pertandingan untuk ruangan ini.']);

    $row['won'] = (bool)$row['won'];
    $row['opponent_id'] = (int)$row['opponent_id'];
    $row['my_rating_change'] = $row['my_rating_change'] !== null ? (int)$row['my_rating_change'] : null;
    $row['my_rank_points_change'] = $row['my_rank_points_change'] !== null ? (int)$row['my_rank_points_change'] : null;
    $row['winner_candles_left'] = (int)$row['winner_candles_left'];
    $row['ranked'] = (bool)$row['ranked'];
    jsonResponse(['ok' => true, 'match' => $row]);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
