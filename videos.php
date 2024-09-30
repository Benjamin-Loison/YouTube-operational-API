<?php

    header('Content-Type: application/json; charset=UTF-8');

    $videosTests = [
        ['part=id&clipId=UgkxU2HSeGL_NvmDJ-nQJrlLwllwMDBdGZFs', 'items/0/videoId', 'NiXD4xVJM5Y'],
        ['part=clip&clipId=UgkxU2HSeGL_NvmDJ-nQJrlLwllwMDBdGZFs', 'items/0/clip', json_decode(file_get_contents('tests/part=clip&clipId=UgkxU2HSeGL_NvmDJ-nQJrlLwllwMDBdGZFs.json'), true)],
        ['part=contentDetails&id=g5xNzUA5Qf8', 'items/0/contentDetails/duration', 213],
        ['part=status&id=J8ZVxDK11Jo', 'items/0/status/embeddable', false],
        ['part=status&id=g5xNzUA5Qf8', 'items/0/status/embeddable', true], // could allow subarray for JSON check in response that way in a single request can check several features
        ['part=short&id=NiXD4xVJM5Y', 'items/0/short/available', false],
        ['part=short&id=ydPkyvWtmg4', 'items/0/short/available', true],
        ['part=musics&id=DUT5rEU6pqM', 'items/0/musics/0', json_decode(file_get_contents('tests/part=musics&id=DUT5rEU6pqM.json'), true)],
        ['part=musics&id=4sC3mbkP_x8', 'items/0/musics', json_decode(file_get_contents('tests/part=musics&id=4sC3mbkP_x8.json'), true)],
        ['part=music&id=FliCdfxdtTI', 'items/0/music/available', false],
        ['part=music&id=ntG3GQdY_Ok', 'items/0/music/available', true],
        ['part=isPaidPromotion&id=Q6gtj1ynstU', 'items/0/isPaidPromotion', false],
        ['part=isPaidPromotion&id=PEorJqo2Qaw', 'items/0/isPaidPromotion', true],
        ['part=isMemberOnly&id=Q6gtj1ynstU', 'items/0/isMemberOnly', false],
        ['part=isMemberOnly&id=Ln9yZDtfcWg', 'items/0/isMemberOnly', true],
        ['part=qualities&id=IkXH9H2ofa0', 'items/0/qualities', json_decode(file_get_contents('tests/part=qualities&id=IkXH9H2ofa0.json'), true)],
        ['part=chapters&id=n8vmXvoVjZw', 'items/0/chapters', json_decode(file_get_contents('tests/part=chapters&id=n8vmXvoVjZw.json'), true)],
        ['part=isOriginal&id=FliCdfxdtTI', 'items/0/isOriginal', false],
        ['part=isOriginal&id=iqKdEhx-dD4', 'items/0/isOriginal', true],
        ['part=isPremium&id=FliCdfxdtTI', 'items/0/isPremium', false],
        ['part=isPremium&id=dNJMI92NZJ0', 'items/0/isPremium', true],
        ['part=isRestricted&id=IkXH9H2ofa0', 'items/0/isRestricted', false],
        ['part=isRestricted&id=ORdWE_ffirg', 'items/0/isRestricted', true],
        ['part=snippet&id=IkXH9H2ofa0', 'items/0/snippet', json_decode(file_get_contents('tests/part=snippet&id=IkXH9H2ofa0.json'), true)],
        ['part=activity&id=V6z0qF54RZ4', 'items/0/activity', json_decode(file_get_contents('tests/part=activity&id=V6z0qF54RZ4.json'), true)],
        ['part=mostReplayed&id=XiCrniLQGYc', 'items/0/mostReplayed/markers/0/intensityScoreNormalized', 1],
        ['part=explicitLyrics&id=Ehoe35hTbuY', 'items/0/explicitLyrics', false],
        ['part=explicitLyrics&id=PvM79DJ2PmM', 'items/0/explicitLyrics', true],
    ];

    include_once 'common.php';

    $realOptions = [
        'id',
        'status',
        'contentDetails',
        'music',
        'short',
        'impressions',
        'musics',
        'isPaidPromotion',
        'isPremium',
        'isMemberOnly',
        'mostReplayed',
        'qualities',
        'location',
        'chapters',
        'isOriginal',
        'isRestricted',
        'snippet',
        'clip',
        'activity',
        'explicitLyrics',
        'statistics',
    ];

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
        $realIds = getMultipleIds($field);
        foreach ($realIds as $realId) {
            if ((!$isClip && !isVideoId($realId)) && !isClipId($realId)) {
                dieWithJsonMessage("Invalid $field");
            }
        }

        if ($options['impressions'] && (!isset($_GET['SAPISIDHASH']) || !isSAPISIDHASH($_GET['SAPISIDHASH']))) {
            dieWithJsonMessage('Invalid SAPISIDHASH');
        }
        echo getAPI($realIds);
    } else if(!test()) {
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
            $opts = [
                'http' => [
                    'header' => [
                        'Accept-Language: en',
                    ]
                ]
            ];
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id", $opts);
            $musics = [];

            $engagementPanels = $json['engagementPanels'];
            $cardsPath = 'engagementPanelSectionListRenderer/content/structuredDescriptionContentRenderer/items';
            $engagementPanel = getFirstNodeContainingPath($engagementPanels, $cardsPath);
            $cards = getValue($engagementPanel, $cardsPath);
            $cardsPath = 'horizontalCardListRenderer/cards';
            $structuredDescriptionContentRendererItem = getFirstNodeContainingPath($cards, $cardsPath);
            $cards = getValue($structuredDescriptionContentRendererItem, $cardsPath);

            foreach ($cards as $card) {
                $videoAttributeViewModel = $card['videoAttributeViewModel'];

                $music = [
                    'image' => $videoAttributeViewModel['image']['sources'][0]['url'],
                    'videoId' => $videoAttributeViewModel['onTap']['innertubeCommand']['watchEndpoint']['videoId'],
                ];
                $runs = $videoAttributeViewModel['overflowMenuOnTap']['innertubeCommand']['confirmDialogEndpoint']['content']['confirmDialogRenderer']['dialogMessages'][0]['runs'];
                for($runIndex = 0; $runIndex < count($runs); $runIndex += 4)
                {
                    $field = strtolower($runs[$runIndex]['text']);
                    $value = $runs[$runIndex + 2]['text'];
                    $music[$field] = $value;
                }

                array_push($musics, $music);
            }
            $item['musics'] = $musics;
        }

        if(isset($_GET['clipId'])) {
            $json = getJSONFromHTMLForcingLanguage("https://www.youtube.com/clip/$id");
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
                        $clipAttributionRenderer = $engagementPanel['engagementPanelSectionListRenderer']['content']['clipSectionRenderer']['contents'][0]['clipAttributionRenderer'];
                        $createdText = explode(' · ', $clipAttributionRenderer['createdText']['simpleText']);
                        $clip = [
                            'title' => $engagementPanel['engagementPanelSectionListRenderer']['content']['clipSectionRenderer']['contents'][0]['clipAttributionRenderer']['title']['runs'][0]['text'],
                            'startTimeMs' => intval($loopCommand['startTimeMs']),
                            'endTimeMs' => intval($loopCommand['endTimeMs']),
                            'viewCount' => getIntValue($createdText[0], 'view'),
                            'publishedAt' => $createdText[1],
                        ];
                        $item['clip'] = $clip;
                        break;
                    }
                }
            }
        }

        if ($options['isPaidPromotion']) {
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id", scriptVariable: 'ytInitialPlayerResponse');
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
            $commonJsonPath = 'payload/macroMarkersListEntity/markersList';
            $jsonPath = "$commonJsonPath/markersDecoration";
            foreach($mutations as $mutation)
            {
                if(doesPathExist($mutation, $jsonPath))
                {
                    break;
                }
            }
            if(doesPathExist($mutation, $jsonPath))
            {
                $mostReplayed = getValue($mutation, $commonJsonPath);
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
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id", scriptVariable: 'ytInitialPlayerResponse');
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
            foreach ($json['engagementPanels'] as $engagementPanel) {
                if ($engagementPanel['engagementPanelSectionListRenderer']['panelIdentifier'] === 'engagement-panel-macro-markers-description-chapters')
                    break;
            }
            $contents = $engagementPanel['engagementPanelSectionListRenderer']['content']['macroMarkersListRenderer']['contents'];
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
            $json = getJson("https://www.youtube.com/watch?v=$id");
            $isOriginal = doesPathExist($json, 'contents/twoColumnWatchNextResults/results/results/contents/1/videoSecondaryInfoRenderer/metadataRowContainer/metadataRowContainerRenderer/rows/2/metadataRowRenderer/contents/0/simpleText') or str_contains($html, 'xtags=' . urlencode('acont=original'));
            $item['isOriginal'] = $isOriginal;
        }

        if ($options['isRestricted']) {
            $opts = [
                'http' => [
                    'header' => ['Cookie: PREF=f2=8000000'],
                ]
            ];
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id", $opts, 'ytInitialPlayerResponse');
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

        if ($options['statistics']) {
            $json = getJSONFromHTMLForcingLanguage("https://www.youtube.com/watch?v=$id");
            preg_match('/like this video along with ([\d,]+) other people/', $json['contents']['twoColumnWatchNextResults']['results']['results']['contents'][0]['videoPrimaryInfoRenderer']['videoActions']['menuRenderer']['topLevelButtons'][0]['segmentedLikeDislikeButtonViewModel']['likeButtonViewModel']['likeButtonViewModel']['toggleButtonViewModel']['toggleButtonViewModel']['defaultButtonViewModel']['buttonViewModel']['accessibilityText'], $viewCount);
            $statistics = [
                'viewCount' => getIntValue($json['playerOverlays']['playerOverlayRenderer']['videoDetails']['playerOverlayVideoDetailsRenderer']['subtitle']['runs'][2]['text'], 'view'),
                'likeCount' => getIntValue($viewCount[1]),
            ];
            $item['statistics'] = $statistics;
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

        if ($options['explicitLyrics']) {
            $json = getJSONFromHTML("https://www.youtube.com/watch?v=$id");
            $rows = $json['contents']['twoColumnWatchNextResults']['results']['results']['contents'][1]['videoSecondaryInfoRenderer']['metadataRowContainer']['metadataRowContainerRenderer']['rows'];
            $item['explicitLyrics'] = $rows !== null && end($rows)['metadataRowRenderer']['contents'][0]['simpleText'] === 'Explicit lyrics';
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
