<?php
// db_config.example.php — bunu kopyalayıp db_config.php yap ve bilgilerini gir
// Bu dosya .gitignore'da, repoya gitmez — şifreyi kodda bırakmak istemeyenler için.
return [
    'host' => 'localhost',        // paylaşımlı hostingde genelde localhost
    'name' => 'devicegame',       // cPanel'deki tam DB adı (prefix dahil)
    'user' => 'root',             // cPanel'deki tam kullanıcı adı
    'pass' => '',                 // şifren
    'port' => '3306',
];
