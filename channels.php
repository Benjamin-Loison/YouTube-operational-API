<?php

    // StackOverflow source: https://stackoverflow.com/a/71067222
    $channelsTests = [['forUsername=FolkartTr', 'items/0/id', 'UCnS--2e1yzQCm5r4ClrMJBg']];

    include_once 'common.php';

    $realOptions = ['status', 'premieres', 'shorts', 'community', 'channels', 'about'];

    // really necessary ?
    foreach ($realOptions as $realOption) {
        $options[$realOption] = false;
    }

    // Forbidding URL with no `part` and using `id` filter is debatable.
    if (isset($_GET['forUsername']) || isset($_GET['id']) || isset($_GET['handle'])) {
        if(isset($_GET['part'])) {
            $part = $_GET['part'];
            $parts = explode(',', $part, count($realOptions));
            foreach ($parts as $part) {
                if (!in_array($part, $realOptions)) {
                    die('invalid part ' . $part);
                } else {
                    $options[$part] = true;
                }
            }
        }
        $id = '';
        if (isset($_GET['forUsername'])) {
            $forUsername = $_GET['forUsername'];
            if (!isUsername($forUsername)) { // what's minimal length ?
                die('invalid forUsername');
            }
            $opts = [
                "http" => [
                    "header" => 'Cookie: CONSENT=YES+'
                ]
            ];
            $result = getJSONFromHTML('https://www.youtube.com/c/' . $forUsername . '/about', $opts);
            $id = $result['header']['c4TabbedHeaderRenderer']['channelId'];
        } else if (isset($_GET['id'])) {
            $id = $_GET['id'];
            if (!isChannelId($id)) {
                die('invalid id'); // could directly die within the function
            }
        } else { // if (isset($_GET['handle']))
            $handle = $_GET['handle'];
            if (!isHandle($handle)) {
                die('invalid handle');
            }
            $result = getJSONFromHTML('https://www.youtube.com/@' . $handle);
            $id = $result['responseContext']['serviceTrackingParams'][0]['params'][6]['value'];
        }
        $continuationToken = '';
        if (isset($_GET['pageToken'])) {
            $continuationToken = $_GET['pageToken'];
            if (($options['shorts'] && !isContinuationTokenAndVisitorData($continuationToken)) || (!$options['shorts'] && !isContinuationToken($continuationToken))) {
                die('invalid continuationToken');
            }
        }
        echo getAPI($id, $continuationToken);
    }

    function getItem($id, $continuationToken)
    {
        global $options;
        $item = [
            'kind' => 'youtube#channel',
            'etag' => 'NotImplemented',
            'id' => $id
        ];
        $continuationTokenProvided = $continuationToken != '';

        if ($options['status']) {
            $http = [
                'header' => [
                    'Accept-Language: en',
                ]
            ];
            $options = [
                'http' => $http
            ];
            $result = getJSONFromHTML('https://www.youtube.com/channel/' . $id, $options);
            $status = $result['alerts'][0]['alertRenderer']['text']['simpleText'];
            $item['status'] = $status;
        }

        if ($options['premieres']) {
            $premieres = [];
            $result = getJSONFromHTML('https://www.youtube.com/channel/' . $id);
            $subItems = $result['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['shelfRenderer']['content']['horizontalListRenderer']['items'];
            foreach ($subItems as $subItem) {
                $subItem = $subItem['gridVideoRenderer'];
                if (array_key_exists('upcomingEventData', $subItem)) {
                    foreach (['navigationEndpoint', 'menu', 'trackingParams', 'thumbnailOverlays'] as $toRemove) {
                        unset($subItem[$toRemove]);
                    }
                    array_push($premieres, $subItem);
                }
            }
            $item['premieres'] = $premieres;
        }

        if ($options['shorts']) {
            if (!$continuationTokenProvided) {
                $http = [
                    'header' => [
                        'Accept-Language: en',
                    ]
                ];

                $options = [
                    'http' => $http
                ];
                $result = getJSONFromHTML('https://www.youtube.com/channel/' . $id . '/shorts', $options);
                $visitorData = $result['responseContext']['webResponseContextExtensionData']['ytConfigData']['visitorData'];
            } else {
                $continuationParts = explode(',', $continuationToken);
                $continuationToken = $continuationParts[0];
                $visitorData = $continuationParts[1];
                $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . MUSIC_VERSION . '"}},"continuation":"' . $continuationToken . '"}';
                $http = [
                    'header' => [
                        'Content-Type: application/json',
                        'X-Goog-EOM-Visitor-Id: ' . $visitorData
                    ],
                    'method' => 'POST',
                    'content' => $rawData
                ];

                $options = [
                    'http' => $http
                ];

                $result = getJSON('https://www.youtube.com/youtubei/v1/browse?key=' . UI_KEY, $options);
            }
            $shorts = [];
            $reelShelfRendererItems = !$continuationTokenProvided ? $result['contents']['twoColumnBrowseResultsRenderer']['tabs'][2]['tabRenderer']['content']['richGridRenderer']['contents'] : $result['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems'];
            foreach($reelShelfRendererItems as $reelShelfRendererItem) {
                if(!array_key_exists('richItemRenderer', $reelShelfRendererItem))
                    continue;
                $reelShelfRendererItem = $reelShelfRendererItem['richItemRenderer']['content'];
                $reelItemRenderer = $reelShelfRendererItem['reelItemRenderer'];
                $reelPlayerRenderer = $reelItemRenderer['navigationEndpoint']['reelWatchEndpoint']['overlay']['reelPlayerOverlayRenderer']['reelPlayerHeaderSupportedRenderers']['reelPlayerHeaderRenderer'];
                $viewCount = getIntValue($reelItemRenderer['viewCountText']['simpleText'], 'view');
                $short = [
                    'videoId' => $reelItemRenderer['videoId'],
                    'title' => $reelItemRenderer['headline']['simpleText'],
                    'thumbnails' => $reelItemRenderer['thumbnail']['thumbnails'],
                    'viewCount' => $viewCount,
                    'viewCountText' => $reelItemRenderer['viewCountText']['simpleText'],
                    'timestamp' => $reelPlayerRenderer['timestampText']['simpleText'],
                    'channelTitle' => $reelPlayerRenderer['channelTitleText']['runs'][0]['text'],
                ];
                array_push($shorts, $short);
            }
            $item['shorts'] = $shorts;
            if($reelShelfRendererItems != null && count($reelShelfRendererItems) > 48)
                $item['nextPageToken'] = str_replace('%3D', '=', $reelShelfRendererItems[48]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'] . ',' . $visitorData);
        }

        if ($options['community']) {
            if (!$continuationTokenProvided) {
                $http = [
                    'header' => ['Accept-Language: en']
                ];

                $options = [
                    'http' => $http
                ];

                $result = getJSONFromHTML('https://www.youtube.com/channel/' . $id . '/community', $options);
            } else {
                $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . MUSIC_VERSION . '"}},"continuation":"' . $continuationToken . '"}';
                $http = [
                    'header' => ['Content-Type: application/json'],
                    'method' => 'POST',
                    'content' => $rawData
                ];

                $options = [
                    'http' => $http
                ];

                $result = getJSON('https://www.youtube.com/youtubei/v1/browse?key=' . UI_KEY, $options);
            }
            $community = [];
            $contents = null;
            if (!$continuationTokenProvided) {
                $tabs = $result['contents']['twoColumnBrowseResultsRenderer']['tabs'];
                $path = 'tabRenderer/content/sectionListRenderer/contents/0/itemSectionRenderer/contents';
                foreach (array_slice($tabs, 3, 3) as $tab) {
                    if (doesPathExist($tab, $path)) {
                        $contents = getValue($tab, $path);
                    }
                }
            } else {
                $contents = $result['onResponseReceivedEndpoints'][0]['appendContinuationItemsAction']['continuationItems'];
            }
            foreach ($contents as $content) {
                if (!array_key_exists('backstagePostThreadRenderer', $content)) {
                    continue;
                }
                $common = $content['backstagePostThreadRenderer']['post']['backstagePostRenderer'];

                $id = $common['postId'];

                // I haven't seen any post without any text. Note that I prefer to cover such edge case instead of spending time to find them in the wild.
                $contentText = [];
                foreach ($common['contentText']['runs'] as $textCommon) {
                    $contentTextItem = ['text' => $textCommon['text']];
                    if (array_key_exists('navigationEndpoint', $textCommon)) {
                        $navigationEndpoint = $textCommon['navigationEndpoint'];
                        if (array_key_exists('commandMetadata', $navigationEndpoint)) {
                            $url = $navigationEndpoint['commandMetadata']['webCommandMetadata']['url'];
                        } else {
                            $url = $navigationEndpoint['browseEndpoint']['canonicalBaseUrl'];
                        }
                        $contentTextItem['url'] = 'https://www.youtube.com' . $url;
                    }
                    array_push($contentText, $contentTextItem);
                }

                $backstageAttachment = [];
                if (array_key_exists('backstageAttachment', $common)) {
                    $backstageAttachment = $common['backstageAttachment'];
                }

                $image = null;
                if (array_key_exists('backstageImageRenderer', $backstageAttachment)) {
                    $image = $backstageAttachment['backstageImageRenderer']['image'];
                }

                $videoId = null;
                if (array_key_exists('videoRenderer', $backstageAttachment)) {
                    $videoRenderer = $backstageAttachment['videoRenderer'];
                    $videoId = $videoRenderer['videoId'];
                    $date = $videoRenderer['publishedTimeText']['simpleText'];
                } else {
                    $date = $common['publishedTimeText']['runs'][0]['text'];
                }
                $edited = str_contains($date, 'edited');
                $date = str_replace(' (edited)', '', $date);

                $poll = null;
                if (array_key_exists('pollRenderer', $backstageAttachment)) {
                    $pollRenderer = $backstageAttachment['pollRenderer'];
                    $choices = [];
                    foreach ($pollRenderer['choices'] as $choice) {
                        array_push($choices, $choice['text']['runs'][0]);
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

                $commentsCommon = $common['actionButtons']['commentActionButtonsRenderer']['replyButton']['buttonRenderer'];
                $comments = array_key_exists('text', $commentsCommon) ? intval($commentsCommon['text']['simpleText']) : 0;

                $post = [
                    'id' => $id,
                    'date' => $date,
                    'contentText' => $contentText,
                    'likes' => $likes,
                    'comments' => $comments,
                    'videoId' => $videoId,
                    'image' => $image,
                    'poll' => $poll,
                    'edited' => $edited
                ];
                array_push($community, $post);
            }
            $item['community'] = $community;
            $item['nextPageToken'] = str_replace('%3D', '=', $contents[10]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']);
        }

        if ($options['channels']) {
            $http = [
                'header' => ['Accept-Language: en']
            ];

            $options = [
                'http' => $http
            ];

            $result = getJSONFromHTML('https://www.youtube.com/channel/' . $id . '/channels', $options);
            $sectionListRenderer = array_slice($result['contents']['twoColumnBrowseResultsRenderer']['tabs'], -3)[0]['tabRenderer']['content']['sectionListRenderer'];
            $channels = [];
            $channelsItems = $sectionListRenderer['contents'][0]['itemSectionRenderer']['contents'][0]['gridRenderer']['items'];
            foreach($channelsItems as $channelItem) {
                $gridChannelRenderer = $channelItem['gridChannelRenderer'];
                $thumbnails = [];
                foreach($gridChannelRenderer['thumbnail']['thumbnails'] as $thumbnail) {
                    $thumbnail['url'] = 'https://' . substr($thumbnail['url'], 2);
                    array_push($thumbnails, $thumbnail);
                }
                $subscriberCount = getIntValue($gridChannelRenderer['subscriberCountText']['simpleText'], 'subscriber');
                // Have observed the singular case for the channel: https://www.youtube.com/channel/UCbOoDorgVGd-4vZdIrU4C1A
                $channel = [
                    'channelId' => $gridChannelRenderer['channelId'],
                    'title' => $gridChannelRenderer['title']['simpleText'],
                    'thumbnails' => $thumbnails,
                    'videoCount' => intval(str_replace(',', '', $gridChannelRenderer['videoCountText']['runs'][0]['text'])),
                    'subscriberCount' => $subscriberCount
                ];
                array_push($channels, $channel);
            }
            $channels = [
                'paratext' => $sectionListRenderer['subMenu']['channelSubMenuRenderer']['contentTypeSubMenuItems'][0]['title'],
                'channels' => $channels
            ];
            $item['channels'] = $channels;
        }

        if ($options['about']) {
            $http = [
                'header' => ['Accept-Language: en']
            ];

            $options = [
                'http' => $http
            ];

            $result = getJSONFromHTML('https://www.youtube.com/channel/' . $id . '/about', $options);

            $resultCommon = array_slice($result['contents']['twoColumnBrowseResultsRenderer']['tabs'], -2)[0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['channelAboutFullMetadataRenderer'];

            $stats = [];

            $stats['joinedDate'] = strtotime($resultCommon['joinedDateText']['runs'][1]['text']);

            $viewCount = $resultCommon['viewCountText']['simpleText'];
            // Could try to find a YouTube channel with a single view to make sure it displays "view" and not "views".
            $viewCount = str_replace(' view', '', str_replace(' views', '', str_replace(',', '', $viewCount)));
            $stats['viewCount'] = $viewCount;

            $about['stats'] = $stats;

            $description = $resultCommon['description']['simpleText'];
            $title = $resultCommon['title']['simpleText'];
            $about['description'] = $description;
            $about['title'] = $title;

            $details = [];
            $details['location'] = $resultCommon['country']['simpleText'];
            $about['details'] = $details;

            $linksObjects = $resultCommon['primaryLinks'];
            $links = [];
            foreach ($linksObjects as $linkObject) {
                $link = [];
                $urlComponents = parse_url($linkObject['navigationEndpoint']['urlEndpoint']['url']);
                parse_str($urlComponents['query'], $params);
                $link['url'] = $params['q'];
                $link['thumbnail'] = $linkObject['icon']['thumbnails'][0]['url'];
                $link['title'] = $linkObject['title']['simpleText'];
                array_push($links, $link);
            }
            $about['links'] = $links;

            $item['about'] = $about;
        }

        return $item;
    }

    function getAPI($id, $continuationToken)
    {
        $items = [];
        array_push($items, getItem($id, $continuationToken));

        $answer = [
            'kind' => 'youtube#channelListResponse',
            'etag' => 'NotImplemented',
            'items' => $items
        ];
        // should add in some way the pageInfo ?

        return json_encode($answer, JSON_PRETTY_PRINT);
    }
