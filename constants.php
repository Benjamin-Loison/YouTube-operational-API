<?php

    define('DOMAIN_NAME', $_SERVER['SERVER_NAME']);
    $protocol = (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on')) ? 'https' : 'http';
    define('WEBSITE_URL', "$protocol://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
    define('SUB_VERSION_STR', '.9999099');
    define('KEYS_FILE', '/var/www/ytPrivate/keys.txt');

    define('MUSIC_VERSION', '2' . SUB_VERSION_STR);
    define('CLIENT_VERSION', '1' . SUB_VERSION_STR);
    define('UI_KEY', 'AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8'); // this isn't a YouTube Data API v3 key
    define('USER_AGENT', 'Firefox/100');

?>
