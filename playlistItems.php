<?php

    header('Content-Type: application/json; charset=UTF-8');

    $playlistItemsTests = [
        // not precise...
        // Preferably have only more than 200 different videos but that I own would be more robust
        //['part=snippet&playlistId=PLKAl8tt2R8OfMnDRnEABZ2M-tI7yJYvl1', 'items/0/snippet/publishedAt', 1520963713]
    ];

include_once 'common.php';

if (isset($_GET['part'], $_GET['playlistId'])) {
    $part = $_GET['part'];
    if (!in_array($part, ['snippet'])) {
        dieWithJsonMessage('Invalid part');
    }
    $playlistId = $_GET['playlistId'];
    if (!isPlaylistId($playlistId)) {
        dieWithJsonMessage('Invalid playlistId');
    }
    $continuationToken = '';
    if (isset($_GET['pageToken'])) {
        $continuationToken = $_GET['pageToken'];
        if (!isContinuationToken($continuationToken)) {
            dieWithJsonMessage('Invalid pageToken');
        }
    }
    echo getAPI($playlistId, $continuationToken);
} else if(!test()) {
    dieWithJsonMessage('Required parameters not provided');
}

function getAPI($playlistId, $continuationToken)
{
    $continuationTokenProvided = $continuationToken != '';
    $http = [];
    $url = '';
    if ($continuationTokenProvided) {
        $url = 'https://www.youtube.com/youtubei/v1/browse?key=' . UI_KEY;
        $rawData = [
            'context' => [
                'client' => [
                    'clientName' => 'WEB',
                    'clientVersion' => MUSIC_VERSION
                ]
            ],
            'continuation' => $continuationToken
        ];
        $http['method'] = 'POST';
        $http['header'] = 'Content-Type: application/json';
        $http['content'] = json_encode($rawData);
    } else {
        $url = "https://www.youtube.com/playlist?list=$playlistId";
        $http['header'] = ['Accept-Language: en'];
    }

    $httpOptions = [
        'http' => $http
    ];

    $res = getRemote($url, $httpOptions);

    if (!$continuationTokenProvided) {
        $res = getJSONStringFromHTML($res);
    }

    $result = json_decode($res, true);
    $answerItems = [];
    $items = $continuationTokenProvided ? getContinuationItems($result) : getTabs($result)[0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['playlistVideoListRenderer']['contents'];
    $itemsCount = count($items);
    for ($itemsIndex = 0; $itemsIndex < $itemsCount - 1; $itemsIndex++) {
        $item = $items[$itemsIndex];
        $playlistVideoRenderer = $item['playlistVideoRenderer'];
        $videoId = $playlistVideoRenderer['videoId'];
        $title = $playlistVideoRenderer['title']['runs'][0]['text'];
        $publishedAt = getPublishedAt($playlistVideoRenderer['videoInfo']['runs'][2]['text']);
        $thumbnails = $playlistVideoRenderer['thumbnail']['thumbnails'];
        $answerItem = [
            'kind' => 'youtube#playlistItem',
            'etag' => 'NotImplemented',
            'snippet' => [
                'publishedAt' => $publishedAt,
                'title' => $title,
                'thumbnails' => $thumbnails,
                'resourceId' => [
                    'kind' => 'youtube#video',
                    'videoId' => $videoId
                ]
            ]
        ];
        array_push($answerItems, $answerItem);
    }
    $nextContinuationToken = urldecode($items[100]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']); // it doesn't seem random but hard to reverse-engineer
    $answer = [
        'kind' => 'youtube#playlistItemListResponse',
        'etag' => 'NotImplemented'
    ];
    // order matter or could afterwards sort by an (official YT API) arbitrary order (not alphabetical)
    // seems to be this behavior with the official API
    if ($nextContinuationToken != '') {
        $answer['nextPageToken'] = $nextContinuationToken;
    }
    $answer['items'] = $answerItems;

    return json_encode($answer, JSON_PRETTY_PRINT);
}
