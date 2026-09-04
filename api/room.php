<?php
require 'db.php';
require 'engine.php'; // [PENGATURAN RUANGAN] utk OnlineEngine::CARD_POOL & daftar opsi valid -- aman di-require, engine.php cuma definisi class, tidak ada dispatcher/action di level atas seperti file api/*.php lainnya

function generateCode($pdo) {
    for ($i = 0; $i < 10; $i++) {
        $code = substr(strtoupper(base_convert(bin2hex(random_bytes(4)), 16, 36)), 0, 6);
        $stmt = $pdo->prepare('SELECT id FROM rooms WHERE room_code = ?');
        $stmt->execute([$code]);
        if (!$stmt->fetch()) return $code;
    }
    return null;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'get';

if ($action === 'create') {
    requireAuth();
    $host_id = $_SESSION['user_id'];

    // [MATCHMAKING] visibility publik/privat opsional -- default TETAP
    // 'private', jadi semua pemanggilan lama (tombol "Buat Ruangan" yang
    // sudah ada di lobi kode) berperilaku SAMA PERSIS seperti sebelumnya.
    $visibility = (($_POST['visibility'] ?? 'private') === 'public') ? 'public' : 'private';
    $lobbyName = trim($_POST['lobby_name'] ?? '');
    if (strlen($lobbyName) > 40) $lobbyName = substr($lobbyName, 0, 40);
    if ($lobbyName === '') $lobbyName = null;

    // ⚠️ PENTING -- JANGAN HAPUS pengecekan "$visibility === 'private'" di
    // bawah ini. Ini LAPIS PERTAMA dari jaminan "matchmaking selalu klasik"
    // (lapis kedua ada di OnlineEngine::resolveSettingsForRoom, engine.php).
    // Kalau ini dihapus, ruangan publik BISA menyimpan settings kustom.
    //
    // [PENGATURAN RUANGAN] settings kustom (waktu giliran/nyawa awal/kartu
    // trump yang diizinkan) HANYA PERNAH disimpan utk ruangan PRIVAT -- utk
    // publik selalu dipaksa NULL di sini (lapis pertahanan pertama; lapis
    // kedua ada di OnlineEngine::resolveSettingsForRoom saat match dimulai).
    // Ini yang menjamin Cari Lawan & Lobi Publik SELALU main dgn peraturan
    // klasik, tidak peduli apa pun yang dikirim client.
    $settingsJson = null;
    if ($visibility === 'private' && !empty($_POST['settings'])) {
        $decoded = json_decode($_POST['settings'], true);
        if (is_array($decoded)) {
            $turnSeconds = (int)($decoded['turn_seconds'] ?? 60);
            $startingLife = (int)($decoded['starting_life'] ?? OnlineEngine::STARTING_LIFE);
            $allCardIds = array_column(OnlineEngine::CARD_POOL, 'id');
            $enabledCards = is_array($decoded['enabled_cards'] ?? null)
                ? array_values(array_intersect($allCardIds, $decoded['enabled_cards']))
                : $allCardIds;
            if (empty($enabledCards)) $enabledCards = $allCardIds;
            $isValid = in_array($turnSeconds, OnlineEngine::TURN_SECONDS_OPTIONS, true) && in_array($startingLife, OnlineEngine::STARTING_LIFE_OPTIONS, true);
            $isClassic = $turnSeconds === 60 && $startingLife === OnlineEngine::STARTING_LIFE && count($enabledCards) === count($allCardIds);
            if ($isValid && !$isClassic) {
                $settingsJson = json_encode(['turn_seconds' => $turnSeconds, 'starting_life' => $startingLife, 'enabled_cards' => $enabledCards]);
            }
        }
    }

    // Aman dipanggil ulang khusus utk lobi PUBLIK: kalau host ini ternyata
    // sudah host lobi publik yang masih menunggu (mis. reload halaman),
    // kembalikan lobi yang sama itu daripada menumpuk lobi kedua di daftar
    // publik. Tidak berlaku utk ruangan kode privat (biarkan seperti semula).
    if ($visibility === 'public') {
        $stmt = $pdo->prepare("SELECT id, room_code FROM rooms WHERE host_id = ? AND status = 'waiting' AND visibility = 'public' LIMIT 1");
        $stmt->execute([$host_id]);
        $existing = $stmt->fetch();
        if ($existing) {
            jsonResponse(['ok' => true, 'room_code' => $existing['room_code'], 'room_id' => (int)$existing['id'], 'status' => 'waiting', 'is_host' => true]);
        }
    }

    $code = generateCode($pdo);
    if (!$code) jsonResponse(['ok' => false, 'error' => 'Tidak bisa membuat kode ruangan.']);
    $stmt = $pdo->prepare('INSERT INTO rooms (room_code, host_id, status, visibility, lobby_name, settings, game_state, last_updated) VALUES (?, ?, "waiting", ?, ?, ?, NULL, NOW())');
    $stmt->execute([$code, $host_id, $visibility, $lobbyName, $settingsJson]);
    $roomId = (int)$pdo->lastInsertId();
    jsonResponse(['ok' => true, 'room_code' => $code, 'room_id' => $roomId, 'status' => 'waiting', 'is_host' => true]);
}

// [PENGATURAN RUANGAN] Data statis (bukan spesifik 1 ruangan) utk merender
// panel pengaturan di client: daftar lengkap kartu trump + pilihan valid
// waktu giliran/nyawa awal. Diambil langsung dari OnlineEngine supaya
// client, room.php (validasi di atas), dan engine.php (validasi ulang saat
// match dimulai) semuanya bersumber dari SATU daftar yang sama -- tidak
// mungkin tidak sinkron.
if ($action === 'card_pool') {
    requireAuth();
    $cards = array_map(function($c) {
        return ['id' => $c['id'], 'name' => $c['name'], 'desc' => $c['desc'], 'online' => !empty($c['online'])];
    }, OnlineEngine::CARD_POOL);
    jsonResponse([
        'ok' => true,
        'cards' => $cards,
        'turn_seconds_options' => OnlineEngine::TURN_SECONDS_OPTIONS,
        'starting_life_options' => OnlineEngine::STARTING_LIFE_OPTIONS,
        'classic' => ['turn_seconds' => 60, 'starting_life' => OnlineEngine::STARTING_LIFE],
    ]);
}

if ($action === 'join') {
    requireAuth();
    $code = trim(strtoupper($_POST['room_code'] ?? ''));
    $guest_id = $_SESSION['user_id'];
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM rooms WHERE room_code = ? FOR UPDATE');
        $stmt->execute([$code]);
        $room = $stmt->fetch();
        if (!$room) {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'error' => 'Kode ruangan tidak ditemukan.']);
        }
        if ($room['status'] !== 'waiting') {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'error' => 'Ruangan sudah penuh atau selesai.']);
        }
        if ($room['host_id'] == $guest_id) {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'error' => 'Anda adalah pemilik ruangan.']);
        }
        if (!empty($room['guest_id'])) {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'error' => 'Ruangan sudah penuh.']);
        }
        $stmt = $pdo->prepare('UPDATE rooms SET guest_id = ?, status = "playing", last_updated = NOW() WHERE id = ?');
        $stmt->execute([$guest_id, $room['id']]);
        $pdo->commit();
        jsonResponse(['ok' => true, 'room_code' => $code, 'room_id' => (int)$room['id'], 'status' => 'playing', 'is_host' => false]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Gagal bergabung.']);
    }
}

