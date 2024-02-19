<?php

    header('Content-Type: application/json; charset=UTF-8');

    chdir('..');

    include_once 'common.php';

    $requestUri = $_SERVER['REQUEST_URI'];
    // As YouTube Data API v3 considers only the first passed `key` parameter if there are multiple of them, providing a first incorrect key convince the no-key service that all its keys are incorrect.
    if(str_contains($requestUri, 'key='))
        dieWithJsonMessage('No YouTube Data API v3 key is required to use the no-key service!');
    if(!file_exists(KEYS_FILE))
       dieWithJsonMessage(KEYS_FILE . ' does not exist!');
    $content = file_get_contents(KEYS_FILE);
    $keys = explode("\n", $content);
    $keysCount = count($keys);
    $parts = explode('/noKey/', $requestUri);
    $url = 'https://www.googleapis.com/youtube/v3/' . end($parts) . '&key=';
    $options = ['http' => ['ignore_errors' => true]];
    $context = stream_context_create($options);

    function myDie($content)
    {
        global $keysCount;
        if(isset($_GET['monitoring']))
        {
            $data = json_decode($content, true);
            $data['monitoring'] = $keysCount;
            $content = json_encode($data, JSON_PRETTY_PRINT);
        }
        die($content);
    }

    /// is there any way someone may get the keys out ? could restrict syntax with the one of the official API but that's not that much clean
    // Tries to proceed to the request with an API key and if running out of quota, then use for this and following requests the API key used the longest time ago.
    for ($keysIndex = 0; $keysIndex < $keysCount; $keysIndex++) {
        $key = $keys[$keysIndex];
        $realUrl = $url . $key;
        $response = file_get_contents($realUrl, false, $context);
        $response = str_replace($key, '!Please contact Benjamin Loison to tell him how you did that!', $response); // quite good but not perfect
        // no need to check for ip leak
        $json = json_decode($response, true);

        if (array_key_exists('error', $json)) {
            $error = $json['error'];
            if ($error['errors'][0]['domain'] !== 'youtube.quota') {
                $message = $error['message'];
                // As there are many different kind of errors other than the quota one, we could just proceed to a test verifying that the expected result is returned, as when adding a key.
                if ($message === 'API key expired. Please renew the API key.' or str_ends_with($message, 'has been suspended.') or $message === 'API key not valid. Please pass a valid API key.' or $message === 'API Key not found. Please pass a valid API key.' or str_starts_with($message, 'YouTube Data API v3 has not been used in project ') or str_ends_with($message, 'are blocked.')) {
                    // Removes this API key as it won't be useful anymore.
                    $newKeys = array_merge(array_slice($keys, $keysIndex + 1), array_slice($keys, 0, $keysIndex));
                    $toWrite = implode("\n", $newKeys);
                    file_put_contents(KEYS_FILE, $toWrite);
                    // Skips to next API key.
                    // Decrements `keysIndex` as it will be incremented due to `continue`.
                    $keysIndex -= 1;
                    $keysCount -= 1;
                    $keys = $newKeys;
                    continue;
                }
                // If such an error occur, returns it to the end-user, as made exceptions for out of quota and expired keys, should also consider transient, backend and suspension errors.
                // As managed in YouTube-comments-graph: https://github.com/Benjamin-Loison/YouTube-comments-graph/blob/993429770417bdfa4fdf176c473ff1bfe7ed21ae/CPP/main.cpp#L55-L60
                myDie($response);
            }
        } else {
            if ($keysIndex !== 0) {
                // As the request is successful with this API key, prioritize this key and all the following ones over the first ones.
                $newKeys = array_merge(array_slice($keys, $keysIndex), array_slice($keys, 0, $keysIndex));
                $toWrite = implode("\n", $newKeys);
                file_put_contents(KEYS_FILE, $toWrite);
            }
            // Returns the proceeded response to the end-user.
            myDie($response);
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
    myDie(json_encode($json, JSON_PRETTY_PRINT));
