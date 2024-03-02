<?php

    include_once 'constants.php';

    ini_set('display_errors', 0);

    if(RESTRICT_USAGE_TO_KEY !== '')
    {
        if(isset($_GET['instanceKey']))
        {
            if($_GET['instanceKey'] !== RESTRICT_USAGE_TO_KEY)
            {
                die("The provided `instanceKey` isn't correct!");
            }
        }
        else
        {
            die('This instance requires that you provide the appropriate `instanceKey` parameter!');
        }
    }

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
        if (in_array($code, HTTP_CODES_DETECTED_AS_SENDING_UNUSUAL_TRAFFIC)) {
            detectedAsSendingUnusualTraffic();
        }
        return $code == 303;
    }

    function getRemote($url, $opts = [], $verifyTrafficIfForbidden = true)
    {
        [$result, $headers] = fileGetContentsAndHeadersFromOpts($url, $opts);
        foreach (HTTP_CODES_DETECTED_AS_SENDING_UNUSUAL_TRAFFIC as $HTTP_CODE_DETECTED_AS_SENDING_UNUSUAL_TRAFFIC) {
            if (str_contains($headers[0], strval($HTTP_CODE_DETECTED_AS_SENDING_UNUSUAL_TRAFFIC)) && ($HTTP_CODE_DETECTED_AS_SENDING_UNUSUAL_TRAFFIC != 403 || $verifyTrafficIfForbidden)) {
                detectedAsSendingUnusualTraffic();
            }
        }
        return $result;
    }

    function dieWithJsonMessage($message, $code = 400)
    {
        $error = [
            'code' => $code,
            'message' => $message
        ];
        $result = [
            'error' => $error
        ];
        die(json_encode($result, JSON_PRETTY_PRINT));
    }

    function detectedAsSendingUnusualTraffic()
    {
        dieWithJsonMessage('YouTube has detected unusual traffic from this YouTube operational API instance. Please try your request again later or see alternatives at https://github.com/Benjamin-Loison/YouTube-operational-API/issues/11', 403);
    }

    function getJSON($url, $opts = [], $verifyTrafficIfForbidden = true)
    {
        return json_decode(getRemote($url, $opts, $verifyTrafficIfForbidden), true);
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
        return getJSONFromHTML($url, $opts, verifiesChannelRedirection: $verifiesChannelRedirection);
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

    function isUsername($username)
    {
        return checkRegex('[a-zA-Z0-9]+', $username);
    }

    function isChannelId($channelId)
    {
        return checkRegex('UC[a-zA-Z0-9-_]{22}', $channelId);
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

    function isCommentId($commentId)
    {
        return checkRegex('Ug[w-z][a-zA-Z0-9-_]{16}4AaABAg', $commentId);
    }

    // Assume `$path !== ''`.
    function doesPathExist($json, $path)
    {
        if ($json === null) {
            return false;
        }
        $parts = explode('/', $path);
        $partsCount = count($parts);
        if ($partsCount == 1) {
            return array_key_exists($path, $json);
        }
        return array_key_exists($parts[0], $json) && doesPathExist($json[$parts[0]], join('/', array_slice($parts, 1, $partsCount - 1)));
    }

    function getValue($json, $path, $defaultPath = null, $defaultValue = null)
    {
        // Alternatively could make a distinct return for `getValue` depending on path found or not to avoid `null` ambiguity.
        if(!doesPathExist($json, $path))
        {
            return $defaultPath !== null ? getValue($json, $defaultPath) : $defaultValue;
        }
        $parts = explode('/', $path);
        $partsCount = count($parts);
        if ($partsCount == 1) {
            return $json[$path];
        }
        $value = getValue($json[$parts[0]], join('/', array_slice($parts, 1, $partsCount - 1)));
        return $value;
    }

    function getIntValue($unitCount, $unit = '')
    {
        $unitCount = str_replace(',', '', $unitCount);
        $unitCount = str_replace(" {$unit}s", '', $unitCount);
        $unitCount = str_replace(" $unit", '', $unitCount);
        if($unitCount === 'No') {
            $unitCount = '0';
        }
        $unitCount = str_replace('K', '*1_000', $unitCount);
        $unitCount = str_replace('M', '*1_000_000', $unitCount);
        $unitCount = str_replace('B', '*1_000_000_000', $unitCount);
        if(checkRegex('[0-9_.*KMB]+', $unitCount)) {
            $unitCount = eval("return round($unitCount);");
        }
        return intval($unitCount);
    }

    function getCommunityPostFromContent($content)
    {
        $backstagePost = $content['backstagePostThreadRenderer']['post']; // for posts that are shared from other channels
        $common = getValue($backstagePost, 'backstagePostRenderer', 'sharedPostRenderer');

        $id = $common['postId'];
        $channelId = $common['publishedTimeText']['runs'][0]['navigationEndpoint']['browseEndpoint']['browseId'];

        // Except for `Image`, all other posts require text.
        $contentText = [];
        $textContent = getValue($common, 'contentText', 'content'); // sharedPosts have the same content just in slightly different positioning
        foreach ($textContent['runs'] as $textCommon) {
            $contentTextItem = ['text' => $textCommon['text']];
            if (array_key_exists('navigationEndpoint', $textCommon)) {
                // `$url` isn't defined.
                if (str_starts_with($url, 'https://www.youtube.com/redirect?')) {
                    // `$text` isn't defined here.
                    $contentTextItem['url'] = $text;
                } else {
                    $navigationEndpoint = $textCommon['navigationEndpoint'];
                    $url = getValue($navigationEndpoint, 'commandMetadata/webCommandMetadata/url', 'browseEndpoint/canonicalBaseUrl');
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

        $videoId = getValue($backstageAttachment, 'videoRenderer/videoId');
        $date = $common['publishedTimeText']['runs'][0]['text'];
        $edited = str_ends_with($date, ' (edited)');
        $date = str_replace(' (edited)', '', $date);
        $date = str_replace('shared ', '', $date);
        $sharedPostId = getValue($common, 'originalPost/backstagePostRenderer/postId');

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

        $likes = getIntValue(getValue($common, 'voteCount/simpleText', defaultValue : 0));

        // Retrieving comments when using `community?part=snippet` requires another HTTPS request to `browse` YouTube UI endpoint.
        // sharedPosts do not have 'actionButtons' so this next line will end up defaulting to 0 $comments
        $commentsPath = 'actionButtons/commentActionButtonsRenderer/replyButton/buttonRenderer';
        $commentsCommon = doesPathExist($common, $commentsPath) ? getValue($common, $commentsPath) : $common;

        $post = [
            'id' => $id,
            'channelId' => $channelId,
            'date' => $date,
            'contentText' => $contentText,
            'likes' => $likes,
            'videoId' => $videoId,
            'images' => $images,
            'poll' => $poll,
            'edited' => $edited,
            'sharedPostId' => $sharedPostId,
        ];
        if(array_key_exists('text', $commentsCommon))
        {
            $commentsCount = getIntValue($commentsCommon['text']['simpleText']);
            $post['commentsCount'] = $commentsCount;
        }
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
            return array_values(array_filter(getTabs($result), fn($tab) => (getValue($tab, 'tabRenderer/title') === $tabName)))[0];
        } else {
            return null;
        }
    }

    function getPublishedAt($publishedAtRaw) {
        $publishedAtStr = str_replace('ago', '', $publishedAtRaw);
        $publishedAtStr = str_replace('seconds', '* 1 +', $publishedAtStr);
        $publishedAtStr = str_replace('second', '* 1 +', $publishedAtStr);
        $publishedAtStr = str_replace('minutes', '* 60 +', $publishedAtStr);
        $publishedAtStr = str_replace('minute', '* 60 +', $publishedAtStr);
        $publishedAtStr = str_replace('hours', '* 3600 +', $publishedAtStr);
        $publishedAtStr = str_replace('hour', '* 3600 +', $publishedAtStr);
        $publishedAtStr = str_replace('days', '* 86400 +', $publishedAtStr);
        $publishedAtStr = str_replace('day', '* 86400 +', $publishedAtStr);
        $publishedAtStr = str_replace('weeks', '* 604800 +', $publishedAtStr);
        $publishedAtStr = str_replace('week', '* 604800 +', $publishedAtStr);
        $publishedAtStr = str_replace('months', '* 2592000 +', $publishedAtStr); // not sure
        $publishedAtStr = str_replace('month', '* 2592000 +', $publishedAtStr);
        $publishedAtStr = str_replace('years', '* 31104000 +', $publishedAtStr); // not sure
        $publishedAtStr = str_replace('year', '* 31104000 +', $publishedAtStr);
        // To remove last ` +`.
        $publishedAtStr = substr($publishedAtStr, 0, strlen($publishedAtStr) - 2);
        $publishedAtStr = str_replace(' ', '', $publishedAtStr); // "security"
        $publishedAtStr = str_replace(',', '', $publishedAtStr);
        $publishedAtStrLen = strlen($publishedAtStr);
        // "security"
        for ($publishedAtStrIndex = $publishedAtStrLen - 1; $publishedAtStrIndex >= 0; $publishedAtStrIndex--) {
            $publishedAtChar = $publishedAtStr[$publishedAtStrIndex];
            if (!str_contains('+*0123456789', $publishedAtChar)) {
                $publishedAtStr = substr($publishedAtStr, $publishedAtStrIndex + 1, $publishedAtStrLen - $publishedAtStrIndex - 1);
                break;
            }
        }
        $publishedAt = time() - eval("return $publishedAtStr;");
        // the time is not perfectly accurate this way
        return $publishedAt;
    }

    function test()
    {
        global $test;
        return isset($test);
    }

    function getContinuationItems($result)
    {
        return $result['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems'];
    }

    function getTabs($result)
    {
        return $result['contents']['twoColumnBrowseResultsRenderer']['tabs'];
    }

    function getContinuationJson($continuationToken)
    {
        $containsVisitorData = str_contains($continuationToken, ',');
        if($containsVisitorData)
        {
            $continuationTokenParts = explode(',', $continuationToken);
            $continuationToken = $continuationTokenParts[0];
        }
        $rawData = [
            'context' => [
                'client' => [
                    'clientName' => 'WEB',
                    'clientVersion' => MUSIC_VERSION
                ]
            ],
            'continuation' => $continuationToken
        ];
        if($containsVisitorData)
        {
            $rawData['context']['client']['visitorData'] = $continuationTokenParts[1];
        }
        $http = [
            'header' => [
                'Content-Type: application/json'
            ],
            'method' => 'POST',
            'content' => json_encode($rawData)
        ];

        $httpOptions = [
            'http' => $http
        ];

        $result = getJSON('https://www.youtube.com/youtubei/v1/browse?key=' . UI_KEY, $httpOptions);
        return $result;
    }

    function verifyMultipleIdsConfiguration($realIds, $field = 'id') {
        if (count($realIds) >= 2 && !MULTIPLE_IDS_ENABLED) {
            dieWithJsonMessage("Multiple ${field}s are disabled on this instance");
        }
    }

?>
