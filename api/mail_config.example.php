<?php
// Rename file ini menjadi mail_config.php dan isi kredensial SMTP.
// InfinityFree free memblokir fungsi mail() bawaan, jadi SMTP eksternal wajib
// agar kode verifikasi/password benar-benar terkirim.
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_username' => 'akun@gmail.com',
    'smtp_password' => 'app_password_16_karakter',
    'smtp_secure' => 'tls',            // atau 'ssl'
    'smtp_port' => 587,                // 465 untuk ssl
    'from' => 'no-reply@gamekartuduasatu.freepage.cc',
    'from_name' => '21: Ruang Lilin',
];
