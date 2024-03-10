<?php

    // Global:
    define('SERVER_NAME', 'my instance');

    // Web-scraping endpoints:
    define('GOOGLE_ABUSE_EXEMPTION', '');
    define('MULTIPLE_IDS_ENABLED', True);

    // No-key endpoint:
    define('KEYS_FILE', 'ytPrivate/keys.txt');
    // Both following entries can be generated with `tr -dc A-Za-z0-9 </dev/urandom | head -c 32 ; echo`.
    define('RESTRICT_USAGE_TO_KEY', '');
    // If not defined, a random value will be used to prevent denial-of-service.
    define('ADD_KEY_FORCE_SECRET', '');
    define('ADD_KEY_TO_INSTANCES', []);

?>
