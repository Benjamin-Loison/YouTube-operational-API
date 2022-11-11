<?php

    // StackOverflow contentDetails source: https://stackoverflow.com/a/70908689
    // StackOverflow status source: https://stackoverflow.com/a/70894799
    // StackOverflow music source: https://stackoverflow.com/a/71012426
    $videosTests = [['contentDetails&id=g5xNzUA5Qf8', 'items/0/contentDetails/duration', '213'],
                    ['status&id=J8ZVxDK11Jo', 'items/0/status/embeddable', false],
                    ['status&id=g5xNzUA5Qf8', 'items/0/status/embeddable', true], // could allow subarray for JSON check in response likewise in a single request can check several features
                    ['music&id=Xge20AqKSRE', 'items/0/music/available', false],
                    ['music&id=ntG3GQdY_Ok', 'items/0/music/available', true]];

    include_once 'common.php';

    $realOptions = ['id', 'status', 'contentDetails', 'music', 'short', 'impressions', 'containsMusic', 'isPaidPromotion', 'isPremium', 'isMemberOnly', 'mostReplayed', 'qualities', 'location', 'chapters']; // could load index.php from that

    // really necessary ?
    foreach ($realOptions as $realOption) {
        $options[$realOption] = false;
    }

    if (isset($_GET['part']) && (isset($_GET['id']) || isset($_GET['clipId']))) {
        $part = $_GET['part'];
        $parts = explode(',', $part, count($realOptions));
        foreach ($parts as $part) {
            if (!in_array($part, $realOptions)) {
                die('invalid part ' . $part);
            } else {
                $options[$part] = true;
            }
        }

        $isClip = isset($_GET['clipId']);
        $field = $isClip ? 'clipId' : 'id';
        $ids = $_GET[$field];
        $realIds = str_contains($ids, ',') ? explode(',', $ids, 50) : [$ids];
        if (count($realIds) == 0) {
            die('invalid id');
        }
        foreach ($realIds as $realId) {
            if ((!$isClip && !isVideoId($realId)) && !isClipId($realId)) {
                die('invalid ' . $field);
            }
        }

        if ($options['impressions'] && (!isset($_GET['SAPISIDHASH']) || !isSAPISIDHASH($_GET['SAPISIDHASH']))) {
            die('invalid SAPISIDHASH');
        }
        echo getAPI($realIds);
    }

    function getJSONFunc($rawData, $music = false)
    {
        $headers = [
            "Content-Type: application/json",
            'Accept-Language: en'
        ];
        if ($music) {
            array_push($headers, 'Referer: https://music.youtube.com');
        }
        $opts = [
            "http" => [
                "method" => "POST",
                "header" => $headers,
                "content" => $rawData,
            ]
        ];
        return getJSON('https://' . ($music ? 'music' : 'www') . '.youtube.com/youtubei/v1/player?key=' . UI_KEY, $opts);
    }

    function getItem($id)
    {
        global $options;
        $result = '';
        if ($options['status'] || $options['contentDetails']) {
            $rawData = '{"videoId":"' . $id . '","context":{"client":{"clientName":"WEB_EMBEDDED_PLAYER","clientVersion":"' . CLIENT_VERSION . '"}}}';

            $result = getJSONFunc($rawData);
        }

        $item = [
            'kind' => 'youtube#video',
            'etag' => 'NotImplemented',
            'id' => $id
        ];

        if ($options['status']) {
            $status = [
                'embeddable' => $result['playabilityStatus']['status'] === 'OK',
                'removedByTheUploader' => $result['playabilityStatus']['errorScreen']['playerErrorMessageRenderer']['subreason']['runs'][0]['text'] === 'This video has been removed by the uploader'
            ];
            $item['status'] = $status;
        }

        if ($options['contentDetails']) {
            $contentDetails = [
                'duration' => intval($result['videoDetails']['lengthSeconds'])
            ];
            $item['contentDetails'] = $contentDetails;
        }

        if ($options['music']) {
            // music request doesn't provide embeddable info - could not make a request if only music and contentDetails
            $rawData = '{"videoId":"' . $id . '","context":{"client":{"clientName":"WEB_REMIX","clientVersion":"' . CLIENT_VERSION . '"}}}';
            $resultMusic = getJSONFunc($rawData, true);
            $music = [
                'available' => $resultMusic['playabilityStatus']['status'] === "OK"
            ];
            $item['music'] = $music;
        }

        if ($options['short']) {
            $short = [
                'available' => !isRedirection('https://www.youtube.com/shorts/' . $id)
            ];
            $item['short'] = $short;
        }

        if ($options['impressions']) {
            $headers = [
                "x-origin: https://studio.youtube.com",
                "authorization: SAPISIDHASH " . $_GET['SAPISIDHASH'],
                "Content-Type:",
                "cookie: HSID=A4BqSu4moNA0Be1N9; SSID=AA0tycmNyGWo-Z_5v; APISID=a; SAPISID=zRbK-_14V7wIAieP/Ab_wY1sjLVrKQUM2c; SID=HwhYm6rJKOn_3R9oOrTNDJjpHIiq9Uos0F5fv4LPdMRSqyVHA1EDZwbLXo0kuUYAIN_MUQ."
            ];
            $rawData = '{"screenConfig":{"entity":{"videoId":"' . $id . '"}},"desktopState":{"tabId":"ANALYTICS_TAB_ID_REACH"}}';
            $opts = [
                "http" => [
                    "method" => "POST",
                    "header" => $headers,
                    "content" => $rawData,
                ]
            ];
            $json = getJSON('https://studio.youtube.com/youtubei/v1/analytics_data/get_screen?key=' . UI_KEY, $opts);
            $impressions = $json['cards'][0]['keyMetricCardData']['keyMetricTabs'][0]['primaryContent']['total'];
            $item['impressions'] = $impressions;
        }

        if ($options['containsMusic']) {
            $json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id);
            $containsMusic = doesPathExist($json, 'engagementPanels/1/engagementPanelSectionListRenderer/content/structuredDescriptionContentRenderer/items/1/videoDescriptionMusicSectionRenderer');
            $item['containsMusic'] = $containsMusic;
        }

        if ($options['id'] && isset($_GET['clipId'])) {
            $json = getJSONFromHTML('https://www.youtube.com/clip/' . $id);
            $videoId = $json['currentVideoEndpoint']['watchEndpoint']['videoId'];
            $item['videoId'] = $videoId;
        }

        if ($options['isPaidPromotion']) {
            $json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id, [], 'ytInitialPlayerResponse');
            $isPaidPromotion = array_key_exists('paidContentOverlay', $json);
            $item['isPaidPromotion'] = $isPaidPromotion;
        }

        if ($options['isPremium']) {
            $json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id);
            $isPremium = array_key_exists('offerModule', $json['contents']['twoColumnWatchNextResults']['secondaryResults']['secondaryResults']);
            $item['isPremium'] = $isPremium;
        }

        if ($options['isMemberOnly']) {
            $json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id, $opts);
            $isMemberOnly = array_key_exists('badges', $json['contents']['twoColumnWatchNextResults']['results']['results']['contents'][0]['videoPrimaryInfoRenderer']);
            $item['isMemberOnly'] = $isMemberOnly;
        }

        if ($options['mostReplayed']) {
            $json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id);
            $markersMap = $json['playerOverlays']['playerOverlayRenderer']['decoratedPlayerBarRenderer']['decoratedPlayerBarRenderer']['playerBar']['multiMarkersPlayerBarRenderer']['markersMap'];
            foreach ($markersMap as $markerMap) {
                if ($markerMap['key'] == 'HEATSEEKER') {
                    $mostReplayed = $markerMap['value']['heatmap']['heatmapRenderer'];
                }
            }
            // What is `Dp` in `maxHeightDp` and `minHeightDp` ? If not relevant could add ['heatMarkers'] to the JSON path above.
            $item['mostReplayed'] = $mostReplayed;
        }

        if ($options['qualities']) {
            $json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id, [], 'ytInitialPlayerResponse');
            $qualities = [];
            foreach ($json['streamingData']['adaptiveFormats'] as $quality) {
                if (array_key_exists('qualityLabel', $quality)) {
                    $quality = $quality['qualityLabel'];
                    if (!in_array($quality, $qualities)) {
                        array_push($qualities, $quality);
                    }
                }
            }
            $item['qualities'] = $qualities;
        }

        if ($options['location']) {
            $json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id);
            $location = $json['contents']['twoColumnWatchNextResults']['results']['results']['contents'][0]['videoPrimaryInfoRenderer']['superTitleLink']['runs'][0]['text'];
            $item['location'] = $location;
        }

        if ($options['chapters']) {
            $json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id);
            $chapters = [];
            foreach ($json['engagementPanels'][1]['engagementPanelSectionListRenderer']['content']['macroMarkersListRenderer']['contents'] as $chapter) {
                $chapter = $chapter['macroMarkersListItemRenderer'];
                $timeStr = $chapter['timeDescription']['simpleText'];
                $format = 'j:H:i:s';
                $timeParts = explode(':', $timeStr);
                $timePartsCount = count($timeParts);
                $minutes = $timeParts[$timePartsCount - 2];
                $timeParts[$timePartsCount - 2] = strlen($minutes) == 1 ? '0' . $minutes : $minutes;
                $timeStr = implode(':', $timeParts);
                for ($timePartsIndex = 0; $timePartsIndex < 4 - $timePartsCount; $timePartsIndex++) {
                    $timeStr = '00:' . $timeStr;
                }
                while (date_parse_from_format($format, $timeStr) === false) {
                    $format = substr($format, 2);
                }
                $timeComponents = date_parse_from_format($format, $timeStr);
                $timeInt = $timeComponents['day'] * (3600 * 24) +
                           $timeComponents['hour'] * 3600 +
                           $timeComponents['minute'] * 60 +
                           $timeComponents['second'];
                array_push($chapters, [
                    'title' => $chapter['title']['simpleText'],
                    'time' => $timeInt
                ]);
            }
            $item['chapters'] = $chapters;
        }

        return $item;
    }

    function getAPI($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            array_push($items, getItem($id));
        }

        $answer = [
            'kind' => 'youtube#videoListResponse',
            'etag' => 'NotImplemented',
            'items' => $items
        ];

        return json_encode($answer, JSON_PRETTY_PRINT);
    }
