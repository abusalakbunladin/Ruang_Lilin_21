<?php
// File: api/db.php
// Proyek: 21: Ruang Lilin
// Fungsi: Koneksi database PDO dan helper query.
// Suppress stray warnings from InfinityFree's aggressive error reporting
@ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);
set_time_limit(15);
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

// PENTING -- BACA INI:
// Kredensial DB TIDAK lagi disimpan langsung di file ini. Sebelumnya password
// lama sudah pernah bocor (ikut ter-zip & dibagikan) sehingga HARUS dianggap
// tidak aman lagi. Kredensial sekarang dipisah ke api/db_config.php (persis
// seperti pola mail_config.php), supaya:
//   1) file ini (db.php) aman dibagikan/dizip ulang tanpa ikut membocorkan password,
//   2) db_config.php diblokir dari akses browser langsung lewat api/.htaccess.
// LANGKAH WAJIB sebelum situs bisa berfungsi:
//   1. Ganti password database ini lewat panel InfinityFree
//      (menu MySQL Databases -> Change Password).
//   2. Isi password BARU itu ke dalam api/db_config.php (bukan file ini).
if (!file_exists(__DIR__ . '/db_config.php')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Konfigurasi database belum ada. Copy api/db_config.example.php menjadi api/db_config.php lalu isi kredensialnya.']);
    exit;
}
$dbConfig = require __DIR__ . '/db_config.php';
$DB_HOST = $dbConfig['host'];
$DB_NAME = $dbConfig['name'];
$DB_USER = $dbConfig['user'];
$DB_PASS = $dbConfig['pass'];

// Fail fast kalau password masih placeholder -- daripada menunggu PDO mencoba
// konek ke MySQL dan timeout (bisa sampai beberapa detik per request, ini yang
// menyebabkan /api/auth.php?action=status butuh ~2 detik dan berakhir error 500
// di laporan PageSpeed). Dengan pengecekan ini, responsnya INSTAN dan jelas.
if ($DB_PASS === '' || $DB_PASS === 'GANTI_DENGAN_PASSWORD_BARU') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Password database di api/db_config.php belum diisi. Ganti password lewat panel InfinityFree lalu isi di sana.']);
    exit;
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
    $options[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 5;
}

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection failed: ' . $e->getMessage() . ' (pastikan kredensial di api/db_config.php sudah benar)']);
    exit;
}

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

// Satu-satunya sumber kebenaran untuk tingkatan judul rank berdasarkan rating.
// Dipakai di semua tempat (bukan kolom `rank` di tabel users, yang cuma cache dan bisa basi)
// supaya julukan yang ditampilkan SELALU sesuai rating terkini pemain.
function rankTitleForRating($rating) {
    $rating = (int)$rating;
    if ($rating >= 2000) return 'Penjaga Gerbang';
    if ($rating >= 1800) return 'Jiwa Terpilih';
    if ($rating >= 1600) return 'Penjelajah Ruang';
    if ($rating >= 1400) return 'Pembakar Lilin';
    if ($rating >= 1200) return 'Penyala Api';
    if ($rating >= 1000) return 'Jiwa Tersesat';
    return 'Bayangan';
}

function requireAuth() {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        jsonResponse(['ok' => false, 'error' => 'Not authenticated']);
    }
}

function requireAdmin() {
    global $pdo;
    requireAuth();
    
    // If role not in session, fetch from database
    if (empty($_SESSION['role'])) {
        try {
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user && !empty($user['role'])) {
                $_SESSION['role'] = $user['role'];
            }
        } catch (PDOException $e) {
            error_log('Failed to fetch user role: ' . $e->getMessage());
        }
    }
    
    if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'support'], true)) {
        http_response_code(403);
        jsonResponse(['ok' => false, 'error' => 'Forbidden: Admin access required']);
    }
}

