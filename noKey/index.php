<?php

    $requestUri = $_SERVER['REQUEST_URI'];
    if (strpos($requestUri, '.') !== false) { // the ../index.php "issue" seem just normal and can't get file outside online folder
        die('uri forbidden');
    }
    $keysFile = '/var/www/ytPrivate/keys.txt';
    $content = file_get_contents($keysFile);
    $keys = explode("\n", $content);
    $keysCount = count($keys);
    $url = 'https://www.googleapis.com/youtube/v3/' . str_replace('/noKey/', '', $requestUri) . '&key=';
    $options = ['http' => ['ignore_errors' => true]];
    $context = stream_context_create($options);
    /// is there any way someone may get the keys out ? could restrict syntax with the one of the official API but that's not that much clean
    for ($keysIndex = 0; $keysIndex < $keysCount; $keysIndex++) {
        $key = $keys[$keysIndex];
        $realUrl = $url . $key;
        $response = file_get_contents($realUrl, false, $context);
        $response = str_replace($key, '!Please contact Benjamin Loison to tell him how you did that!', $response); // quite good but not perfect
        // no need to check for ip leak
        $json = json_decode($response, true);

        if (array_key_exists('kind', $json)) {
            if ($keysIndex !== 0) {
                $newKeys = array_merge(array_slice($keys, $keysIndex, $keysCount - $keysIndex), array_slice($keys, 0, $keysIndex));
                $toWrite = implode("\n", $newKeys);
                file_put_contents($keysFile, $toWrite);
            }
            die($response);
        } elseif (array_key_exists('error', $json) && $json['error']['errors'][0]['domain'] !== 'youtube.quota') {
            die($response);
        }
    }
    $message = 'The request cannot be completed because the YouTube operational API run out of quota. Please try again later.';
    $errors = [
        'message' => $message,
        'domain' => 'youtube.quota',
        'reason' => 'quotaExceeded'
    ];
    $error = [
        'code' => 403,
        'message' => $message,
        'errors' => [$errors]
    ];
    $json = ['error' => $error];
    die(json_encode($json, JSON_PRETTY_PRINT));
