<?php

    include_once 'compatibility.php';
    include_once 'constants.php';

    function isRedirection($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code == 302) {
            detectedAsSendingUnusualTraffic();
        }
        return $code == 303;
    }

    function getRemote($url, $opts = [])
    {
        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);
        if ($result === false) {
            $opts = [
                'http' => [
                    'follow_location' => false
                ]
            ];
            $context = stream_context_create($opts);
            $result = file_get_contents($url, false, $context);
            if (str_contains($result, 'https://www.google.com/sorry/index?continue=')) {
                detectedAsSendingUnusualTraffic();
            }
        }
        return $result;
    }

    function detectedAsSendingUnusualTraffic()
    {
        $error = [
            'code' => 400,
            'message' => 'YouTube has detected unusual traffic from this YouTube operational API instance. Please try your request again later or see alternatives at https://github.com/Benjamin-Loison/YouTube-operational-API/issues/11',
        ];
        $result = [
            'error' => $error
        ];
        die(json_encode($result, JSON_PRETTY_PRINT));
    }

    function getJSON($url, $opts = [])
    {
        return json_decode(getRemote($url, $opts), true);
    }

    function getJSONFromHTMLScriptPrefix($html, $scriptPrefix)
    {
        $html = explode(';</script>', explode('">' . $scriptPrefix, $html, 3)[1], 2)[0];
        return json_decode($html, true);
    }

    function getJSONStringFromHTML($html, $scriptVariable = '', $prefix = 'var ')
    {
        // don't use as default variable because getJSONFromHTML call this function with empty string
        if ($scriptVariable === '') {
            $scriptVariable = 'ytInitialData';
        }
        return explode(';</script>', explode('">' . $prefix . $scriptVariable . ' = ', $html, 3)[1], 2)[0];
    }

    function getJSONFromHTML($url, $opts = [], $scriptVariable = '', $prefix = 'var ')
    {
        $html = getRemote($url, $opts);
        $jsonStr = getJSONStringFromHTML($html, $scriptVariable, $prefix);
        return json_decode($jsonStr, true);
    }

    function checkRegex($regex, $str)
    {
        return preg_match('/^' . $regex . '$/', $str) === 1;
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
        return true; // checkRegex('[a-zA-Z0-9_]+', $hashTag); // 'é' is a valid hashtag for instance
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
        return checkRegex('[a-zA-Z0-9-_.]{3,}', $handle);
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

    function getIntValue($unitCount, $unit)
    {
        $unitCount = str_replace(' ' . $unit . 's', '', $unitCount);
        $unitCount = str_replace(' ' . $unit, '', $unitCount);
        $unitCount = str_replace('K', '*1000', $unitCount);
        $unitCount = str_replace('M', '*1000000', $unitCount);
        if(checkRegex('[0-9.*KM]+', $unitCount)) {
            $unitCount = eval('return ' . $unitCount . ';');
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
                if (str_starts_with($url, 'https://www.youtube.com/redirect?')) {
                    $contentTextItem['url'] = $text;
                } else {
                    $navigationEndpoint = $textCommon['navigationEndpoint'];
                    if (array_key_exists('commandMetadata', $navigationEndpoint)) {
                        $url = $navigationEndpoint['commandMetadata']['webCommandMetadata']['url'];
                    } else {
                        $url = $navigationEndpoint['browseEndpoint']['canonicalBaseUrl'];
                    }
                    $contentTextItem['url'] = 'https://www.youtube.com' . $url;
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

        $videoId = null;
        if (array_key_exists('publishedTimeText', $common)) {
            $date = $common['publishedTimeText']['runs'][0]['text'];
        } else {
            $videoRenderer = $backstageAttachment['videoRenderer'];
            $videoId = $videoRenderer['videoId'];
            $date = $videoRenderer['publishedTimeText']['simpleText'];
        }
        $edited = str_ends_with($date, ' (edited)');
        $date = str_replace(' (edited)', '', $date);
        $date = str_replace('shared ', '', $date);
        $sharedPostId = $common['originalPost']['backstagePostRenderer']['postId'];

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

        $likes = intval($common['voteCount']['simpleText']);

        // Retrieving comments when using `community?part=snippet` requires another HTTPS request to `browse` YouTube UI endpoint.
        // sharedPosts do not have 'actionButtons' so this next line will end up defaulting to 0 $comments
        $commentsPath = 'actionButtons/commentActionButtonsRenderer/replyButton/buttonRenderer';
        $commentsCommon = doesPathExist($common, $commentsPath) ? getValue($common, $commentsPath) : $common;
        $comments = array_key_exists('text', $commentsCommon) ? intval($commentsCommon['text']['simpleText']) : 0;

        $post = [
            'id' => $id,
            'channelId' => $channelId,
            'date' => $date,
            'contentText' => $contentText,
            'likes' => $likes,
            'comments' => $comments,
            'videoId' => $videoId,
            'images' => $images,
            'poll' => $poll,
            'edited' => $edited,
            'sharedPostId' => $sharedPostId,
        ];
        return $post;
    }

?>
