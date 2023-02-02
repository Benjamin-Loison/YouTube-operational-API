<?php

    include_once 'common.php';

    if (isset($_GET['key'])) {
        $key = $_GET['key'];
        // Regex-based filter.
        if (isYouTubeDataAPIV3Key($key)) {
            $keysContent = file_get_contents(KEYS_FILE);
            $keys = explode("\n", $keysContent);
            // Verify that the YouTube Data API v3 key isn't already stored by the instance.
            if (!in_array($key, $keys)) {
                $httpOptions = [
                    'http' => [
                        'ignore_errors' => true,
                    ]
                ];
                $content = getJSON("https://www.googleapis.com/youtube/v3/videos?part=snippet&id=mWdFMNQBcjs&key=$key", $httpOptions);
                if ($content['items'][0]['snippet']['title'] === 'A public video') {
                    file_put_contents(KEYS_FILE, ($keysContent === '' ? '' : "\n") . $key, FILE_APPEND);
                    echo 'YouTube Data API v3 key added.';
                } elseif ($content['error']['errors'][0]['reason'] === 'quotaExceeded') {
                    // As users can set `Queries per minute` quota to 0, we avoid denial-of-service by not considering them.
                    echo 'Not adding YouTube Data API v3 key having quota exceeded.';
                } else {
                    // This YouTube Data API API v3 keys isn't assigned or there is another error.
                    echo 'Incorrect YouTube Data API v3 key.';
                }
            } else {
                echo 'This YouTube Data API v3 key is already in the list.';
            }
        } else {
            echo "The key provided isn't a YouTube Data API v3 key.";
        }
    }
