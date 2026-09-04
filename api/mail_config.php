<?php
// Konfigurasi SMTP opsional. Isi untuk mengirim email via SMTP (Gmail, dll).
// Copy mail_config.example.php ke mail_config.php dan isi kredensial.
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_username' => 'gamekartuduasatu@gmail.com',
    // PENTING: App Password lama sudah pernah bocor (ikut ter-zip & dibagikan).
    // Cabut App Password itu di myaccount.google.com/apppasswords, buat yang baru,
    // baru isi di bawah ini (16 karakter, spasi dibuang -- App Password memang begini formatnya).
    'smtp_password' => 'GANTI_DENGAN_APP_PASSWORD_BARU',
    'smtp_secure' => 'tls',
    'smtp_port' => 587,
    // "From" HARUS sama dengan akun Gmail yang dipakai login SMTP di atas,
    // kalau tidak Gmail akan menolak/mengganti pengirimnya secara paksa.
    'from' => 'gamekartuduasatu@gmail.com',
    'from_name' => '21: Ruang Lilin',
];
