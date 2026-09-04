<?php
// File: api/game_action.php
// Proyek: 21: Ruang Lilin
// Fungsi: Endpoint AJAX aksi game online: hit, stand, special, sync, dan continue.
require 'db.php';
require 'engine.php';

$method = $_SERVER['REQUEST_METHOD'];
requireAuth();

if ($method === 'GET') {
    // sync: return current game_state stripped for this player
    $code = trim(strtoupper($_GET['room_code'] ?? ''));
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM rooms WHERE room_code = ? FOR UPDATE');
        $stmt->execute([$code]);
        $room = $stmt->fetch();
        if (!$room) { $pdo->rollBack(); jsonResponse(['ok' => false, 'error' => 'Ruangan tidak ditemukan.']); }
        if ($room['host_id'] != $_SESSION['user_id'] && $room['guest_id'] != $_SESSION['user_id']) {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'error' => 'Bukan anggota ruangan.']);
        }
        $uid = $_SESSION['user_id'];
        $side = ($room['host_id'] == $uid) ? 'host' : 'guest';

        $rawState = $room['game_state'];
        $state = $rawState ? json_decode($rawState, true) : null;
        $stateChanged = false;
        if ($rawState && $state === null) {
            // Ada data tersimpan tapi gagal di-decode -- state korup (kemungkinan sisa
            // bug lama: kolom game_state kepotong karena melebihi batas TEXT 64KB sebelum
            // migrasi MEDIUMTEXT). Dulu ini jatuh ke cabang "!$state" di bawah dan diam-diam
            // dianggap room baru -- me-reset seluruh pertandingan & Riwayat Kejadian tanpa
            // jejak. Sekarang dilaporkan sebagai error yang jelas, bukan direset diam-diam.
            $pdo->rollBack();
            error_log('game_action.php sync: game_state room ' . $code . ' gagal di-decode, panjang data=' . strlen($rawState));
            jsonResponse(['ok' => false, 'error' => 'Data pertandingan di ruangan ini rusak dan tidak bisa dipulihkan (kemungkinan sisa bug versi lama). Silakan buat ruangan baru.']);
        }
        if (!$state || empty($state['status']) || $state['status'] === 'waiting') {
            $h = $pdo->prepare('SELECT username FROM users WHERE id = ?'); $h->execute([$room['host_id']]); $hostName = $h->fetchColumn() ?? 'Host';
            $g = $pdo->prepare('SELECT username FROM users WHERE id = ?'); $g->execute([$room['guest_id'] ?? 0]); $guestName = $g->fetchColumn() ?? 'Guest';
            $room['host_name'] = $hostName; $room['guest_name'] = $guestName;
            $state = OnlineEngine::freshState($room);
            $stateChanged = true;
        }
        if (OnlineEngine::checkTimeout($state)) {
            $stateChanged = true;
        }
        if (OnlineEngine::checkContinueTimeout($state)) {
            $stateChanged = true;
        }
        if ($stateChanged) {
            $stmt = $pdo->prepare('UPDATE rooms SET game_state = ?, last_updated = NOW() WHERE id = ?');
            $stmt->execute([json_encode($state), $room['id']]);
        }
        $pdo->commit();
        jsonResponse(['ok' => true, 'state' => OnlineEngine::stripSecrets($state, $side), 'side' => $side]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $code = trim(strtoupper($_POST['room_code'] ?? ''));
    if (!$code) jsonResponse(['ok' => false, 'error' => 'Kode ruangan diperlukan.']);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM rooms WHERE room_code = ? FOR UPDATE');
        $stmt->execute([$code]);
        $room = $stmt->fetch();
        if (!$room) { $pdo->rollBack(); jsonResponse(['ok' => false, 'error' => 'Ruangan tidak ditemukan.']); }
        $uid = $_SESSION['user_id'];
        if ($room['host_id'] != $uid && $room['guest_id'] != $uid) { $pdo->rollBack(); jsonResponse(['ok' => false, 'error' => 'Bukan anggota ruangan.']); }

        $side = ($room['host_id'] == $uid) ? 'host' : 'guest';

        if ($action === 'start' || empty($room['game_state'])) {
            $h = $pdo->prepare('SELECT username FROM users WHERE id = ?'); $h->execute([$room['host_id']]); $hostName = $h->fetchColumn() ?? 'Host';
            $g = $pdo->prepare('SELECT username FROM users WHERE id = ?'); $g->execute([$room['guest_id'] ?? 0]); $guestName = $g->fetchColumn() ?? 'Guest';
            $room['host_name'] = $hostName; $room['guest_name'] = $guestName;
            $state = OnlineEngine::freshState($room);
            $stmt = $pdo->prepare('UPDATE rooms SET game_state = ?, status = "playing" WHERE id = ?');
            $stmt->execute([json_encode($state), $room['id']]);
            $pdo->commit();
            jsonResponse(['ok' => true, 'state' => OnlineEngine::stripSecrets($state, $side), 'side' => $side]);
        }

        $state = json_decode($room['game_state'], true);
        if (!$state) {
            $pdo->rollBack();
            error_log('game_action.php action=' . $action . ': game_state room ' . $code . ' gagal di-decode, panjang data=' . strlen($room['game_state']));
            jsonResponse(['ok' => false, 'error' => 'Data pertandingan di ruangan ini rusak dan tidak bisa dipulihkan (kemungkinan sisa bug versi lama). Silakan buat ruangan baru.']);
        }

        OnlineEngine::checkTimeout($state);
        OnlineEngine::checkContinueTimeout($state);

        if ($action === 'sync') {
            $pdo->commit();
            jsonResponse(['ok' => true, 'state' => OnlineEngine::stripSecrets($state, $side), 'side' => $side]);
        }

        // Rematch: aksi khusus pasca-permainan, ditangani di sini (bukan lewat
        // OnlineEngine::action) karena state sudah 'finished' dan dispatcher utama
        // menolak aksi apa pun begitu status finished=true.
        if ($action === 'rematch') {
            if (empty($state['finished'])) {
                $pdo->rollBack();
                jsonResponse(['ok' => false, 'error' => 'Pertandingan belum selesai.']);
            }
            if (!isset($state['rematch_requested'])) {
                $state['rematch_requested'] = ['host' => false, 'guest' => false];
            }
            $state['rematch_requested'][$side] = true;

            $bothReady = !empty($state['rematch_requested']['host']) && !empty($state['rematch_requested']['guest']);
            if ($bothReady) {
                $h = $pdo->prepare('SELECT username FROM users WHERE id = ?'); $h->execute([$room['host_id']]); $hostName = $h->fetchColumn() ?? 'Host';
                $g = $pdo->prepare('SELECT username FROM users WHERE id = ?'); $g->execute([$room['guest_id'] ?? 0]); $guestName = $g->fetchColumn() ?? 'Guest';
                $room['host_name'] = $hostName; $room['guest_name'] = $guestName;

                // Bawa skor kepala-lawan-kepala (session_score) dari pertandingan sebelumnya
                // ke pertandingan baru, supaya rekapnya tetap jalan sepanjang sesi rematch.
                $carriedScore = $state['session_score'] ?? ['host' => 0, 'guest' => 0];
                $state = OnlineEngine::freshState($room);
                $state['session_score'] = $carriedScore;
            }

            $stmt = $pdo->prepare('UPDATE rooms SET game_state = ?, status = ?, last_updated = NOW() WHERE id = ?');
            $stmt->execute([json_encode($state), $bothReady ? 'playing' : $room['status'], $room['id']]);
            $pdo->commit();
            jsonResponse(['ok' => true, 'state' => OnlineEngine::stripSecrets($state, $side), 'side' => $side, 'both_ready' => $bothReady]);
        }

        $result = OnlineEngine::action($state, $side, $action, $_POST);
        if (!$result['ok']) { $pdo->rollBack(); jsonResponse($result); }

        // Auto-advance jika waktu konfirmasi lanjut habis sebelum polling berikutnya
        OnlineEngine::checkContinueTimeout($state);

        // Update DB
        $stmt = $pdo->prepare('UPDATE rooms SET game_state = ?, last_updated = NOW() WHERE id = ?');
        $stmt->execute([json_encode($state), $room['id']]);

        // If game finished, update win/loss stats and ratings
        if (!empty($state['finished']) && empty($state['stats_updated'])) {
            $state['stats_updated'] = true;
            if (!isset($state['session_score'])) {
                $state['session_score'] = ['host' => 0, 'guest' => 0];
            }
            if ($state['winner'] === 'host' || $state['winner'] === 'guest') {
                $state['session_score'][$state['winner']]++;
            }
            $winnerId = ($state['winner'] === 'host') ? $room['host_id'] : ($room['guest_id'] ?? null);
            $loserId = ($state['winner'] === 'host') ? ($room['guest_id'] ?? null) : $room['host_id'];

            // ============================================================
            // [PERINGKAT VS LATIHAN] V.0.9.1.0 -- naik rating/rank_points/wins/
            // losses HANYA dari pertandingan visibility='public' (mencakup Lobi
            // Publik yang dibuat manual MAUPUN yang dicocokkan lewat "Cari
            // Lawan" -- keduanya berbagi kolam ruangan publik yang sama, lihat
            // api/matchmaking.php). Ruangan kode PRIVAT -- baik dibiarkan
            // klasik maupun memakai Pengaturan Ruangan kustom -- selalu
            // dianggap pertandingan LATIHAN: tetap bisa dimainkan & tetap
            // tercatat di riwayat pertandingan (lihat INSERT match_history di
            // bawah), tapi tidak menyentuh statistik publik pemain sama sekali.
            // Ini otomatis menjamin permintaan "naik poin harus main publik
            // tanpa ubah peraturan, atau lewat Cari Lawan" -- karena ruangan
            // publik TIDAK PERNAH bisa punya settings kustom (dipaksa NULL di
            // api/room.php & OnlineEngine::resolveSettingsForRoom di
            // api/engine.php), jadi "publik" di sini otomatis berarti "publik
            // DAN peraturan klasik" sekaligus, tidak perlu dicek terpisah.
            // $room['visibility'] dibaca dari baris yang sudah di-SELECT ...
            // FOR UPDATE di atas fungsi ini -- bukan input dari client, jadi
            // tidak bisa dipalsukan lewat request.
            // ============================================================
            $isRanked = (($room['visibility'] ?? 'private') === 'public') && empty($room['settings']);

            // Perubahan rating/rank points per pertandingan ini -- disimpan ke match_history
            // supaya riwayat pertandingan & layar akhir bisa menunjukkan +/- poin yang akurat
            // (nilainya tidak bisa dihitung ulang belakangan karena rating terus berubah).
            // Tetap null (tidak berubah) utk pertandingan latihan (ruangan privat).
            $winnerRatingChange = null; $loserRatingChange = null;
            $winnerRankPointsChange = null; $loserRankPointsChange = null;
            if ($winnerId && $loserId && $isRanked) {
                // Get current ratings
                $stmt = $pdo->prepare('SELECT id, rating, rank_points FROM users WHERE id IN (?, ?)');
                $stmt->execute([$winnerId, $loserId]);
                $ratings = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $ratings[$row['id']] = ['rating' => (int)$row['rating'], 'rank_points' => (int)$row['rank_points']];
                }
                
                $winnerRating = $ratings[$winnerId]['rating'] ?? 1000;
                $loserRating = $ratings[$loserId]['rating'] ?? 1000;
                $winnerRankPointsBefore = $ratings[$winnerId]['rank_points'] ?? 0;
                $loserRankPointsBefore = $ratings[$loserId]['rank_points'] ?? 0;
                
                // ELO-like calculation
                $expectedWinner = 1 / (1 + pow(10, ($loserRating - $winnerRating) / 400));
                $expectedLoser = 1 / (1 + pow(10, ($winnerRating - $loserRating) / 400));
                
                $kFactor = 32;
                $newWinnerRating = round($winnerRating + $kFactor * (1 - $expectedWinner));
                $newLoserRating = round($loserRating + $kFactor * (0 - $expectedLoser));
                
                // Rank points based on performance
                $candlesLeft = $state['lives'][$state['winner']] ?? 0;
                $rankPointsGain = 10 + ($candlesLeft * 5);
                
                // Update winner (rank dihitung dari fungsi terpusat rankTitleForRating() di db.php)
                $pdo->prepare('UPDATE users SET wins = wins + 1, rating = ?, rank_points = rank_points + ?, rank = ? WHERE id = ?')
                    ->execute([$newWinnerRating, $rankPointsGain, rankTitleForRating($newWinnerRating), $winnerId]);
                
                // Update loser
                $pdo->prepare('UPDATE users SET losses = losses + 1, rating = ?, rank_points = GREATEST(0, rank_points - 5), rank = ? WHERE id = ?')
                    ->execute([$newLoserRating, rankTitleForRating($newLoserRating), $loserId]);

                // Catat perubahan aktual (bukan cuma niat) supaya konsisten dengan yang benar-benar
                // tersimpan di kolom users -- rank_points loser dibatasi minimal 0 lewat GREATEST().
                $winnerRatingChange = $newWinnerRating - $winnerRating;
                $loserRatingChange = $newLoserRating - $loserRating;
                $winnerRankPointsChange = $rankPointsGain;
                $loserRankPointsChange = max(0, $loserRankPointsBefore - 5) - $loserRankPointsBefore;
            }
            $stmt = $pdo->prepare('UPDATE rooms SET game_state = ?, status = "finished", last_updated = NOW() WHERE id = ?');
            $stmt->execute([json_encode($state), $room['id']]);
            // Pertandingan latihan TETAP direkam (ranked=0, perubahan poin NULL) supaya
            // masih muncul di Riwayat Pertandingan pemain -- cuma tidak menyumbang ke
            // rating/rank_points/wins/losses publik seperti dijelaskan di atas.
            $pdo->prepare('INSERT INTO match_history (winner_id, loser_id, winner_candles_left, room_code, winner_rating_change, loser_rating_change, winner_rank_points_change, loser_rank_points_change, ranked, played_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$winnerId, $loserId, $state['lives'][$state['winner']] ?? 0, $code, $winnerRatingChange, $loserRatingChange, $winnerRankPointsChange, $loserRankPointsChange, $isRanked ? 1 : 0]);
        }

        $pdo->commit();
        jsonResponse(['ok' => true, 'state' => OnlineEngine::stripSecrets($state, $side), 'side' => $side]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

jsonResponse(['ok' => false, 'error' => 'Method not allowed.']);
