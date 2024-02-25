<?php

    header('Content-Type: application/json; charset=UTF-8');

    $channelsTests = [
        ['cId=FolkartTr', 'items/0/id', 'UCnS--2e1yzQCm5r4ClrMJBg'],
        ['handle=@Test-kq9ig', 'items/0/id', 'UCv_LqFI-0vMVYgNR3TeB3zQ'],
        ['forUsername=DonDiablo', 'items/0/id', 'UC8y7Xa0E1Lo6PnVsu2KJbOA'],
        ['part=status&id=UC7LoiySz7-FcGgZCKBq_2vQ', 'items/0/status', 'This channel is not available.'],
        // How to precise viewCount can be any integer greater than those we have? Same concerning relative date.
        // Do not forget to format JSON
        ['part=shorts&id=UCv_LqFI-0vMVYgNR3TeB3zQ', 'items/0/shorts', json_decode(file_get_contents('tests/part=shorts&id=UCv_LqFI-0vMVYgNR3TeB3zQ.json'), true)],
        ['part=community&id=UCv_LqFI-0vMVYgNR3TeB3zQ', 'items/0/community', json_decode(file_get_contents('tests/part=community&id=UCv_LqFI-0vMVYgNR3TeB3zQ.json'), true)],
        ['part=about&id=UCv_LqFI-0vMVYgNR3TeB3zQ', 'items/0', json_decode(file_get_contents('tests/part=about&id=UCv_LqFI-0vMVYgNR3TeB3zQ.json'), true)],
        ['part=approval&id=UC0aMaqIs997ggjDs_Q9UYiw', 'items/0/approval', 'Official Artist Channel'],
        ['part=snippet&id=UCv_LqFI-0vMVYgNR3TeB3zQ', 'items/0/snippet', json_decode(file_get_contents('tests/part=snippet&id=UCv_LqFI-0vMVYgNR3TeB3zQ.json'), true)],
        ['part=membership&id=UCX6OQ3DkcsbYNE6H8uQQuVA', 'items/0/isMembershipEnabled', true],
        ['part=popular&id=UCyvTYozFRVuM_mKKyT6K50g', 'items/0', []],
        ['part=recent&id=UCyvTYozFRVuM_mKKyT6K50g', 'items/0', []],
    ];

    include_once 'common.php';

    $realOptions = [
        'status',
        'upcomingEvents',
        'shorts',
        'community',
        'channels',
        'about',
        'approval',
        'playlists',
        'snippet',
        'membership',
        'popular',
        'recent',
        'letsPlay',
    ];

    // really necessary ?
    foreach ($realOptions as $realOption) {
        $options[$realOption] = false;
    }

    // Forbidding URL with no `part` and using `id` filter is debatable.
    if (isset($_GET['cId']) || isset($_GET['id']) || isset($_GET['handle']) || isset($_GET['forUsername']) || isset($_GET['raw'])) {
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
        } else if (isset($_GET['handle'])) {
            $handle = $_GET['handle'];
            if (!isHandle($handle)) {
                dieWithJsonMessage('Invalid handle');
            }
            $result = getJSONFromHTML("https://www.youtube.com/$handle");
            $params = $result['responseContext']['serviceTrackingParams'][0]['params'];
            foreach($params as $param)
            {
                if($param['key'] === 'browse_id')
                {
                    $id = $param['value'];
                    break;
                }
            }
        }
        else if (isset($_GET['forUsername'])) {
            $username = $_GET['forUsername'];
            if (!isUsername($username)) {
                dieWithJsonMessage('Invalid forUsername');
            }
            $result = getJSONFromHTML("https://www.youtube.com/user/$username");
            $id = $result['header']['c4TabbedHeaderRenderer']['channelId'];
        }
        else /*if (isset($_GET['raw']))*/ {
            $raw = $_GET['raw'];
            // Adding filter would be nice.
            $result = getJSONFromHTML("https://www.youtube.com/$raw");
            $id = $result['header']['c4TabbedHeaderRenderer']['channelId'];
        }
        $order = 'time';
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
            if (!in_array($order, ['time', 'viewCount'])) {
                dieWithJsonMessage('Invalid order');
            }
        }
        $continuationToken = '';
        if (isset($_GET['pageToken'])) {
            $continuationToken = $_GET['pageToken'];
            $hasVisitorData = $options['shorts'] || $options['popular'] || $options['recent'];
            if (($hasVisitorData && !isContinuationTokenAndVisitorData($continuationToken)) || (!$hasVisitorData && !isContinuationToken($continuationToken))) {
                dieWithJsonMessage('Invalid pageToken');
            }
        }
        echo getAPI($id, $order, $continuationToken);
    } else if(!test()) {
        dieWithJsonMessage('Required parameters not provided');
    }

    function getItem($id, $order, $continuationToken)
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
            $result = getJSONFromHTML("https://www.youtube.com/channel/$id", verifiesChannelRedirection: true);
            $subItems = getTabs($result)[0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['shelfRenderer']['content']['horizontalListRenderer']['items'];
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
                $visitorData = getVisitorData($result);
                $tab = getTabByName($result, 'Shorts');
                $tabRenderer = $tab['tabRenderer'];
                $richGridRenderer = $tabRenderer['content']['richGridRenderer'];
                if ($order === 'viewCount') {
                    $nextPageToken = $richGridRenderer['header']['feedFilterChipBarRenderer']['contents'][1]['chipCloudChipRenderer']['navigationEndpoint']['continuationCommand']['token'];
                    if($nextPageToken !== null) {
                        $continuationToken = urldecode("$nextPageToken,$visitorData");
                        return getItem($id, $order, $continuationToken);
                    }
                }
            } else {
                $result = getContinuationJson($continuationToken);
            }
            $shorts = [];
            if (!$continuationTokenProvided) {
                $reelShelfRendererItems = $richGridRenderer['contents'];
            }
            else {
                $onResponseReceivedActions = $result['onResponseReceivedActions'];
                $onResponseReceivedAction = $onResponseReceivedActions[count($onResponseReceivedActions) - 1];
                $continuationItems = getValue($onResponseReceivedAction, 'appendContinuationItemsAction', 'reloadContinuationItemsCommand');
                $reelShelfRendererItems = $continuationItems['continuationItems'];
            }
            foreach($reelShelfRendererItems as $reelShelfRendererItem) {
                if(!array_key_exists('richItemRenderer', $reelShelfRendererItem))
                    continue;
                $reelItemRenderer = $reelShelfRendererItem['richItemRenderer']['content']['reelItemRenderer'];
                $viewCount = getIntValue($reelItemRenderer['viewCountText']['simpleText'], 'view');
                $frame0Thumbnails = $reelItemRenderer['navigationEndpoint']['reelWatchEndpoint']['thumbnail']['thumbnails'];

                $short = [
                    'videoId' => $reelItemRenderer['videoId'],
                    'title' => $reelItemRenderer['headline']['simpleText'],
                    // Both `sqp` and `rs` parameters are required to crop correctly the thumbnail.
                    'thumbnails' => $reelItemRenderer['thumbnail']['thumbnails'],
                    'viewCount' => $viewCount,
                    'frame0Thumbnails' => $frame0Thumbnails,
                ];
                if (!$continuationTokenProvided) {
                    $browseEndpoint = $tabRenderer['endpoint']['browseEndpoint'];
                    $short['channelHandle'] = substr($browseEndpoint['canonicalBaseUrl'], 1);
                    $short['channelId'] = $browseEndpoint['browseId'];
                }
                array_push($shorts, $short);
            }
            $item['shorts'] = $shorts;
            if($reelShelfRendererItems != null && count($reelShelfRendererItems) > 48) {
                $nextPageToken = $reelShelfRendererItems[48]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'];
                $item['nextPageToken'] = urldecode("$nextPageToken,$visitorData");
            }
        }

        if ($options['community']) {
            if (!$continuationTokenProvided) {
                $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/channel/$id/community", true);
            } else {
                $result = getContinuationJson($continuationToken);
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
                        $content = getValue($content, 'horizontalListRenderer', 'expandedShelfContentsRenderer');
                    } else {
                        $sectionTitle = $sectionListRenderer['subMenu']['channelSubMenuRenderer']['contentTypeSubMenuItems'][0]['title'];
                        $content = $content['gridRenderer'];
                    }
                    array_push($itemsArray, [$sectionTitle, $content['items']]);
                }
            } else {
                $result = getContinuationJson($continuationToken);
                $itemsArray = [[null, getContinuationItems($result)]];
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
                    $gridChannelRenderer = $sectionChannelItem[getValue($sectionChannelItem, 'gridChannelRenderer', 'channelRenderer')];
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
            $resultCommon = $result['onResponseReceivedEndpoints'][0]['showEngagementPanelEndpoint']['engagementPanel']['engagementPanelSectionListRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['aboutChannelRenderer']['metadata']['aboutChannelViewModel'];

            $about['stats'] = [
                'joinedDate' => strtotime(str_replace('Joined ', '', $resultCommon['joinedDateText']['content'])),
                // Could try to find a YouTube channel with a single view to make sure it displays "view" and not "views".
                'viewCount' => getIntValue($resultCommon['viewCountText'], 'view'),
                'subscriberCount' => getIntValue($c4TabbedHeaderRenderer['subscriberCountText']['simpleText'], 'subscriber'),
                'videoCount' => getIntValue($resultCommon['videoCountText'], 'video')
            ];

            $about['description'] = $resultCommon['description'];

            $about['details'] = [
                'location' => $resultCommon['country']
            ];

            $linksObjects = $resultCommon['links'];
            $links = [];
            foreach ($linksObjects as $linkObject) {
                $linkObject = $linkObject['channelExternalLinkViewModel'];
                $url = $linkObject['link']['commandRuns'][0]['onTap']['innertubeCommand']['urlEndpoint']['url'];
                $urlComponents = parse_url($url);
                parse_str($urlComponents['query'], $params);
                $link = [
                    'url' => getValue($params, 'q', defaultValue: $url),
                    'title' => $linkObject['title']['content'],
                    'favicon' => $linkObject['favicon']['sources']
                ];
                array_push($links, $link);
            }
            $about['links'] = $links;
            $about['handle'] = $c4TabbedHeaderRenderer['channelHandleText']['runs'][0]['text'];

            $item['about'] = $about;
        }

        if ($options['approval']) {
            $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/channel/$id", true);
            $badgeTooltipPath = 'header/c4TabbedHeaderRenderer/badges/0/metadataBadgeRenderer/tooltip';
            $item['approval'] = doesPathExist($result, $badgeTooltipPath) ? getValue($result, $badgeTooltipPath) : '';
        }

        if ($options['snippet']) {
            $result = getJSONFromHTML("https://www.youtube.com/channel/$id", verifiesChannelRedirection: true);
            $c4TabbedHeaderRenderer = $result['header']['c4TabbedHeaderRenderer'];
            $c4TabbedHeaderRendererKeys = ['avatar', 'banner', 'tvBanner', 'mobileBanner'];
            $c4TabbedHeaderRendererItems = array_map(fn($c4TabbedHeaderRendererKey) => $c4TabbedHeaderRenderer[$c4TabbedHeaderRendererKey]['thumbnails'], $c4TabbedHeaderRendererKeys);
            $snippet = array_combine($c4TabbedHeaderRendererKeys, array_map(fn($c4TabbedHeaderRendererKey) => $c4TabbedHeaderRenderer[$c4TabbedHeaderRendererKey]['thumbnails'], $c4TabbedHeaderRendererKeys));
            $item['snippet'] = $snippet;
        }

        if ($options['membership']) {
            $result = getJSONFromHTML("https://www.youtube.com/channel/$id");
            $item['isMembershipEnabled'] = array_key_exists('sponsorButton', $result['header']['c4TabbedHeaderRenderer']);
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
                        $content = getValue($content, 'horizontalListRenderer', 'expandedShelfContentsRenderer');
                    } else {
                        $sectionTitle = $sectionListRenderer['subMenu']['channelSubMenuRenderer']['contentTypeSubMenuItems'][0]['title'];
                        $content = $content['gridRenderer'];
                    }
                    array_push($itemsArray, [$sectionTitle, $content['items']]);
                }
            } else {
                $result = getContinuationJson($continuationToken);
                $itemsArray = [[null, getContinuationItems($result)]];
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

                    $playlistRenderer = getValue($sectionPlaylistItem, 'gridPlaylistRenderer', defaultValue: getValue($sectionPlaylistItem, 'playlistRenderer', 'gridShowRenderer'));
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

                    $videoCount = intval(getValue($playlistRenderer, 'videoCountText', 'thumbnailOverlays/0/thumbnailOverlayBottomPanelRenderer/text')['runs'][0]['text']);

                    $sectionPlaylist = [
                        'id' => $id,
                        'thumbnailVideo' => $thumbnailVideo,
                        'firstVideos' => $firstVideos,
                        'title' => getValue($title, 'runs/0/text', 'simpleText'),
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

        if ($options['popular'])
        {
            $getRendererItems = function($result)
            {
                $contents = getTabs($result)[0]['tabRenderer']['content']['sectionListRenderer']['contents'];
                $shelfRendererPath = 'itemSectionRenderer/contents/0/shelfRenderer';
                $content = array_values(array_filter($contents, fn($content) => getValue($content, $shelfRendererPath)['title']['runs'][0]['text'] == 'Popular'))[0];
                $shelfRenderer = getValue($content, $shelfRendererPath);
                $gridRendererItems = $shelfRenderer['content']['gridRenderer']['items'];
                return $gridRendererItems;
            };
            $item['popular'] = getVideos($item, $id, "https://www.youtube.com/channel/$id", $getRendererItems, $continuationToken);
        }

        if ($options['recent'])
        {
            $item['recent'] = getVideos($item, $id, "https://www.youtube.com/channel/$id/recent", fn($result) => getTabByName($result, 'Recent')['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['gridRenderer']['items'], $continuationToken);
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

    function getAPI($id, $order, $continuationToken)
    {
        $items = [];
        array_push($items, getItem($id, $order, $continuationToken));
        return returnItems($items);
    }

    function getVisitorData($result)
    {
        return $result['responseContext']['webResponseContextExtensionData']['ytConfigData']['visitorData'];
    }

    function getVideo($gridRendererItem)
    {
        $gridVideoRenderer = $gridRendererItem['gridVideoRenderer'];
        $run = $gridVideoRenderer['shortBylineText']['runs'][0];
        $browseEndpoint = $run['navigationEndpoint']['browseEndpoint'];
        $title = $gridVideoRenderer['title'];
        $publishedAt = getPublishedAt(end(explode('views', $title['accessibility']['accessibilityData']['label'])));
        return [
            'videoId' => $gridVideoRenderer['videoId'],
            'thumbnails' => $gridVideoRenderer['thumbnail']['thumbnails'],
            'title' => $title['runs'][0]['text'],
            'publishedAt' => $publishedAt,
            'views' => getIntFromViewCount($gridVideoRenderer['viewCountText']['simpleText']),
            'channelTitle' => $run['text'],
            'channelId' => $browseEndpoint['browseId'],
            'channelHandle' => substr($browseEndpoint['canonicalBaseUrl'], 1),
            'duration' => getIntFromDuration($gridVideoRenderer['thumbnailOverlays'][0]['thumbnailOverlayTimeStatusRenderer']['text']['simpleText']),
            'approval' => $gridVideoRenderer['ownerBadges'][0]['metadataBadgeRenderer']['tooltip'],
        ];
    }

    function getVideos(&$item, $id, $url, $getGridRendererItems, $continuationToken)
    {
        $videos = [];
        if ($continuationToken === '') {
            $result = getJSONFromHTMLForcingLanguage($url);
            $gridRendererItems = $getGridRendererItems($result);
            $visitorData = getVisitorData($result);
        }
        else
        {
            $result = getContinuationJson($continuationToken);
            $gridRendererItems = getContinuationItems($result);
        }
        foreach($gridRendererItems as $gridRendererItem)
        {
            if(!array_key_exists('continuationItemRenderer', $gridRendererItem))
            {
                array_push($videos, getVideo($gridRendererItem));
            }
        }
        if($gridRendererItem != null && array_key_exists('continuationItemRenderer', $gridRendererItem))
        {
            $item['nextPageToken'] = $gridRendererItem['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'] . ',' . $visitorData;
        }
        return $videos;
    }