// Pastikan tabel-tabel yang dibutuhkan sudah ada (berguna kalau user belum sempat menjalankan schema.sql)
//
// PENTING (perbaikan performa): blok CREATE TABLE/ALTER TABLE di bawah ini
// dulu dijalankan ULANG di SETIAP request ke SEMUA endpoint api/*.php --
// termasuk game_action.php & room.php yang di-poll tiap 2.5-2.8 detik selama
// pertandingan multiplayer berlangsung! Itu artinya satu pertandingan singkat
// saja bisa memicu ribuan query DDL yang tidak perlu, menghabiskan kuota query
// MySQL gratis InfinityFree dan menambah latensi di setiap request.
// Sekarang blok ini hanya benar-benar dijalankan maksimal 1x per jam lewat
// penanda file kecil di bawah -- kalau nanti ada ALTER TABLE baru ditambahkan,
// tetap akan otomatis kejalan dalam waktu maksimal 1 jam ke semua server tanpa
// perlu campur tangan manual.
$schemaCheckFile = __DIR__ . '/.schema_checked';
$needsSchemaCheck = true;
if (@file_exists($schemaCheckFile) && (time() - @filemtime($schemaCheckFile)) < 3600) {
    $needsSchemaCheck = false;
}
if ($needsSchemaCheck) {
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `username` VARCHAR(32) NOT NULL,
      `email` VARCHAR(255) DEFAULT NULL,
      `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
      `password` VARCHAR(255) NOT NULL,
      `wins` INT(11) NOT NULL DEFAULT 0,
      `losses` INT(11) NOT NULL DEFAULT 0,
      `rank` VARCHAR(64) NOT NULL DEFAULT 'Jiwa Tersesat',
      `role` ENUM('user','support','admin') NOT NULL DEFAULT 'user',
      `banned` TINYINT(1) NOT NULL DEFAULT 0,
      `ban_reason` VARCHAR(255) DEFAULT NULL,
      `ban_description` VARCHAR(255) DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_username` (`username`),
      UNIQUE KEY `idx_email` (`email`),
      KEY `idx_email_verified` (`email_verified`),
      KEY `idx_banned` (`banned`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `rooms` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `room_code` VARCHAR(6) NOT NULL,
      `host_id` INT(11) UNSIGNED NOT NULL,
      `guest_id` INT(11) UNSIGNED DEFAULT NULL,
      `status` ENUM('waiting','playing','finished') NOT NULL DEFAULT 'waiting',
      `game_state` MEDIUMTEXT DEFAULT NULL,
      `visibility` ENUM('public','private') NOT NULL DEFAULT 'private',
      `lobby_name` VARCHAR(40) DEFAULT NULL,
      `settings` TEXT DEFAULT NULL,
      `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_room_code` (`room_code`),
      KEY `idx_host` (`host_id`),
      KEY `idx_guest` (`guest_id`),
      KEY `idx_status` (`status`),
      KEY `idx_visibility_status` (`visibility`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    // PERBAIKAN PENTING (lihat CHANGELOG): kolom game_state di atas dulu bertipe TEXT
    // (batas 64KB). Pertandingan online yang berjalan cukup lama -- banyak ronde dan/atau
    // banyak kartu spesial dimainkan -- membuat JSON state (termasuk array 'logs' Riwayat
    // Kejadian yang terus bertambah) menembus batas itu. Efeknya: MySQL memotong diam-diam
    // isi kolom di tengah JSON, baris jadi tidak valid, lalu json_decode() gagal dan
    // game_action.php mengiranya "room baru" -- me-reset seluruh pertandingan (skor, ronde,
    // dan tentu saja Riwayat Kejadian) tanpa pemberitahuan apa pun ke pemain. ALTER di bawah
    // ini melebarkan kolom yang SUDAH ADA di database production ke MEDIUMTEXT (maks 16MB,
    // longgar sekali untuk skala game ini) supaya hal ini tidak terjadi lagi. CREATE TABLE
    // IF NOT EXISTS di atas tidak menyentuh tabel yang sudah ada, jadi ALTER manual ini
    // wajib ada -- akan otomatis jalan ke semua server dalam waktu maksimal 1 jam lewat
    // mekanisme .schema_checked yang sama seperti ALTER lain di bawah.
    try {
      $pdo->exec("ALTER TABLE `rooms` MODIFY COLUMN `game_state` MEDIUMTEXT DEFAULT NULL;");
    } catch (PDOException $e2) {
      error_log('Gagal migrasi rooms.game_state ke MEDIUMTEXT: ' . $e2->getMessage());
    }
    // ============================================================
    // [MATCHMAKING] Migrasi utk instalasi lama: tabel `rooms` yang sudah ada
    // dari sebelum fitur "Cari Lawan" & "Lobi Publik" ini dibuat belum punya
    // kolom `visibility`/`lobby_name`. try/catch (bukan IF NOT EXISTS) supaya
    // konsisten dengan pola migrasi kolom lain di file ini & tetap jalan di
    // versi MySQL lama yang tidak dukung ADD COLUMN IF NOT EXISTS.
    // ============================================================
    try { $pdo->exec("ALTER TABLE `rooms` ADD COLUMN `visibility` ENUM('public','private') NOT NULL DEFAULT 'private'"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `rooms` ADD COLUMN `lobby_name` VARCHAR(40) DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `rooms` ADD COLUMN `settings` TEXT DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `rooms` ADD INDEX `idx_visibility_status` (`visibility`, `status`)"); } catch (PDOException $e2) {}
    $pdo->exec("CREATE TABLE IF NOT EXISTS `match_history` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `winner_id` INT(11) UNSIGNED NOT NULL,
      `loser_id` INT(11) UNSIGNED NOT NULL,
      `winner_candles_left` INT(11) NOT NULL DEFAULT 0,
      `played_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_winner` (`winner_id`),
      KEY `idx_loser` (`loser_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `friends` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id_1` INT(11) UNSIGNED NOT NULL,
      `user_id_2` INT(11) UNSIGNED NOT NULL,
      `requester_id` INT(11) UNSIGNED NOT NULL,
      `status` ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_friend_pair` (`user_id_1`,`user_id_2`),
      KEY `idx_user1` (`user_id_1`),
      KEY `idx_user2` (`user_id_2`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    try {
      $pdo->exec("ALTER TABLE `friends` ADD COLUMN IF NOT EXISTS `requester_id` INT(11) UNSIGNED NOT NULL DEFAULT 0;");
    } catch (PDOException $e2) {
      // abaikan kalau sudah ada atau versi MySQL tidak mendukung IF NOT EXISTS
    }
    try {
      $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `role` ENUM('user','support','admin') NOT NULL DEFAULT 'user';");
    } catch (PDOException $e2) {
      // abaikan
    }
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `email` VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD UNIQUE KEY `idx_email` (`email`)"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD KEY `idx_email_verified` (`email_verified`)"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `banned` TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `ban_reason` VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `ban_description` VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD KEY `idx_banned` (`banned`)"); } catch (PDOException $e2) {}
    $pdo->exec("CREATE TABLE IF NOT EXISTS `email_verifications` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) UNSIGNED NOT NULL,
      `email` VARCHAR(255) NOT NULL,
      `code` VARCHAR(16) NOT NULL,
      `type` ENUM('register','reset') NOT NULL DEFAULT 'register',
      `used` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `expires_at` TIMESTAMP NOT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_user` (`user_id`),
      KEY `idx_code` (`code`),
      KEY `idx_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `messages` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `room_id` INT(11) UNSIGNED DEFAULT NULL,
      `from_user_id` INT(11) UNSIGNED NOT NULL,
      `to_user_id` INT(11) UNSIGNED DEFAULT NULL,
      `body` TEXT NOT NULL,
      `read_at` TIMESTAMP NULL DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_room` (`room_id`),
      KEY `idx_to` (`to_user_id`),
      KEY `idx_from` (`from_user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    // Migrasi untuk tabel messages yang sudah ada dari sebelum fitur "pesan terbaca" ini dibuat.
    try { $pdo->exec("ALTER TABLE `messages` ADD COLUMN `read_at` TIMESTAMP NULL DEFAULT NULL"); } catch (PDOException $e2) {}
    $pdo->exec("CREATE TABLE IF NOT EXISTS `password_requests` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) UNSIGNED NOT NULL,
      `username` VARCHAR(32) NOT NULL,
      `email` VARCHAR(255) DEFAULT NULL,
      `reason` TEXT DEFAULT NULL,
      `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      `admin_id` INT(11) UNSIGNED DEFAULT NULL,
      `admin_note` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_user` (`user_id`),
      KEY `idx_status` (`status`),
      KEY `idx_admin` (`admin_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    // Tabel galeri: unlock konten galeri per akun
    $pdo->exec("CREATE TABLE IF NOT EXISTS `gallery_unlocks` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) UNSIGNED NOT NULL,
      `unlock_key` VARCHAR(64) NOT NULL,
      `unlocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_user_unlock` (`user_id`, `unlock_key`),
      KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    // Tabel campaign_history: riwayat penyelesaian kampanye
    $pdo->exec("CREATE TABLE IF NOT EXISTS `campaign_history` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) UNSIGNED NOT NULL,
      `result` ENUM('win','lose') NOT NULL,
      `difficulty` VARCHAR(16) NOT NULL,
      `stage_reached` INT(11) NOT NULL DEFAULT 1,
      `total_rounds` INT(11) NOT NULL DEFAULT 0,
      `played_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_user` (`user_id`),
      KEY `idx_user_result` (`user_id`, `result`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    // Migrasi kolom users untuk gallery & campaign (pakai try-catch karena MySQL versi lama tidak support IF NOT EXISTS)
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `hard_clear` TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `campaign_best_mode` VARCHAR(16) DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `campaign_completed` TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $e2) {}
    // Migrasi rating & rank_points (tanpa IF NOT EXISTS untuk kompatibilitas)
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `rating` INT(11) NOT NULL DEFAULT 1000"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `rank_points` INT(11) NOT NULL DEFAULT 0"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `losses` INT(11) NOT NULL DEFAULT 0"); } catch (PDOException $e2) {}

    // ============================================================
    // Perluasan match_history: simpan kode ruangan + perubahan rating/rank
    // points PER PERTANDINGAN, supaya riwayat pertandingan di profil bisa
    // menampilkan "dapat/kehilangan berapa poin" untuk tiap match secara akurat
    // (tidak bisa dihitung ulang belakangan karena rating waktu itu berubah-ubah).
    // ============================================================
    try { $pdo->exec("ALTER TABLE `match_history` ADD COLUMN `room_code` VARCHAR(6) DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `match_history` ADD COLUMN `winner_rating_change` INT(11) DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `match_history` ADD COLUMN `loser_rating_change` INT(11) DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `match_history` ADD COLUMN `winner_rank_points_change` INT(11) DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `match_history` ADD COLUMN `loser_rank_points_change` INT(11) DEFAULT NULL"); } catch (PDOException $e2) {}
    try { $pdo->exec("ALTER TABLE `match_history` ADD INDEX `idx_room_code` (`room_code`)"); } catch (PDOException $e2) {}

    // ============================================================
    // [PERINGKAT VS LATIHAN] V.0.9.1.0 -- kolom `ranked` menandai apakah SATU
    // pertandingan ini ikut dihitung ke rating/rank_points/wins/losses publik
    // pemain, atau cuma pertandingan latihan yang direkam ke riwayat tanpa
    // memengaruhi statistik publik. Default 1 supaya baris lama (sebelum
    // kolom ini ada, dari saat SEMUA pertandingan online masih dihitung
    // sebagai peringkat) tetap ditandai sesuai perilaku aslinya -- tidak ada
    // riwayat lama yang tiba-tiba "ditandai ulang" jadi latihan.
    // Logika penentuan nilai ranked (visibility==='public') ada di
    // game_action.php saat pertandingan selesai, BUKAN di sini.
    // ============================================================
    try { $pdo->exec("ALTER TABLE `match_history` ADD COLUMN `ranked` TINYINT(1) NOT NULL DEFAULT 1"); } catch (PDOException $e2) {}

    // ============================================================
    // Tabel laporan bug: dikirim pemain, ditangani admin/support.
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bug_reports` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) UNSIGNED NOT NULL,
      `username` VARCHAR(32) NOT NULL,
      `category` VARCHAR(64) NOT NULL,
      `severity` ENUM('rendah','sedang','tinggi','kritis') NOT NULL DEFAULT 'sedang',
      `title` VARCHAR(150) NOT NULL,
      `description` TEXT NOT NULL,
      `steps` TEXT DEFAULT NULL,
      `device_info` VARCHAR(255) DEFAULT NULL,
      `game_context` VARCHAR(255) DEFAULT NULL,
      `status` ENUM('baru','dilihat','diproses','selesai','ditolak') NOT NULL DEFAULT 'baru',
      `admin_note` TEXT DEFAULT NULL,
      `admin_id` INT(11) UNSIGNED DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_user` (`user_id`),
      KEY `idx_status` (`status`),
      KEY `idx_severity` (`severity`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ============================================================
    // Tabel laporan pemain (cheat/kecurangan/pelanggaran), ditangani admin/support.
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `player_reports` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `reporter_id` INT(11) UNSIGNED NOT NULL,
      `reporter_username` VARCHAR(32) NOT NULL,
      `reported_username` VARCHAR(32) NOT NULL,
      `reported_user_id` INT(11) UNSIGNED DEFAULT NULL,
      `room_code` VARCHAR(6) DEFAULT NULL,
      `category` VARCHAR(64) NOT NULL,
      `description` TEXT NOT NULL,
      `game_context` VARCHAR(255) DEFAULT NULL,
      `status` ENUM('baru','ditinjau','ditindak','ditolak') NOT NULL DEFAULT 'baru',
      `action_taken` VARCHAR(64) DEFAULT NULL,
      `admin_note` TEXT DEFAULT NULL,
      `admin_id` INT(11) UNSIGNED DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_reporter` (`reporter_id`),
      KEY `idx_reported_user` (`reported_user_id`),
      KEY `idx_reported_username` (`reported_username`),
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ============================================================
    // Tabel lampiran screenshot untuk laporan bug & laporan pemain.
    // Dipisah dari tabel utama supaya tabel laporan tetap ringan/cepat
    // di-query untuk daftar, dan lampiran cuma diambil saat lihat detail.
    // ============================================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `report_attachments` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `report_type` ENUM('bug','player') NOT NULL,
      `report_id` INT(11) UNSIGNED NOT NULL,
      `image_data` MEDIUMTEXT NOT NULL,
      `mime_type` VARCHAR(32) NOT NULL DEFAULT 'image/jpeg',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_report` (`report_type`, `report_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (PDOException $e) {
    // Biarkan error tertangkap saat query berikutnya dijalankan agar respons tetap JSON
}
@touch($schemaCheckFile);
}
