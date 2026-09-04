-- ============================================================
-- Skema Database 21: Ruang Lilin (Multiplayer Online)
-- Jalankan file ini di MySQL/MariaDB panel InfinityFree
-- Database target: if0_42474879_kartu
-- ============================================================

-- Pilih database (sesuaikan dengan nama database Anda)
USE `if0_42474879_kartu`;

-- Tabel pemain
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(32) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `password` VARCHAR(255) NOT NULL,
  `wins` INT(11) NOT NULL DEFAULT 0,
  `losses` INT(11) NOT NULL DEFAULT 0,
  `rank` VARCHAR(64) NOT NULL DEFAULT 'Jiwa Tersesat',
  `rating` INT(11) NOT NULL DEFAULT 1000,
  `rank_points` INT(11) NOT NULL DEFAULT 0,
  `hard_clear` TINYINT(1) NOT NULL DEFAULT 0,
  `campaign_best_mode` VARCHAR(16) DEFAULT NULL,
  `campaign_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `role` ENUM('user','support','admin') NOT NULL DEFAULT 'user',
  `banned` TINYINT(1) NOT NULL DEFAULT 0,
  `ban_reason` VARCHAR(255) DEFAULT NULL,
  `ban_description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`),
  UNIQUE KEY `idx_email` (`email`),
  KEY `idx_email_verified` (`email_verified`),
  KEY `idx_banned` (`banned`),
  KEY `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel ruangan multiplayer
CREATE TABLE IF NOT EXISTS `rooms` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_code` VARCHAR(6) NOT NULL,
  `host_id` INT(11) UNSIGNED NOT NULL,
  `guest_id` INT(11) UNSIGNED DEFAULT NULL,
  `status` ENUM('waiting','playing','finished') NOT NULL DEFAULT 'waiting',
  `game_state` MEDIUMTEXT DEFAULT NULL, -- Lihat CHANGELOG: TEXT (64KB) bisa terpotong diam-diam pada pertandingan panjang, merusak seluruh riwayat & state
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MIGRATION: Sistem "Cari Lawan" (quick match) & "Lobi Publik"
-- `visibility` membedakan ruangan kode privat (lama, tidak berubah) dari
-- lobi publik yang bisa ditemukan lewat jelajah daftar ATAU otomatis lewat
-- tombol Cari Lawan -- keduanya berbagi kolam ruangan `visibility='public'`
-- yang sama. `lobby_name` opsional, cuma dipakai lobi publik yang dibuat
-- manual dengan nama custom (NULL = dibuat otomatis lewat Cari Lawan).
-- `settings` (JSON) menyimpan pengaturan kustom (waktu giliran/nyawa
-- awal/kartu trump yang diizinkan) -- HANYA pernah diisi utk ruangan kode
-- PRIVAT (lihat OnlineEngine::resolveSettingsForRoom di engine.php, yang
-- memaksa NULL/default utk ruangan apa pun yang visibility-nya bukan
-- 'private' -- menjamin Cari Lawan & Lobi Publik selalu peraturan klasik).
-- ============================================================
ALTER TABLE `rooms`
ADD COLUMN IF NOT EXISTS `visibility` ENUM('public','private') NOT NULL DEFAULT 'private' AFTER `status`,
ADD COLUMN IF NOT EXISTS `lobby_name` VARCHAR(40) DEFAULT NULL AFTER `visibility`,
ADD COLUMN IF NOT EXISTS `settings` TEXT DEFAULT NULL AFTER `lobby_name`,
ADD INDEX IF NOT EXISTS `idx_visibility_status` (`visibility`, `status`);

-- Tabel riwayat pertandingan
CREATE TABLE IF NOT EXISTS `match_history` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `winner_id` INT(11) UNSIGNED NOT NULL,
  `loser_id` INT(11) UNSIGNED NOT NULL,
  `winner_candles_left` INT(11) NOT NULL DEFAULT 0,
  `room_code` VARCHAR(6) DEFAULT NULL,
  `winner_rating_change` INT(11) DEFAULT NULL,
  `loser_rating_change` INT(11) DEFAULT NULL,
  `winner_rank_points_change` INT(11) DEFAULT NULL,
  `loser_rank_points_change` INT(11) DEFAULT NULL,
  `ranked` TINYINT(1) NOT NULL DEFAULT 1,
  `played_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_winner` (`winner_id`),
  KEY `idx_loser` (`loser_id`),
  KEY `idx_room_code` (`room_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Catatan: kolom room_code/winner_rating_change/dst di atas juga otomatis
