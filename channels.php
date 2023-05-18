<?php

    header('Content-Type: application/json; charset=UTF-8');

    // StackOverflow source: https://stackoverflow.com/a/71067222
    $channelsTests = [['cId=FolkartTr', 'items/0/id', 'UCnS--2e1yzQCm5r4ClrMJBg']];

    include_once 'common.php';

    $realOptions = ['status', 'upcomingEvents', 'shorts', 'community', 'channels', 'about', 'approval', 'playlists'];

    // really necessary ?
    foreach ($realOptions as $realOption) {
        $options[$realOption] = false;
    }

    // Forbidding URL with no `part` and using `id` filter is debatable.
    if (isset($_GET['cId']) || isset($_GET['id']) || isset($_GET['handle'])) {
        if(isset($_GET['part'])) {
            $part = $_GET['part'];
            $parts = explode(',', $part, count($realOptions));
            foreach ($parts as $part) {
                if (!in_array($part, $realOptions)) {
                    dieWithJsonMessage("Invalid part $part");
                } else {
                    $options[$part] = true;
                }
            }
        }
        $id = '';
        if (isset($_GET['cId'])) {
            $cId = $_GET['cId'];
            if (!isCId($cId)) { // what's minimal length ?
                dieWithJsonMessage('Invalid cId');
            }
            $result = getJSONFromHTML("https://www.youtube.com/c/$cId/about");
            $id = $result['header']['c4TabbedHeaderRenderer']['channelId'];
        } else if (isset($_GET['id'])) {
            $id = $_GET['id'];
            if (!isChannelId($id)) {
                dieWithJsonMessage('Invalid id'); // could directly die within the function
            }
        } else { // if (isset($_GET['handle']))
            $handle = $_GET['handle'];
            if (!isHandle($handle)) {
                dieWithJsonMessage('Invalid handle');
            }
            $result = getJSONFromHTML("https://www.youtube.com/$handle");
            $id = $result['responseContext']['serviceTrackingParams'][0]['params'][6]['value'];
        }
        $continuationToken = '';
        if (isset($_GET['pageToken'])) {
            $continuationToken = $_GET['pageToken'];
            if (($options['shorts'] && !isContinuationTokenAndVisitorData($continuationToken)) || (!$options['shorts'] && !isContinuationToken($continuationToken))) {
                dieWithJsonMessage('Invalid pageToken');
            }
        }
        echo getAPI($id, $continuationToken);
    } else {
        dieWithJsonMessage("Required parameters not provided");
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
            $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/channel/$id", true);
            $status = $result['alerts'][0]['alertRenderer']['text']['simpleText'];
            $item['status'] = $status;
        }

        if ($options['upcomingEvents']) {
            $upcomingEvents = [];
            $result = getJSONFromHTML("https://www.youtube.com/channel/$id", [], '', 'var ', false, true);
            $subItems = $result['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['shelfRenderer']['content']['horizontalListRenderer']['items'];
            foreach ($subItems as $subItem) {
                $path = 'gridVideoRenderer/upcomingEventData';
                if (doesPathExist($subItem, $path)) {
                    $subItem = $subItem['gridVideoRenderer'];
                    foreach (['navigationEndpoint', 'menu', 'trackingParams', 'thumbnailOverlays'] as $toRemove) {
                        unset($subItem[$toRemove]);
                    }
                    array_push($upcomingEvents, $subItem);
                }
            }
            $item['upcomingEvents'] = $upcomingEvents;
        }

        if ($options['shorts']) {
            if (!$continuationTokenProvided) {
                $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/channel/$id/shorts", true);
                $visitorData = $result['responseContext']['webResponseContextExtensionData']['ytConfigData']['visitorData'];
            } else {
                $continuationParts = explode(',', $continuationToken);
                $continuationToken = $continuationParts[0];
                $visitorData = $continuationParts[1];
                $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . MUSIC_VERSION . '"}},"continuation":"' . $continuationToken . '"}';
                $http = [
                    'header' => [
                        'Content-Type: application/json',
                        // Isn't it always the same `$visitorData`?
                        "X-Goog-EOM-Visitor-Id: $visitorData"
                    ],
                    'method' => 'POST',
                    'content' => $rawData
                ];

                $httpOptions = [
                    'http' => $http
                ];

                $result = getJSON('https://www.youtube.com/youtubei/v1/browse?key=' . UI_KEY, $httpOptions);
            }
            $shorts = [];
            if (!$continuationTokenProvided) {
                $tab = getTabByName($result, 'Shorts');
                $tabRenderer = $tab['tabRenderer'];
                $reelShelfRendererItems = $tabRenderer['content']['richGridRenderer']['contents'];
            }
            else {
                $reelShelfRendererItems = $result['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems'];
            }
            foreach($reelShelfRendererItems as $reelShelfRendererItem) {
                if(!array_key_exists('richItemRenderer', $reelShelfRendererItem))
                    continue;
                $reelItemRenderer = $reelShelfRendererItem['richItemRenderer']['content']['reelItemRenderer'];
                $viewCount = getIntValue($reelItemRenderer['viewCountText']['simpleText'], 'view');
                $frame0Thumbnails = $reelItemRenderer['navigationEndpoint']['reelWatchEndpoint']['thumbnail']['thumbnails'];
                $durationParts = explode(' - ', $reelItemRenderer['accessibility']['accessibilityData']['label']);
                end($durationParts);
                // Can be `n seconds` or `1 minute` it seems.
                $duration = prev($durationParts);

                $short = [
                    'videoId' => $reelItemRenderer['videoId'],
                    'title' => $reelItemRenderer['headline']['simpleText'],
                    // Both `sqp` and `rs` parameters are required to crop correctly the thumbnail.
                    'thumbnails' => $reelItemRenderer['thumbnail']['thumbnails'],
                    'viewCount' => $viewCount,
                    'frame0Thumbnails' => $frame0Thumbnails,
                    'duration' => $duration
                ];
                if (!$continuationTokenProvided) {
                    $browseEndpoint = $tabRenderer['endpoint']['browseEndpoint'];
                    $short['channelHandle'] = substr($browseEndpoint['canonicalBaseUrl'], 1);
                    $short['channelId'] = $browseEndpoint['browseId'];
                }
                array_push($shorts, $short);
            }
            $item['shorts'] = $shorts;
            if($reelShelfRendererItems != null && count($reelShelfRendererItems) > 48)
                $item['nextPageToken'] = urldecode($reelShelfRendererItems[48]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'] . ",$visitorData");
        }

        if ($options['community']) {
            if (!$continuationTokenProvided) {
                $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/channel/$id/community", true);
            } else {
                $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . MUSIC_VERSION . '"}},"continuation":"' . $continuationToken . '"}';
                $http = [
                    'header' => ['Content-Type: application/json'],
                    'method' => 'POST',
                    'content' => $rawData
                ];

                $httpOptions = [
                    'http' => $http
                ];

                $result = getJSON('https://www.youtube.com/youtubei/v1/browse?key=' . UI_KEY, $httpOptions);
            }
            $community = [];
            $contents = null;
            if (!$continuationTokenProvided) {
                $tab = getTabByName($result, 'Community');
                $contents = $tab['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'];
            } else {
                $contents = $result['onResponseReceivedEndpoints'][0]['appendContinuationItemsAction']['continuationItems'];
            }
            foreach ($contents as $content) {
                // What is the purpose of this condition?
                if (!array_key_exists('backstagePostThreadRenderer', $content)) {
                    continue;
                }
                $post = getCommunityPostFromContent($content);
                array_push($community, $post);
            }
            $item['community'] = $community;
            if ($contents !== null && array_key_exists('continuationItemRenderer', end($contents))) {
                $item['nextPageToken'] = urldecode(end($contents)['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']);
            }
        }

        if ($options['channels']) {
            if (!$continuationTokenProvided) {
                $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/channel/$id/channels", true);

                $tab = getTabByName($result, 'Channels');
                $sectionListRenderer = $tab['tabRenderer']['content']['sectionListRenderer'];
                $contents = array_map(fn($content) => $content['itemSectionRenderer']['contents'][0], $sectionListRenderer['contents']);
                $itemsArray = [];
                foreach($contents as $content)
                {
                    if (array_key_exists('shelfRenderer', $content)) {
                        $sectionTitle = $content['shelfRenderer']['title']['runs'][0]['text'];
                        $content = $content['shelfRenderer']['content'];
                        $content = array_key_exists('horizontalListRenderer', $content) ? $content['horizontalListRenderer'] : $content['expandedShelfContentsRenderer'];
                    } else {
                        $sectionTitle = $sectionListRenderer['subMenu']['channelSubMenuRenderer']['contentTypeSubMenuItems'][0]['title'];
                        $content = $content['gridRenderer'];
                    }
                    array_push($itemsArray, [$sectionTitle, $content['items']]);
                }
            } else {
                $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . MUSIC_VERSION . '"}},"continuation":"' . $continuationToken . '"}';
                $http = [
                    'header' => [
                        'Content-Type: application/json'
                    ],
                    'method' => 'POST',
                    'content' => $rawData
                ];

                $httpOptions = [
                    'http' => $http
                ];

                $result = getJSON('https://www.youtube.com/youtubei/v1/browse?key=' . UI_KEY, $httpOptions);
                $itemsArray = [[null, $result['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems']]];
            }
            $channelSections = [];
            foreach($itemsArray as [$sectionTitle, $items]) {
                $sectionChannels = [];
                $nextPageToken = null;
                $lastChannelItem = !empty($items) ? end($items) : [];
                $path = 'continuationItemRenderer/continuationEndpoint/continuationCommand/token';
                if (doesPathExist($lastChannelItem, $path)) {
                    $nextPageToken = urldecode(getValue($lastChannelItem, $path));
                    $items = array_slice($items, 0, count($items) - 1);
                }
                foreach($items as $sectionChannelItem) {
                    $gridChannelRenderer = $sectionChannelItem[array_key_exists('gridChannelRenderer', $sectionChannelItem) ? 'gridChannelRenderer' : 'channelRenderer'];
                    // Condition required for channel `UC-1BnotsIsigEK4zLw20IDQ` which doesn't have a `CHANNELS` tab and using the `channel/CHANNEL_ID/channels` URL shows the `HOME` channel tab content.
                    if($gridChannelRenderer === null) {
                        goto breakChannelSectionsTreatment;
                    }
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
                    array_push($sectionChannels, $channel);
                }
                array_push($channelSections, [
                    'title' => $sectionTitle,
                    'sectionChannels' => $sectionChannels,
                    'nextPageToken' => $nextPageToken
                ]);
            }
        breakChannelSectionsTreatment:
            $item['channelSections'] = $channelSections;
        }

        if ($options['about']) {
            $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/channel/$id/about", true);

            $c4TabbedHeaderRenderer = $result['header']['c4TabbedHeaderRenderer'];
            $item['countryChannelId'] = $c4TabbedHeaderRenderer['channelId'];

            $tab = getTabByName($result, 'About');
            $resultCommon = $tab['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['channelAboutFullMetadataRenderer'];

            $stats = [];

            $stats['joinedDate'] = strtotime($resultCommon['joinedDateText']['runs'][1]['text']);

            $viewCount = $resultCommon['viewCountText']['simpleText'];
            // Could try to find a YouTube channel with a single view to make sure it displays "view" and not "views".
            $viewCount = str_replace(' view', '', str_replace(' views', '', str_replace(',', '', $viewCount)));
            $stats['viewCount'] = intval($viewCount);

            $subscriberCount = getIntValue($c4TabbedHeaderRenderer['subscriberCountText']['simpleText'], 'subscriber');
            $stats['subscriberCount'] = $subscriberCount;

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
                $url = $linkObject['navigationEndpoint']['urlEndpoint']['url'];
                $urlComponents = parse_url($url);
                parse_str($urlComponents['query'], $params);
                $link['url'] = array_key_exists('q', $params) ? $params['q'] : $url;
                $link['thumbnail'] = $linkObject['icon']['thumbnails'][0]['url'];
                $link['title'] = $linkObject['title']['simpleText'];
                array_push($links, $link);
            }
            $about['links'] = $links;
            $about['handle'] = $result['header']['c4TabbedHeaderRenderer']['channelHandleText']['runs'][0]['text'];

            $item['about'] = $about;
        }

        if ($options['approval']) {
            $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/channel/$id", true);
            $badgeTooltipPath = 'header/c4TabbedHeaderRenderer/badges/0/metadataBadgeRenderer/tooltip';
            $item['approval'] = doesPathExist($result, $badgeTooltipPath) ? getValue($result, $badgeTooltipPath) : '';
        }

        if ($options['playlists']) {
            if (!$continuationTokenProvided) {
                $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/channel/$id/playlists", true);

                $tab = getTabByName($result, 'Playlists');
                if ($tab === null) {
                    die(returnItems([]));
                }
                $sectionListRenderer = $tab['tabRenderer']['content']['sectionListRenderer'];
                $contents = array_map(fn($content) => $content['itemSectionRenderer']['contents'][0], $sectionListRenderer['contents']);
                $itemsArray = [];
                foreach($contents as $content)
                {
                    if (array_key_exists('shelfRenderer', $content)) {
                        $sectionTitle = $content['shelfRenderer']['title']['runs'][0]['text'];
                        $content = $content['shelfRenderer']['content'];
                        $content = array_key_exists('horizontalListRenderer', $content) ? $content['horizontalListRenderer'] : $content['expandedShelfContentsRenderer'];
                    } else {
                        $sectionTitle = $sectionListRenderer['subMenu']['channelSubMenuRenderer']['contentTypeSubMenuItems'][0]['title'];
                        $content = $content['gridRenderer'];
                    }
                    array_push($itemsArray, [$sectionTitle, $content['items']]);
                }
            } else {
                $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . MUSIC_VERSION . '"}},"continuation":"' . $continuationToken . '"}';
                $http = [
                    'header' => [
                        'Content-Type: application/json'
                    ],
                    'method' => 'POST',
                    'content' => $rawData
                ];

                $httpOptions = [
                    'http' => $http
                ];

                $result = getJSON('https://www.youtube.com/youtubei/v1/browse?key=' . UI_KEY, $httpOptions);
                $itemsArray = [[null, $result['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems']]];
            }

            function getVideoFromItsThumbnails($videoThumbnails, $isVideo = true) {
                $videoThumbnails = $videoThumbnails['thumbnails'];
                // Maybe we can simplify URLs, as we used to as follows, but keep in mind that the resolution or the access may then become incorrect for both kind of thumbnails.
                //$videoThumbnails[0]['url'] = explode('?', $videoThumbnails[0]['url'])[0];
                $videoId = $isVideo ? substr($videoThumbnails[0]['url'], 23, 11) : null;
                return [
                    'id' => $videoId,
                    'thumbnails' => $videoThumbnails
                ];
            }

            // Note that if there is a `Created playlist`, then there isn't any pagination mechanism on YouTube UI.
            // This comment was assuming that they were only `Created playlists` and `Saved playlists`, which isn't the case.

            $c4TabbedHeaderRenderer = $result['header']['c4TabbedHeaderRenderer'];
            $authorChannelName = $c4TabbedHeaderRenderer['title'];
            $authorChannelHandle = $c4TabbedHeaderRenderer['channelHandleText']['runs'][0]['text'];
            $authorChannelApproval = $c4TabbedHeaderRenderer['badges'][0]['metadataBadgeRenderer']['tooltip'];

            $playlistSections = [];
            foreach($itemsArray as [$sectionTitle, $items]) {
                // Note that empty playlists aren't listed at all.
                $sectionPlaylists = [];
                $path = 'continuationItemRenderer/continuationEndpoint/continuationCommand/token';
                $lastItem = !empty($items) ? end($items) : [];
                if (doesPathExist($lastItem, $path)) {
                    $nextPageToken = getValue($lastItem, $path);
                    $items = array_slice($items, 0, count($items) - 1);
                }
                $isCreatedPlaylists = $sectionTitle === 'Created playlists';
                foreach($items as $sectionPlaylistItem) {
                    if (array_key_exists('showRenderer', $sectionPlaylistItem)) {
                        continue;
                    }

                    $playlistRenderer = array_key_exists('gridPlaylistRenderer', $sectionPlaylistItem) ? $sectionPlaylistItem['gridPlaylistRenderer'] : (array_key_exists('playlistRenderer', $sectionPlaylistItem) ? $sectionPlaylistItem['playlistRenderer'] : $sectionPlaylistItem['gridShowRenderer']);
                    $runs = $playlistRenderer['shortBylineText']['runs'];
                    if ($isCreatedPlaylists) {
                        $runs = [null];
                    }
                    $authors = !empty($runs) ? array_values(array_filter(array_map(function($shortBylineRun) use ($isCreatedPlaylists, $authorChannelName, $authorChannelHandle, $id, $authorChannelApproval) {
                        $shortBylineNavigationEndpoint = $shortBylineRun['navigationEndpoint'];
                        $channelHandle = $shortBylineNavigationEndpoint['commandMetadata']['webCommandMetadata']['url'];
                        return [
                            // The following fields `channel*` are `null` without additional code for the `Created playlists` section if there are multiple videos in this section.
                            'channelName' => $isCreatedPlaylists ? $authorChannelName : $shortBylineRun['text'],
                            'channelHandle' => $isCreatedPlaylists ? $authorChannelHandle : (str_starts_with($channelHandle, "/@") ? substr($channelHandle, 1) : null),
                            'channelId' => $isCreatedPlaylists ? $id : $shortBylineNavigationEndpoint['browseEndpoint']['browseId'],
                            'channelApproval' => $isCreatedPlaylists ? $authorChannelApproval : $playlistRenderer['ownerBadges'][0]['metadataBadgeRenderer']['tooltip'],
                        ];
                    }, $runs), fn($author) => $author['channelName'] !== ', ')) : [];

                    $thumbnailRenderer = $playlistRenderer['thumbnailRenderer'];
                    // For unknown reasons, the playlist `OLAK5uy_ku1ocdOmuBzWb3XrtrAQglseslpye5eIw` has achieved to have a custom thumbnail according to YouTube UI source code.
                    // The playlist `Playlist with a thumbnail different than the first video one` on https://www.youtube.com/@anothertestagain5569/playlists isn't detected as using a custom thumbnail.
                    $isThumbnailAVideo = $thumbnailRenderer === null || array_key_exists('playlistVideoThumbnailRenderer', $thumbnailRenderer);
                    $thumbnailRendererField = 'playlist' . ($isThumbnailAVideo ? 'Video' : 'Custom') . 'ThumbnailRenderer';
                    if (!array_key_exists($thumbnailRendererField, $thumbnailRenderer)) {
                        $thumbnailRendererField = 'showCustomThumbnailRenderer';
                    }
                    $thumbnailVideo = getVideoFromItsThumbnails(($thumbnailRenderer[$thumbnailRendererField])['thumbnail'], $isThumbnailAVideo);

                    $firstVideos = array_key_exists('thumbnail', $playlistRenderer) ? [getVideoFromItsThumbnails($playlistRenderer['thumbnail'])] : array_map(fn($videoThumbnails) => getVideoFromItsThumbnails($videoThumbnails), (array_key_exists('thumbnails', $playlistRenderer) ? $playlistRenderer['thumbnails'] : []));

                    $sidebarThumbnails = $playlistRenderer['sidebarThumbnails'];
                    $secondToFourthVideo = $sidebarThumbnails !== null ? array_map(fn($videoThumbnails) => getVideoFromItsThumbnails($videoThumbnails), $sidebarThumbnails) : [];

                    $firstVideos = array_merge($firstVideos, $secondToFourthVideo);

                    $title = $playlistRenderer['title'];

                    if (array_key_exists('playlistId', $playlistRenderer)) {
                        $id = $playlistRenderer['playlistId'];
                    } else {
                        $browseId = $playlistRenderer['navigationEndpoint']['browseEndpoint']['browseId'];
                        // For instance https://www.youtube.com/@FlyMinimal/playlists contains https://www.youtube.com/show/SCG2QET_lsEE-lsp4Kjk7HjA while neither `SCG2QET_lsEE-lsp4Kjk7HjA` nor `G2QET_lsEE-lsp4Kjk7HjA` are correct playlist ids.
                        // While https://www.youtube.com/@Goldenmoustache/playlists contains https://www.youtube.com/playlist?list=PLHYVKdTa8XHWIEnerFQ-X2dQT5liZpDix which is encoded as `VLPLHYVKdTa8XHWIEnerFQ-X2dQT5liZpDix` in `$browseId` which still seems more appropriate than the `$playlistRenderer['navigationEndpoint']['commandMetadata']['webCommandMetadata']['url']` which contains `/playlist?list=PLHYVKdTa8XHWIEnerFQ-X2dQT5liZpDix`.
                        // Note that https://www.youtube.com/@FlyMinimal/playlists only contain in its source code `SCG2QET_lsEE-lsp4Kjk7HjA`.
                        // Only `$browseId` and `url` are common fields to both of these edge cases.
                        if (str_starts_with($browseId, 'VL')) {
                            $browseId = substr($browseId, 2);
                        }
                        $id = $browseId;
                    }

                    $videoCount = intval((array_key_exists('videoCountText', $playlistRenderer) ? $playlistRenderer['videoCountText'] : $playlistRenderer['thumbnailOverlays'][0]['thumbnailOverlayBottomPanelRenderer']['text'])['runs'][0]['text']);

                    $sectionPlaylist = [
                        'id' => $id,
                        'thumbnailVideo' => $thumbnailVideo,
                        'firstVideos' => $firstVideos,
                        'title' => array_key_exists('runs', $title) ? $title['runs'][0]['text'] : $title['simpleText'],
                        'videoCount' => $videoCount,
                        'authors' => $authors,
                        // Does it always start with `Updated `?
                        // Note that for channels we don't have this field.
                        'publishedTimeText' => $playlistRenderer['publishedTimeText']['simpleText']
                    ];
                    array_push($sectionPlaylists, $sectionPlaylist);
                }
                array_push($playlistSections, [
                    'title' => $sectionTitle,
                    'playlists' => $sectionPlaylists,
                    'nextPageToken' => $nextPageToken
                ]);
            }
            $item['playlistSections'] = $playlistSections;
        }

        return $item;
    }

    function returnItems($items)
    {
        $answer = [
            'kind' => 'youtube#channelListResponse',
            'etag' => 'NotImplemented',
            'items' => $items
        ];
        // should add in some way the pageInfo ?

        return json_encode($answer, JSON_PRETTY_PRINT);
    }

    function getAPI($id, $continuationToken)
    {
        $items = [];
        array_push($items, getItem($id, $continuationToken));
        return returnItems($items);
    }
