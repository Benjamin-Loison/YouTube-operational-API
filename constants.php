<?php

    include_once 'configuration.php';

    $newAddKeyForceSecret = ADD_KEY_FORCE_SECRET;
    if (ADD_KEY_FORCE_SECRET === '') {
        $newAddKeyForceSecret = bin2hex(random_bytes(16));
    }
    define('NEW_ADD_KEY_FORCE_SECRET', $newAddKeyForceSecret);
    use const NEW_ADD_KEY_FORCE_SECRET as ADD_KEY_FORCE_SECRET;

    define('DOMAIN_NAME', $_SERVER['SERVER_NAME']);
    $protocol = (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on')) ? 'https' : 'http';
    define('WEBSITE_URL_BASE', "$protocol://{$_SERVER['HTTP_HOST']}");
    define('WEBSITE_URL', WEBSITE_URL_BASE . "{$_SERVER['REQUEST_URI']}");
    define('SUB_VERSION_STR', '.9999099');

    define('MUSIC_VERSION', '2' . SUB_VERSION_STR);
    define('CLIENT_VERSION', '1' . SUB_VERSION_STR);
    define('UI_KEY', 'AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8'); // this isn't a YouTube Data API v3 key
    define('USER_AGENT', 'Firefox/100');
    define('HTTP_CODES_DETECTED_AS_SENDING_UNUSUAL_TRAFFIC', [302, 403, 429]);

?>