-- ditambahkan lewat ALTER TABLE di api/db.php untuk instalasi yang sudah ada
-- (tidak perlu jalankan manual).
-- `ranked` (V.0.9.1.0): 1 = pertandingan publik/Cari Lawan yang dihitung ke
-- rating/rank_points/wins/losses; 0 = pertandingan latihan di ruangan kode
-- privat, tetap direkam di riwayat tapi tidak memengaruhi statistik publik.
-- Lihat api/game_action.php ($isRanked) untuk logika penentuannya.

-- Tabel pertemanan
CREATE TABLE IF NOT EXISTS `friends` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel kode verifikasi email (registrasi & reset password)
CREATE TABLE IF NOT EXISTS `email_verifications` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel pesan (chat in-game & antar teman)
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` INT(11) UNSIGNED DEFAULT NULL,
  `from_user_id` INT(11) UNSIGNED NOT NULL,
  `to_user_id` INT(11) UNSIGNED DEFAULT NULL,
  `body` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room` (`room_id`),
  KEY `idx_to` (`to_user_id`),
  KEY `idx_from` (`from_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel permintaan ganti password
CREATE TABLE IF NOT EXISTS `password_requests` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel galeri: menyimpan unlock konten galeri per akun
CREATE TABLE IF NOT EXISTS `gallery_unlocks` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `unlock_key` VARCHAR(64) NOT NULL,
  `unlocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_unlock` (`user_id`, `unlock_key`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel campaign_history: riwayat penyelesaian kampanye
CREATE TABLE IF NOT EXISTS `campaign_history` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MIGRATION: Tambah kolom rating dan rank_points
-- ============================================================
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `rating` INT(11) NOT NULL DEFAULT 1000 AFTER `rank`,
ADD COLUMN IF NOT EXISTS `rank_points` INT(11) NOT NULL DEFAULT 0 AFTER `rating`,
ADD INDEX IF NOT EXISTS `idx_rating` (`rating`);

UPDATE `users` SET `rating` = 1000 WHERE `rating` = 0 OR `rating` IS NULL;

ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `losses` INT(11) NOT NULL DEFAULT 0 AFTER `wins`;

-- ============================================================
-- MIGRATION: Tambah kolom galeri & campaign ke users
-- ============================================================
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `hard_clear` TINYINT(1) NOT NULL DEFAULT 0 AFTER `rank_points`,
ADD COLUMN IF NOT EXISTS `campaign_best_mode` VARCHAR(16) DEFAULT NULL AFTER `hard_clear`,
ADD COLUMN IF NOT EXISTS `campaign_completed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `campaign_best_mode`;

-- ============================================================
-- FITUR: Sistem Laporan Bug & Laporan Pemain (Cheat Report)
-- Catatan: tabel-tabel ini JUGA otomatis dibuat oleh api/db.php saat
-- request pertama (lihat blok $needsSchemaCheck), jadi menjalankan file
-- ini secara manual bersifat OPSIONAL -- hanya untuk dokumentasi/referensi
-- atau kalau ingin membuat tabelnya lebih cepat tanpa menunggu auto-migrate.
-- ============================================================

-- Tabel laporan bug: dikirim pemain, ditangani admin/support.
CREATE TABLE IF NOT EXISTS `bug_reports` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel laporan pemain (cheat/kecurangan/pelanggaran), ditangani admin/support.
CREATE TABLE IF NOT EXISTS `player_reports` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel lampiran screenshot untuk laporan bug & laporan pemain (dipisah dari
-- tabel utama supaya daftar laporan tetap ringan/cepat di-query).
CREATE TABLE IF NOT EXISTS `report_attachments` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_type` ENUM('bug','player') NOT NULL,
  `report_id` INT(11) UNSIGNED NOT NULL,
  `image_data` MEDIUMTEXT NOT NULL,
  `mime_type` VARCHAR(32) NOT NULL DEFAULT 'image/jpeg',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report` (`report_type`, `report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;