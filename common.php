<?php

    define('DOMAIN_NAME', 'yt.lemnoslife.com');
    define('WEBSITE_URL', 'https://' . DOMAIN_NAME . '/');
    define('SUB_VERSION_STR', '1.9999099');

    define('MUSIC_VERSION', '2.9999099');//SUB_VERSION_STR);
    define('CLIENT_VERSION', '1.9999099');
    define('UI_KEY', 'AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8'); // this isn't a YouTube Data API v3 key

    function isRedirection($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $code == 303;
    }

    function getRemote($url, $opts = [])
    {
        $context = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }

    function getJSON($url, $opts)
    {
        return json_decode(getRemote($url, $opts), true);
    }

    function getJSONFromHTMLScriptPrefix($html, $scriptPrefix)
    {
        $html = explode(';</script>', explode('">' . $scriptPrefix, $html)[1])[0];
        return json_decode($html, true);
    }

    function getJSONStringFromHTML($html, $scriptVariable = '')
    {
        // don't use as default variable because getJSONFromHTML call this function with empty string
        if ($scriptVariable === '') {
            $scriptVariable = 'ytInitialData';
        }
        return explode(';</script>', explode('">var ' . $scriptVariable . ' = ', $html)[1])[0]; // otherwise having troubles with people using ';' in their channel description
    }

    function getJSONFromHTML($url, $opts = [], $scriptVariable = '')
    {
        $res = getRemote($url, $opts);
        $res = getJSONStringFromHTML($res, $scriptVariable);
        return json_decode($res, true);
    }

    function checkRegex($regex, $str)
    {
        return preg_match('/^' . $regex . '$/', $str) === 1;
    }

    function isContinuationToken($continuationToken)
    {
        return checkRegex('[A-Za-z0-9=]+', $continuationToken);
    }

    function isPlaylistId($playlistId)
    {
        return checkRegex('[a-zA-Z0-9-_]+', $playlistId);
    }

    function isUsername($forUsername)
    {
        return checkRegex('[a-zA-Z0-9]+', $forUsername);
    }

    function isChannelId($channelId)
    {
        return checkRegex('[a-zA-Z0-9-_]{24}', $channelId);
    }

    function isVideoId($videoId)
    {
        return checkRegex('[a-zA-Z0-9-_]{11}', $videoId);
    }

    function isHashTag($hashTag)
    {
        return true;//checkRegex('[a-zA-Z0-9_]+', $hashTag); // 'é'is a valid hashtag for instance
    }

    function isSAPISIDHASH($SAPISIDHASH)
    {
        return checkRegex('[1-9][0-9]{9}_[a-f0-9]{40}', $SAPISIDHASH);
    }

    function isQuery($q)
    {
        return true; // should restrain
    }

    function isClipId($clipId)
    {
        return checkRegex('[a-zA-Z0-9-_]{36}', $clipId); // may be more precise
    }

    function isEventType($eventType)
    {
        return in_array($eventType, ['completed', 'live', 'upcoming']);
    }

    function doesPathExist($json, $path)
    {
        $parts = explode('/', $path);
        $partsCount = count($parts);
        if ($partsCount == 1) {
            return array_key_exists($path, $json);
        }
        return array_key_exists($parts[0], $json) && doesPathExist($json[$parts[0]], join('/', array_slice($parts, 1, $partsCount - 1)));
    }

    // assume path checked before
    function getValue($json, $path)
    {
        $parts = explode('/', $path);
        $partsCount = count($parts);
        if ($partsCount == 1) {
            return $json[$path];
        }
        return getValue($json[$parts[0]], join('/', array_slice($parts, 1, $partsCount - 1)));
    }

    function str_contains($haystack, $needle)
    {
        return strpos($haystack, $needle) !== false;
    }

    function str_starts_with($haystack, $needle)
    {
        return strpos($haystack, $needle) === 0;
    }
