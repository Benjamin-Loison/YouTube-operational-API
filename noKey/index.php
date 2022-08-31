<?php

    include_once '../common.php';

    $requestUri = $_SERVER['REQUEST_URI'];
    if (strpos($requestUri, '.') !== false) { // the ../index.php "issue" seem just normal and can't get file outside online folder
        die('URI forbidden');
    }
    $keysFile = '/var/www/ytPrivate/keys.txt';
    $content = file_get_contents($keysFile);
    $keys = explode("\n", $content);
    $keysCount = count($keys);
    $url = 'https://www.googleapis.com/youtube/v3/' . str_replace('/noKey/', '', $requestUri) . '&key=';
    $options = ['http' => ['ignore_errors' => true]];
    $context = stream_context_create($options);
    /// is there any way someone may get the keys out ? could restrict syntax with the one of the official API but that's not that much clean
    // Tries to proceed to the request with an API key and if running out of quota, then use for this and following requests the API key used the longest time ago.
    for ($keysIndex = 0; $keysIndex < $keysCount; $keysIndex++) {
        $key = $keys[$keysIndex];
        $realUrl = $url . $key;
        $response = file_get_contents($realUrl, false, $context);
        $response = str_replace($key, '!Please contact Benjamin Loison to tell him how you did that!', $response); // quite good but not perfect
        // no need to check for ip leak
        $json = json_decode($response, true);

        if (array_key_exists('kind', $json)) {
            if ($keysIndex !== 0) {
                // As the request is successful with this API key, prioritize this key and all the following ones over the first ones.
                $newKeys = array_merge(array_slice($keys, $keysIndex), array_slice($keys, 0, $keysIndex));
                $toWrite = implode("\n", $newKeys);
                file_put_contents($keysFile, $toWrite);
            }
            // Returns the proceeded response to the end-user.
            die($response);
        } elseif (array_key_exists('error', $json) && $json['error']['errors'][0]['domain'] !== 'youtube.quota') {
            $message = $json['error']['message'];
            if ($message === 'API key expired. Please renew the API key.' or str_ends_with($message, 'has been suspended.')) {
                // Removes this API key as it won't be useful anymore.
                $newKeys = array_merge(array_slice($keys, $keysIndex + 1), array_slice($keys, 0, $keysIndex));
                $toWrite = implode("\n", $newKeys);
                file_put_contents($keysFile, $toWrite);
                // Skips to next API key.
                // Decrements `keysIndex` as it will be incremented due to `continue`.
                $keysIndex -= 1;
                $keysCount -= 1;
                $keys = $newKeys;
                continue;
            }
            // If such an error occur, returns it to the end-user, as made exceptions for out of quota and expired keys, should also consider transient, backend and suspension errors.
            // As managed in YouTube-comments-graph: https://github.com/Benjamin-Loison/YouTube-comments-graph/blob/993429770417bdfa4fdf176c473ff1bfe7ed21ae/CPP/main.cpp#L55-L60
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
