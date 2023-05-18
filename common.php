<?php

    include_once 'compatibility.php';
    include_once 'constants.php';

    ini_set('display_errors', 0);

    function getContextFromOpts($opts)
    {
        if (GOOGLE_ABUSE_EXEMPTION !== '') {
            $cookieToAdd = 'GOOGLE_ABUSE_EXEMPTION=' . GOOGLE_ABUSE_EXEMPTION;
            // Can't we simplify the following code?
            if (array_key_exists('http', $opts)) {
                $http = $opts['http'];
                if (array_key_exists('header', $http)) {
                    $headers = $http['header'];
                    $isThereACookieHeader = false;
                    foreach ($headers as $headerIndex => $header) {
                        if (str_starts_with($header, 'Cookie: ')) {
                            $opts['http']['header'][$headerIndex] = "$header; $cookieToAdd";
                            $isThereACookieHeader = true;
                            break;
                        }
                    }
                    if (!$isThereACookieHeader) {
                        array_push($opts['http']['header'], "Cookie: $cookieToAdd");
                    }
                }
            } else {
                $opts = [
                    'http' => [
                        'header' => [
                            "Cookie: $cookieToAdd"
                        ]
                    ]
                ];
            }
        }
        $context = stream_context_create($opts);
        return $context;
    }

    function getHeadersFromOpts($url, $opts)
    {
        $context = getContextFromOpts($opts);
        $headers = get_headers($url, false, $context);
        return $headers;
    }

    function fileGetContentsAndHeadersFromOpts($url, $opts)
    {
        $context = getContextFromOpts($opts);
        $result = file_get_contents($url, false, $context);
        return [$result, $http_response_header];
    }

    function isRedirection($url)
    {
        $opts = [
            'http' => [
                'ignore_errors' => true
            ]
        ];
        $http_response_header = getHeadersFromOpts($url, $opts);
        $code = intval(explode(' ', $http_response_header[0])[1]);
        if ($code == HTTP_CODE_DETECTED_AS_SENDING_UNUSUAL_TRAFFIC) {
            detectedAsSendingUnusualTraffic();
        }
        return $code == 303;
    }

    function getRemote($url, $opts = [])
    {
        [$result, $headers] = fileGetContentsAndHeadersFromOpts($url, $opts);
        if (str_contains($headers[0], strval(HTTP_CODE_DETECTED_AS_SENDING_UNUSUAL_TRAFFIC))) {
            detectedAsSendingUnusualTraffic();
        }
        return $result;
    }

    function dieWithJsonMessage($message)
    {
        $error = [
            'code' => 400,
            'message' => $message
        ];
        $result = [
            'error' => $error
        ];
        die(json_encode($result, JSON_PRETTY_PRINT));
    }

    function detectedAsSendingUnusualTraffic()
    {
        dieWithJsonMessage('YouTube has detected unusual traffic from this YouTube operational API instance. Please try your request again later or see alternatives at https://github.com/Benjamin-Loison/YouTube-operational-API/issues/11');
    }

    function getJSON($url, $opts = [])
    {
        return json_decode(getRemote($url, $opts), true);
    }

    function getJSONStringFromHTMLScriptPrefix($html, $scriptPrefix)
    {
        $html = explode(';</script>', explode("\">$scriptPrefix", $html, 3)[1], 2)[0];
        return $html;
    }

    function getJSONFromHTMLScriptPrefix($html, $scriptPrefix)
    {
        $html = getJSONStringFromHTMLScriptPrefix($html, $scriptPrefix);
        return json_decode($html, true);
    }

    function getJSONStringFromHTML($html, $scriptVariable = '', $prefix = 'var ')
    {
        // don't use as default variable because getJSONFromHTML call this function with empty string
        if ($scriptVariable === '') {
            $scriptVariable = 'ytInitialData';
        }
        return getJSONStringFromHTMLScriptPrefix($html, "$prefix$scriptVariable = ");
    }

    function getJSONFromHTML($url, $opts = [], $scriptVariable = '', $prefix = 'var ', $forceLanguage = false, $verifiesChannelRedirection = false)
    {
        $html = getRemote($url, $opts);
        $jsonStr = getJSONStringFromHTML($html, $scriptVariable, $prefix);
        $json = json_decode($jsonStr, true);
        if($verifiesChannelRedirection)
        {
            $redirectedToChannelIdPath = 'onResponseReceivedActions/0/navigateAction/endpoint/browseEndpoint/browseId';
            if(doesPathExist($json, $redirectedToChannelIdPath))
            {
                $redirectedToChannelId = getValue($json, $redirectedToChannelIdPath);
                $url = preg_replace('/[a-zA-Z0-9-_]{24}/', $redirectedToChannelId, $url);
                // Does a redirection of redirection for a channel exist?
                return getJSONFromHTML($url, $opts, $scriptVariable, $prefix, $forceLanguage, $verifiesChannelRedirection);
            }
        }
        return $json;
    }

    function getJSONFromHTMLForcingLanguage($url, $verifiesChannelRedirection = false)
    {
        $opts = [
            'http' => [
                'header' => ['Accept-Language: en']
            ]
        ];
        return getJSONFromHTML($url, $opts, '', 'var ', false, $verifiesChannelRedirection);
    }

    function checkRegex($regex, $str)
    {
        return preg_match("/^$regex$/", $str) === 1;
    }

    function isContinuationToken($continuationToken)
    {
        return checkRegex('[A-Za-z0-9=\-_]+', $continuationToken);
    }

    function isContinuationTokenAndVisitorData($continuationTokenAndVisitorData)
    {
        return checkRegex('[A-Za-z0-9=]+,[A-Za-z0-9=\-_]+', $continuationTokenAndVisitorData);
    }

    function isPlaylistId($playlistId)
    {
        return checkRegex('[a-zA-Z0-9-_]+', $playlistId);
    }

    function isCId($cId)
    {
        return checkRegex('[a-zA-Z0-9]+', $cId);
    }

    function isChannelId($channelId)
    {
        return checkRegex('[a-zA-Z0-9-_]{24}', $channelId);
    }

    function isVideoId($videoId)
    {
        return checkRegex('[a-zA-Z0-9-_]{11}', $videoId);
    }

    function isHashtag($hashtag)
    {
        return true; // checkRegex('[a-zA-Z0-9_]+', $hashtag); // 'é' is a valid hashtag for instance
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
        return checkRegex('Ug[a-zA-Z0-9-_]{34}', $clipId);
    }

    function isEventType($eventType)
    {
        return in_array($eventType, ['completed', 'live', 'upcoming']);
    }

    function isPositiveInteger($s)
    {
        return preg_match("/^\d+$/", $s);
    }

    function isYouTubeDataAPIV3Key($youtubeDataAPIV3Key)
    {
        return checkRegex('AIzaSy[A-D][a-zA-Z0-9-_]{32}', $youtubeDataAPIV3Key);
    }

    function isHandle($handle)
    {
        return checkRegex('@[a-zA-Z0-9-_.]{3,}', $handle);
    }

    function isPostId($postId)
    {
        return (checkRegex('Ug[w-z][a-zA-Z0-9-_]{16}4AaABCQ', $postId) || checkRegex('Ugkx[a-zA-Z0-9-_]{32}', $postId));
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

    function getIntValue($unitCount, $unit = '')
    {
        $unitCount = str_replace(" {$unit}s", '', $unitCount);
        $unitCount = str_replace(" $unit", '', $unitCount);
        $unitCount = str_replace('K', '*1000', $unitCount);
        $unitCount = str_replace('M', '*1000000', $unitCount);
        if(checkRegex('[0-9.*KM]+', $unitCount)) {
            $unitCount = eval("return round($unitCount);");
        }
        return $unitCount;
    }

    function getCommunityPostFromContent($content)
    {
        $backstagePost = $content['backstagePostThreadRenderer']['post']; // for posts that are shared from other channels
        $common = array_key_exists('backstagePostRenderer', $backstagePost) ? $backstagePost['backstagePostRenderer'] : $backstagePost['sharedPostRenderer'];

        $id = $common['postId'];
        $channelId = $common['publishedTimeText']['runs'][0]['navigationEndpoint']['browseEndpoint']['browseId'];

        // Except for `Image`, all other posts require text.
        $contentText = [];
        $textContent = array_key_exists('contentText', $common) ? $common['contentText'] : $common['content']; // sharedPosts have the same content just in slightly different positioning
        foreach ($textContent['runs'] as $textCommon) {
            $contentTextItem = ['text' => $textCommon['text']];
            if (array_key_exists('navigationEndpoint', $textCommon)) {
                // `$url` isn't defined.
                if (str_starts_with($url, 'https://www.youtube.com/redirect?')) {
                    // `$text` isn't defined here.
                    $contentTextItem['url'] = $text;
                } else {
                    $navigationEndpoint = $textCommon['navigationEndpoint'];
                    if (array_key_exists('commandMetadata', $navigationEndpoint)) {
                        $url = $navigationEndpoint['commandMetadata']['webCommandMetadata']['url'];
                    } else {
                        $url = $navigationEndpoint['browseEndpoint']['canonicalBaseUrl'];
                    }
                    $contentTextItem['url'] = "https://www.youtube.com$url";
                }
            }
            array_push($contentText, $contentTextItem);
        }

        $backstageAttachment = [];
        if (array_key_exists('backstageAttachment', $common)) {
            $backstageAttachment = $common['backstageAttachment'];
        }

        $images = [];
        if (array_key_exists('backstageImageRenderer', $backstageAttachment)) {
            $images = [$backstageAttachment['backstageImageRenderer']['image']];
        } else if (array_key_exists('postMultiImageRenderer', $backstageAttachment)) {
            foreach($backstageAttachment['postMultiImageRenderer']['images'] as $image) {
                array_push($images, $image['backstageImageRenderer']['image']);
            }
        }

        $videoId = array_key_exists('videoRenderer', $backstageAttachment) ? $backstageAttachment['videoRenderer']['videoId'] : null;
        $date = $common['publishedTimeText']['runs'][0]['text'];
        $edited = str_ends_with($date, ' (edited)');
        $date = str_replace(' (edited)', '', $date);
        $date = str_replace('shared ', '', $date);
        $sharedPostId = array_key_exists('originalPost', $common) ? $common['originalPost']['backstagePostRenderer']['postId'] : null;

        $poll = null;
        if (array_key_exists('pollRenderer', $backstageAttachment)) {
            $pollRenderer = $backstageAttachment['pollRenderer'];
            $choices = [];
            foreach ($pollRenderer['choices'] as $choice) {
                $returnedChoice = $choice['text']['runs'][0];
                $returnedChoice['image'] = $choice['image'];
                array_push($choices, $returnedChoice);
            }
            $totalVotesStr = $pollRenderer['totalVotes']['simpleText'];
            // What if no vote? Note that haven't seen a poll with a single vote.
            $totalVotes = intval(str_replace(' vote', '', str_replace(' votes', '', $totalVotesStr)));
            $poll = [
                'choices' => $choices,
                'totalVotes' => $totalVotes
            ];
        }

        $likes = intval(array_key_exists('voteCount', $common) ? $common['voteCount']['simpleText'] : 0);

        // Retrieving comments when using `community?part=snippet` requires another HTTPS request to `browse` YouTube UI endpoint.
        // sharedPosts do not have 'actionButtons' so this next line will end up defaulting to 0 $comments
        $commentsPath = 'actionButtons/commentActionButtonsRenderer/replyButton/buttonRenderer';
        $commentsCommon = doesPathExist($common, $commentsPath) ? getValue($common, $commentsPath) : $common;
        $commentsCount = array_key_exists('text', $commentsCommon) ? intval($commentsCommon['text']['simpleText']) : 0;

        $post = [
            'id' => $id,
            'channelId' => $channelId,
            'date' => $date,
            'contentText' => $contentText,
            'likes' => $likes,
            'commentsCount' => $commentsCount,
            'videoId' => $videoId,
            'images' => $images,
            'poll' => $poll,
            'edited' => $edited,
            'sharedPostId' => $sharedPostId,
        ];
        return $post;
    }

    function getIntFromViewCount($viewCount)
    {
        if ($viewCount === 'No views') {
            $viewCount = 0;
        } else {
            foreach([',', ' views', 'view'] as $toRemove) {
                $viewCount = str_replace($toRemove, '', $viewCount);
            }
        } // don't know if the 1 view case is useful
        $viewCount = intval($viewCount);
        return $viewCount;
    }

    function getIntFromDuration($timeStr)
    {
        $isNegative = $timeStr[0] === '-';
        if ($isNegative) {
            $timeStr = substr($timeStr, 1);
        }
        $format = 'j:H:i:s';
        $timeParts = explode(':', $timeStr);
        $timePartsCount = count($timeParts);
        $minutes = $timeParts[$timePartsCount - 2];
        $timeParts[$timePartsCount - 2] = strlen($minutes) == 1 ? "0$minutes" : $minutes;
        $timeStr = implode(':', $timeParts);
        for ($timePartsIndex = 0; $timePartsIndex < 4 - $timePartsCount; $timePartsIndex++) {
            $timeStr = "00:$timeStr";
        }
        while (date_parse_from_format($format, $timeStr) === false) {
            $format = substr($format, 2);
        }
        $timeComponents = date_parse_from_format($format, $timeStr);
        $timeInt = $timeComponents['day'] * (3600 * 24) +
                   $timeComponents['hour'] * 3600 +
                   $timeComponents['minute'] * 60 +
                   $timeComponents['second'];
        return ($isNegative ? -1 : 1) * $timeInt;
    }

    function getTabByName($result, $tabName) {
        if (array_key_exists('contents', $result)) {
            return array_values(array_filter($result['contents']['twoColumnBrowseResultsRenderer']['tabs'], fn($tab) => (array_key_exists('tabRenderer', $tab) && $tab['tabRenderer']['title'] === $tabName)))[0];
        } else {
            return null;
        }
    }

?>
