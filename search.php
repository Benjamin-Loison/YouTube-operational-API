<?php

    // StackOverflow source: https://stackoverflow.com/a/70793047
    $searchTests = [['snippet&channelId=UC4QobU6STFB0P71PMvOGN5A&order=viewCount', 'items/0/id/videoId', 'jNQXAC9IVRw']];

// copy YT perfectly (answers and arguments) - slower because not always everything from answer in one request for me
// make an API based on one request I receive involves one request on my side - more precise in terms of complexity
// can from this last model also just include "the interesting data" and nothing repetitive with the YouTube Data API v3, I mean that from the videoId we can get all details we want from the official API so maybe no need to repeat some here even if there are in the answer of my request

include_once 'common.php';

$realOptions = ['id', 'snippet'];

// really necessary ?
foreach ($realOptions as $realOption) {
    $options[$realOption] = false;
}

if (isset($_GET['part']) &&
  (isset($_GET['channelId']) || isset($_GET['channelId'], $_GET['eventType']) || isset($_GET['hashTag']) || isset($_GET['q'])) &&
  (isset($_GET['order']) || isset($_GET['q']) || isset($_GET['eventType']))) {
    $part = $_GET['part'];
    $parts = explode(',', $part, count($realOptions));
    foreach ($parts as $part) {
        if (!in_array($part, $realOptions)) {
            die("invalid part $part");
        } else {
            $options[$part] = true;
        }
    }

    if ($options['snippet']) {
        $options['id'] = true;
    }

    $id = '';
    if (isset($_GET['channelId'])) {
        $id = $_GET['channelId'];
    
        if (!isChannelId($id)) {
            die('invalid channelId');
        }
    } elseif ($_GET['eventType']) {
        $eventType = $_GET['eventType'];

        if (!isEventType($eventType)) {
            die('invalid eventType');
        }
    } elseif ($_GET['hashTag']) {
        $id = $_GET['hashTag'];

        if (!isHashTag($id)) {
            die('invalid hashTag');
        }
    } elseif ($_GET['q']) {
        $id = $_GET['q'];

        if (!isQuery($id)) {
            die('invalid q');
        }
    } else {
        die('no channelId or hashTag or q field was provided');
    }

    if ((isset($_GET['order']) || !isset($_GET['q'])) && !isset($_GET['eventType'])) {
        $order = $_GET['order'];
        if (!in_array($order, ['viewCount', 'relevance'])) {
            die('invalid order');
        }
    }
    $continuationToken = '';
    if (isset($_GET['pageToken'])) {
        $continuationToken = $_GET['pageToken'];
        // what checks to do ?
        if (!isContinuationToken($continuationToken)) {
            die('invalid pageToken');
        }
    }
    echo getAPI($id, $order, $continuationToken);
}

