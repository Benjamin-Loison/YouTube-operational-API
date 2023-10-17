<?php

    header('Content-Type: application/json; charset=UTF-8');

    // Stack Overflow contentDetails source: https://stackoverflow.com/a/70908689
    // Stack Overflow status source: https://stackoverflow.com/a/70894799
    // Stack Overflow music source: https://stackoverflow.com/a/71012426
    $videosTests = [['contentDetails&id=g5xNzUA5Qf8', 'items/0/contentDetails/duration', '213'],
                    ['status&id=J8ZVxDK11Jo', 'items/0/status/embeddable', false],
                    ['status&id=g5xNzUA5Qf8', 'items/0/status/embeddable', true], // could allow subarray for JSON check in response likewise in a single request can check several features
                    ['music&id=Xge20AqKSRE', 'items/0/music/available', false],
                    ['music&id=ntG3GQdY_Ok', 'items/0/music/available', true]];

    include_once 'common.php';

    $realOptions = ['id', 'status', 'contentDetails', 'music', 'short', 'impressions', 'musics', 'isPaidPromotion', 'isPremium', 'isMemberOnly', 'mostReplayed', 'qualities', 'location', 'chapters', 'isOriginal', 'isRestricted', 'snippet', 'clip', 'activity']; // could load index.php from that

    // really necessary ?
    foreach ($realOptions as $realOption) {
        $options[$realOption] = false;
    }

    if (isset($_GET['part']) && (isset($_GET['id']) || isset($_GET['clipId']))) {
        $part = $_GET['part'];
        $parts = explode(',', $part, count($realOptions));
        foreach ($parts as $part) {
            if (!in_array($part, $realOptions)) {
                dieWithJsonMessage("Invalid part $part");
            } else {
                $options[$part] = true;
            }
        }

        $isClip = isset($_GET['clipId']);
        $field = $isClip ? 'clipId' : 'id';
        $ids = $_GET[$field];
        $realIds = str_contains($ids, ',') ? explode(',', $ids, 50) : [$ids];
        if (count($realIds) == 0) {
            dieWithJsonMessage('Invalid id');
        }
        foreach ($realIds as $realId) {
            if ((!$isClip && !isVideoId($realId)) && !isClipId($realId)) {
                dieWithJsonMessage("Invalid $field");
            }
        }

        if ($options['impressions'] && (!isset($_GET['SAPISIDHASH']) || !isSAPISIDHASH($_GET['SAPISIDHASH']))) {
            dieWithJsonMessage('Invalid SAPISIDHASH');
        }
        echo getAPI($realIds);
    } else {
        dieWithJsonMessage('Required parameters not provided');
    }

    function getJSONFunc($rawData, $music = false)
    {
        $headers = [
            'Content-Type: application/json',
            'Accept-Language: en'
        ];
        if ($music) {
            array_push($headers, 'Referer: https://music.youtube.com');
        }
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => $rawData,
            ]
        ];
        return getJSON('https://' . ($music ? 'music' : 'www') . '.youtube.com/youtubei/v1/player?key=' . UI_KEY, $opts);
    }

    function getItem($id)
    {
        global $options;
        $result = '';
        if ($options['status'] || $options['contentDetails']) {
            $rawData = [
                'videoId' => $id,
                'context' => [
                    'client' => [
                        'clientName' => 'WEB_EMBEDDED_PLAYER',
                        'clientVersion' => CLIENT_VERSION
                    ]
                ]
            ];

            $result = getJSONFunc(json_encode($rawData));
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
            $rawData = [
                'videoId' => $id,
                'context' => [
                    'client' => [
                        'clientName' => 'WEB_REMIX',
                        'clientVersion' => CLIENT_VERSION
                    ]
                ]
            ];
            $resultMusic = getJSONFunc(json_encode($rawData), true);
            $music = [
                'available' => $resultMusic['playabilityStatus']['status'] === 'OK'
            ];
            $item['music'] = $music;
        }

        if ($options['short']) {
            $short = [
                'available' => !isRedirection("https://www.youtube.com/shorts/$id")
            ];
            $item['short'] = $short;
        }

        if ($options['impressions']) {
            $headers = [
                'x-origin: https://studio.youtube.com',
                "authorization: SAPISIDHASH {$_GET['SAPISIDHASH']}",
                'Content-Type:',
                'Cookie: HSID=A4BqSu4moNA0Be1N9; SSID=AA0tycmNyGWo-Z_5v; APISID=a; SAPISID=zRbK-_14V7wIAieP/Ab_wY1sjLVrKQUM2c; SID=HwhYm6rJKOn_3R9oOrTNDJjpHIiq9Uos0F5fv4LPdMRSqyVHA1EDZwbLXo0kuUYAIN_MUQ.'
            ];
            $rawData = [
                'screenConfig' => [
                    'entity' => [
                        'videoId' => $id
                    ]
                ],
                'desktopState' => [
                    'tabId' => 'ANALYTICS_TAB_ID_REACH'
                ]
            ];

            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => $headers,
                    'content' => json_encode($rawData),
                ]
            ];
            $json = getJSON('https://studio.youtube.com/youtubei/v1/analytics_data/get_screen?key=' . UI_KEY, $opts);
            $impressions = $json['cards'][0]['keyMetricCardData']['keyMetricTabs'][0]['primaryContent']['total'];
            $item['impressions'] = $impressions;
        }

        if ($options['musics']) {
            $json = getJSONFromHTMLForcingLanguage("https://www.youtube.com/watch?v=$id");
            $musics = [];

            $engagementPanels = $json['engagementPanels'];
            $carouselLockupsPath = 'engagementPanelSectionListRenderer/content/structuredDescriptionContentRenderer/items/2/videoDescriptionMusicSectionRenderer/carouselLockups';
            $carouselLockupsEngagementPanels1 = getValue($engagementPanels[1], $carouselLockupsPath);
            $multipleMusics = $carouselLockupsEngagementPanels1 !== null ? (count($carouselLockupsEngagementPanels1) > 1) : false;
            $carouselLockups = getValue(($engagementPanels[1]['engagementPanelSectionListRenderer']['panelIdentifier'] === 'engagement-panel-structured-description') ? $engagementPanels[1] : $engagementPanels[2], $carouselLockupsPath);

            foreach ($carouselLockups as $carouselLockup) {
                $carouselLockupRenderer = $carouselLockup['carouselLockupRenderer'];
                $compactVideoRenderer = $carouselLockupRenderer['videoLockup']['compactVideoRenderer'];
                $infoRows = $carouselLockupRenderer['infoRows'];

                $title = $compactVideoRenderer['title'];
                $song = [
                    'title' => $multipleMusics ? (array_key_exists('runs', $title) ? $title['runs'][0]['text'] : $title['simpleText']) : $infoRows[0]['infoRowRenderer']['defaultMetadata']['simpleText'],
                    'videoId' => $compactVideoRenderer['navigationEndpoint']['watchEndpoint']['videoId']
                ];

                $defaultMetadata = $infoRows[$multipleMusics ? 0 : 1]['infoRowRenderer']['defaultMetadata'];
                if ($defaultMetadata !== null && array_key_exists('runs', $defaultMetadata)) {
                    $artistsCommon = $defaultMetadata['runs'][0];
                    $artists = [
                        [
                            'title' => $artistsCommon['text'],
                            'channelId' => $artistsCommon['navigationEndpoint']['browseEndpoint']['browseId']
                        ]
                    ];
                } else {
                    $artists = array_map(function($title) { return ['title' => $title, 'channelId' => null]; }, explode(', ', $defaultMetadata['simpleText']));
                }

                $album = null;
                foreach(array_slice($infoRows, 1, 2) as $infoRow)
                {
                    $infoRowRenderer = $infoRow['infoRowRenderer'];
                    $infoRowTitle = $infoRowRenderer['title']['simpleText'];
                    if ($infoRowTitle === 'ALBUM') {
                        $album = $infoRowRenderer['defaultMetadata']['simpleText'];
                        break;
                    }

                }

                $writers = null;
                foreach(array_slice($infoRows, 1, 3) as $infoRow)
                {
                    $infoRowRenderer = $infoRow['infoRowRenderer'];
                    $infoRowTitle = $infoRowRenderer['title']['simpleText'];
                    if ($infoRowTitle === 'WRITERS') {
                        if (array_key_exists('expandedMetadata', $infoRowRenderer)) {
                            $writers = $infoRowRenderer['expandedMetadata']['runs'];
                            $writers = array_values(array_filter(array_map(function($run) { $text = $run['text']; return $text !== ', ' ? $run['text'] : false; }, $writers)));
                        } else {
                            $writers = [$infoRowRenderer['defaultMetadata']['simpleText']];
                        }
                    }
                }
                $music = [
                    'song' => $song,
                    'artists' => $artists,
                    'album' => $album,
                    'writers' => $writers,
                    'licenses' => end($infoRows)['infoRowRenderer']['expandedMetadata']['simpleText']
                ];
                array_push($musics, $music);
            }
            $item['musics'] = $musics;
        }

        if(isset($_GET['clipId'])) {
            $json = getJSONFromHTML("https://www.youtube.com/clip/$id");
            if ($options['id']) {
                $videoId = $json['currentVideoEndpoint']['watchEndpoint']['videoId'];
                $item['videoId'] = $videoId;
            }
            if ($options['clip']) {
                $engagementPanels = $json['engagementPanels'];
                $path = 'engagementPanelSectionListRenderer/onShowCommands/0/showEngagementPanelScrimAction/onClickCommands/0/commandExecutorCommand/commands/3/openPopupAction/popup/notificationActionRenderer/actionButton/buttonRenderer/command/commandExecutorCommand/commands/1/loopCommand';
                foreach ($engagementPanels as $engagementPanel) {
                    if (doesPathExist($engagementPanel, $path)) {
                        $loopCommand = getValue($engagementPanel, $path);
                        $clip = [
                            'title' => $engagementPanel['engagementPanelSectionListRenderer']['content']['clipSectionRenderer']['contents'][0]['clipAttributionRenderer']['title']['runs'][0]['text'],
                            'startTimeMs' => intval($loopCommand['startTimeMs']),
                            'endTimeMs' => intval($loopCommand['endTimeMs'])
                        ];
                        $item['clip'] = $clip;
                        break;
                    }
                }
            }
        }

        if ($options['isPaidPromotion']) {
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id", [], 'ytInitialPlayerResponse');
            $isPaidPromotion = array_key_exists('paidContentOverlay', $json);
            $item['isPaidPromotion'] = $isPaidPromotion;
        }

        if ($options['isPremium']) {
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id");
            $isPremium = array_key_exists('offerModule', $json['contents']['twoColumnWatchNextResults']['secondaryResults']['secondaryResults']);
            $item['isPremium'] = $isPremium;
        }

        if ($options['isMemberOnly']) {
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id", $opts);
            $isMemberOnly = array_key_exists('badges', $json['contents']['twoColumnWatchNextResults']['results']['results']['contents'][0]['videoPrimaryInfoRenderer']);
            $item['isMemberOnly'] = $isMemberOnly;
        }

        if ($options['mostReplayed']) {
            $json = getJSONFromHTMLForcingLanguage("https://www.youtube.com/watch?v=$id");
            $mutations = $json['frameworkUpdates']['entityBatchUpdate']['mutations'];
            $jsonPath = 'payload/macroMarkersListEntity/markersList';
            foreach($mutations as $mutation)
            {
                if(doesPathExist($mutation, $jsonPath))
                {
                    break;
                }
            }
            if(doesPathExist($mutation, $jsonPath))
            {
                $mostReplayed = getValue($mutation, $jsonPath);
                foreach(array_keys($mostReplayed['markers']) as $markerIndex)
                {
                    unset($mostReplayed['markers'][$markerIndex]['durationMillis']);
                    $mostReplayed['markers'][$markerIndex]['startMillis'] = intval($mostReplayed['markers'][$markerIndex]['startMillis']);
                }
                $timedMarkerDecorations = $mostReplayed['markersDecoration']['timedMarkerDecorations'];
                foreach(array_keys($timedMarkerDecorations) as $timedMarkerDecorationIndex)
                {
                    foreach(['label', 'icon', 'decorationTimeMillis'] as $timedMarkerDecorationKey)
                    {
                        unset($timedMarkerDecorations[$timedMarkerDecorationIndex][$timedMarkerDecorationKey]);
                    }
                }
                $mostReplayed['timedMarkerDecorations'] = $timedMarkerDecorations;
                foreach(['markerType', 'markersMetadata', 'markersDecoration'] as $mostReplayedKey)
                {
                    unset($mostReplayed[$mostReplayedKey]);
                }
            }
            else
            {
                $mostReplayed = null;
            }

            $item['mostReplayed'] = $mostReplayed;
        }

        if ($options['qualities']) {
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id", [], 'ytInitialPlayerResponse');
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
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id");
            $location = $json['contents']['twoColumnWatchNextResults']['results']['results']['contents'][0]['videoPrimaryInfoRenderer']['superTitleLink']['runs'][0]['text'];
            $item['location'] = $location;
        }

        if ($options['chapters']) {
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id");
            $chapters = [];
            $areAutoGenerated = false;
            $contents = $json['engagementPanels'][1]['engagementPanelSectionListRenderer']['content']['macroMarkersListRenderer']['contents'];
            if ($contents !== null) {
                $areAutoGenerated = array_key_exists('macroMarkersInfoItemRenderer', $contents[0]);
                $contents = array_slice($contents, $areAutoGenerated ? 1 : 0);
                foreach ($contents as $chapter) {
                    $chapter = $chapter['macroMarkersListItemRenderer'];
                    $timeInt = getIntFromDuration($chapter['timeDescription']['simpleText']);
                    array_push($chapters, [
                        'title' => $chapter['title']['simpleText'],
                        'time' => $timeInt,
                        'thumbnails' => $chapter['thumbnail']['thumbnails']
                    ]);
                }
            }
            $chapters = [
                'areAutoGenerated' => $areAutoGenerated,
                'chapters' => $chapters
            ];
            $item['chapters'] = $chapters;
        }

        if ($options['isOriginal']) {
            $html = getRemote("https://www.youtube.com/watch?v=$id");
            $jsonStr = getJSONStringFromHTML($html);
            $json = json_decode($jsonStr, true);
            $isOriginal = doesPathExist($json, 'contents/twoColumnWatchNextResults/results/results/contents/1/videoSecondaryInfoRenderer/metadataRowContainer/metadataRowContainerRenderer/rows/2/metadataRowRenderer/contents/0/simpleText');
            if (!$isOriginal) {
                $isOriginal = str_contains($html, 'xtags=acont%3Doriginal');
            }
            $item['isOriginal'] = $isOriginal;
        }

        if ($options['isRestricted']) {
            $opts = [
                'http' => [
                    'header' => ['Cookie: PREF=f2=8000000'],
                ]
            ];
            $html = getRemote("https://www.youtube.com/watch?v=$id", $opts);
            $jsonStr = getJSONStringFromHTML($html, 'ytInitialPlayerResponse');
            $json = json_decode($jsonStr, true);
            $playabilityStatus = $json['playabilityStatus'];
            $isRestricted = array_key_exists('isBlockedInRestrictedMode', $playabilityStatus);
            $item['isRestricted'] = $isRestricted;
        }

        if ($options['snippet']) {
            $json = getJSONFromHTMLForcingLanguage("https://www.youtube.com/watch?v=$id");
            $contents = $json['contents']['twoColumnWatchNextResults']['results']['results']['contents'];
            // Note that `publishedAt` has a day only precision.
            $publishedAt = strtotime($contents[0]['videoPrimaryInfoRenderer']['dateText']['simpleText']);
            $description = $contents[1]['videoSecondaryInfoRenderer']['attributedDescription']['content'];
            $snippet = [
                'publishedAt' => $publishedAt,
                'description' => $description
            ];
            $item['snippet'] = $snippet;
        }

        if ($options['activity']) {
            $json = getJSONFromHTMLForcingLanguage("https://www.youtube.com/watch?v=$id");
            $activity = $json['contents']['twoColumnWatchNextResults']['results']['results']['contents'][1]['videoSecondaryInfoRenderer']['metadataRowContainer']['metadataRowContainerRenderer']['rows'][0]['richMetadataRowRenderer']['contents'][0]['richMetadataRenderer'];
            $name = $activity['title']['simpleText'];
            $year = $activity['subtitle']['simpleText'];
            $thumbnails = $activity['thumbnail']['thumbnails'];
            $channelId = $activity['endpoint']['browseEndpoint']['browseId'];
            $activity = [
                'name' => $name,
                'year' => $year,
                'thumbnails' => $thumbnails,
                'channelId' => $channelId
            ];
            $item['activity'] = $activity;
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