if ($action === 'get') {
    requireAuth();
    $code = trim(strtoupper($_GET['room_code'] ?? ''));
    // [LOBI KREATIF] V.0.9.1.0 -- rating/rank host & tamu diikutsertakan (LEFT JOIN
    // users lagi dgn alias berbeda) supaya layar tunggu bisa menampilkan panel duel
    // "kamu vs lawan" (nama + tingkatan) tanpa perlu request tambahan terpisah.
    // `ranked` dihitung persis dgn aturan yang sama dipakai api/game_action.php saat
    // pertandingan selesai (visibility publik & tanpa settings kustom) supaya lencana
    // Peringkat/Latihan di layar tunggu SELALU cocok dgn yang benar-benar terjadi ke
    // statistik pemain nanti -- bukan sumber kebenaran terpisah yang bisa tidak sinkron.
    $stmt = $pdo->prepare('SELECT r.*, h.username AS host_name, h.rating AS host_rating, g.username AS guest_name, g.rating AS guest_rating
        FROM rooms r
        JOIN users h ON h.id = r.host_id
        LEFT JOIN users g ON g.id = r.guest_id
        WHERE r.room_code = ?');
    $stmt->execute([$code]);
    $room = $stmt->fetch();
    if (!$room) jsonResponse(['ok' => false, 'error' => 'Ruangan tidak ditemukan.']);
    $uid = $_SESSION['user_id'];
    if ($room['host_id'] != $uid && $room['guest_id'] != $uid) {
        jsonResponse(['ok' => false, 'error' => 'Anda bukan anggota ruangan ini.']);
    }
    jsonResponse(['ok' => true, 'room' => [
        'room_id' => (int)$room['id'],
        'room_code' => $room['room_code'],
        'status' => $room['status'],
        'visibility' => $room['visibility'],
        'ranked' => ($room['visibility'] === 'public' && empty($room['settings'])),
        'host_id' => (int)$room['host_id'],
        'guest_id' => $room['guest_id'] ? (int)$room['guest_id'] : null,
        'host_name' => $room['host_name'],
        'guest_name' => $room['guest_name'],
        'host_rating' => $room['host_rating'] !== null ? (int)$room['host_rating'] : null,
        'host_rank' => $room['host_rating'] !== null ? rankTitleForRating($room['host_rating']) : null,
        'guest_rating' => $room['guest_rating'] !== null ? (int)$room['guest_rating'] : null,
        'guest_rank' => $room['guest_rating'] !== null ? rankTitleForRating($room['guest_rating']) : null,
        'last_updated' => $room['last_updated']
    ]]);
}

if ($action === 'leave') {
    requireAuth();
    $code = trim(strtoupper($_POST['room_code'] ?? ''));
    $uid = $_SESSION['user_id'];
    $stmt = $pdo->prepare('DELETE FROM rooms WHERE room_code = ? AND (host_id = ? OR guest_id = ?)');
    $stmt->execute([$code, $uid, $uid]);
    jsonResponse(['ok' => true, 'affected' => $stmt->rowCount()]);
}

jsonResponse(['ok' => false, 'error' => 'Unknown action']);