function getAPI($id, $order, $continuationToken)
{
    global $options;
    $items = null;
    $continuationTokenProvided = $continuationToken != '';
    if (isset($_GET['hashTag'])) {
        if ($continuationTokenProvided) {
            $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . MUSIC_VERSION . '"}},"continuation":"' . $continuationToken . '"}';
            $opts = [
                "http" => [
                    "method" => "POST",
                    "header" => "Content-Type: application/json",
                    "content" => $rawData
                ]
            ];
            $json = getJSON('https://www.youtube.com/youtubei/v1/browse?key=' . UI_KEY, $opts);
        } else {
            $json = getJSONFromHTML('https://www.youtube.com/hashtag/' . urlencode($id));
        }
        $items = $continuationTokenProvided ? $json['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems'] : $json['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['richGridRenderer']['contents'];
    } elseif (isset($_GET['eventType'])) {
        $json = getJSONFromHTML("https://www.youtube.com/channel/{$_GET['channelId']}/videos?view=2&live_view=502");
        $items = $json['contents']['twoColumnBrowseResultsRenderer']['tabs']['1']['tabRenderer']['content']['sectionListRenderer']['contents']['0']['itemSectionRenderer']['contents']['0']['gridRenderer']['items'];
    } elseif (isset($_GET['q'])) {
        $typeBase64 = $order === 'relevance' ? '' : 'EgIQAQ==';
        $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . MUSIC_VERSION . '"}}' . ($continuationTokenProvided ? ',"continuation":"' . $continuationToken . '"' : ',"query":"' . str_replace('"', '\"', $_GET['q']) . '"' . ($typeBase64 !== '' ? ',"params":"' . $typeBase64 . '"' : '')) . '}';
        $opts = [
               "http" => [
                   "method" => "POST",
                   "header" => "Content-Type: application/json",
                   "content" => $rawData,
               ]
        ];
        $json = getJSON('https://www.youtube.com/youtubei/v1/search?key=' . UI_KEY, $opts);
        $items = $continuationTokenProvided ? $json['continuationContents']['sectionListContinuation']['contents'][0]['itemSectionRenderer']['contents'] : $json['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'];
    } else { // should precise case to make it more readable
        $orderBase64 = 'EgZ2aWRlb3MYASAAMAE=';
        $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . CLIENT_VERSION . '"}},"' . ($continuationTokenProvided ? 'continuation":"' . $continuationToken : 'browseId":"' . $_GET['channelId'] . '","params":"' . $orderBase64) . '"}';
        $opts = [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json",
                "content" => $rawData,
            ]
        ];
    
        $result = getJSON('https://www.youtube.com/youtubei/v1/browse?key=' . UI_KEY, $opts);
        // repeated on official API but not in UI requests
        //if(!$continuationTokenProvided)
        //     $regionCode = $result['topbar']['desktopTopbarRenderer']['countryCode'];
        $items = $continuationTokenProvided ? $result['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems'] : $result['contents']['twoColumnBrowseResultsRenderer']['tabs'][1]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['gridRenderer']['items'];
    }
    $answerItems = [];
    $itemsCount = count($items);
    for ($itemsIndex = 0; $itemsIndex < $itemsCount - ($continuationTokenProvided || $_GET['hashTag'] ? 1 : 0); $itemsIndex++) { // check upper bound for hashtags
        $item = $items[$itemsIndex];
        $path = '';
        if (isset($_GET['hashTag'])) {
            $path = 'richItemRenderer/content/videoRenderer';
        } elseif (isset($_GET['q'])) {
            $path = 'videoRenderer';
        } else {
            $path = 'gridVideoRenderer';
        }
        $gridVideoRenderer = getValue($item, $path);
        $answerItem = [
            'kind' => 'youtube#searchResult',
            'etag' => 'NotImplemented'
        ];
        if ($options['id']) {
            $videoId = $gridVideoRenderer['videoId'];
            $answerItem['id'] = [
                'kind' => 'youtube#video',
                'videoId' => $videoId
            ];
        }
        if ($options['snippet']) {
            $title = $gridVideoRenderer['title']['runs'][0]['text'];
            $run = $gridVideoRenderer['ownerText']['runs'][0];
            $browseEndpoint = $run['navigationEndpoint']['browseEndpoint'];
            $channelId = $browseEndpoint['browseId'];
            $views = getIntFromViewCount($gridVideoRenderer['viewCountText']['simpleText']);
            $badges = $gridVideoRenderer['badges'];
            $badges = !empty($badges) ? array_map(fn($badge) => $badge['metadataBadgeRenderer']['label'], $badges) : [];
            $chapters = $gridVideoRenderer['expandableMetadata']['expandableMetadataRenderer']['expandedContent']['horizontalCardListRenderer']['cards'];
            $chapters = !empty($chapters) ? array_map(function($chapter) {
                $macroMarkersListItemRenderer = $chapter['macroMarkersListItemRenderer'];
                return [
                    'title' => $macroMarkersListItemRenderer['title']['simpleText'],
                    'time' => getIntFromDuration($macroMarkersListItemRenderer['timeDescription']['simpleText']),
                    'thumbnails' => $macroMarkersListItemRenderer['thumbnail']['thumbnails']
            ]; }, $chapters) : [];
            $answerItem['snippet'] = [
                'channelId' => $channelId,
                'title' => $title,
                'thumbnails' => $gridVideoRenderer['thumbnail']['thumbnails'],
                'channelTitle' => $run['text'],
                'channelHandle' => substr($browseEndpoint['canonicalBaseUrl'], 2),
                'timestamp' => $gridVideoRenderer['publishedTimeText']['simpleText'],
                'duration' => getIntFromDuration($gridVideoRenderer['lengthText']['simpleText']),
                'views' => $views,
                'badges' => $badges,
                'channelApproval' => $gridVideoRenderer['ownerBadges'][0]['metadataBadgeRenderer']['tooltip'],
                'channelThumbnails' => $gridVideoRenderer['channelThumbnailSupportedRenderers']['channelThumbnailWithLinkRenderer']['thumbnail']['thumbnails'],
                'detailedMetadataSnippet' => $gridVideoRenderer['detailedMetadataSnippets'][0]['snippetText']['runs'][0]['text'],
                'chapters' => $chapters
            ];
        }
        array_push($answerItems, $answerItem);
    }
    if (isset($_GET['hashTag'])) {
        $nextContinuationToken = $itemsCount > 60 ? $items[60] : '';
    } else {
        $nextContinuationToken = $itemsCount > 30 ? $items[30] : '';
    } // it doesn't seem random but hard to reverse-engineer
    if ($nextContinuationToken !== '') {
        $nextContinuationToken = $nextContinuationToken['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'];
    }
    if (isset($_GET['q'])) {
        $nextContinuationToken = ($continuationTokenProvided ? $json['continuationContents']['sectionListContinuation'] : $json['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer'])['continuations'][0]['nextContinuationData']['continuation'];
    }
    $nextContinuationToken = urldecode($nextContinuationToken);
    $answer = [
        'kind' => 'youtube#searchListResponse',
        'etag' => 'NotImplemented'
    ];
    // order matter or could afterwards sort by an (official YT API) arbitrary order (not alphabetical)
    // seems to be this behavior with the official API
    if ($nextContinuationToken != '') {
        $answer['nextPageToken'] = $nextContinuationToken;
    }
    //if(!$continuationTokenProvided) // doesn't seem accurate
    //  $answer['regionCode'] = $regionCode;
    $answer['items'] = $answerItems;

    return json_encode($answer, JSON_PRETTY_PRINT);
}
