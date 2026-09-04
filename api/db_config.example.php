<?php
// Copy file ini menjadi db_config.php (di folder yang sama), lalu isi kredensial asli.
// db_config.php TIDAK boleh dibagikan/dizip ke orang lain dan sudah diblokir dari akses
// browser langsung lewat api/.htaccess -- sama seperti pola mail_config.php.
return [
    'host' => 'sql309.infinityfree.com',
    'name' => 'if0_42474879_kartu',
    'user' => 'if0_42474879',
    'pass' => 'ganti_dengan_password_baru',
];
