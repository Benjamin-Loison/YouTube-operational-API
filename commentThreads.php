<?php

    // StackOverflow source: https://stackoverflow.com/questions/71186488/how-do-i-get-the-comments-on-a-video-that-have-been-liked-by-the-video-creator
    $commentThreadsTests = [['snippet&videoId=UC4QobU6STFB0P71PMvOGN5A&order=viewCount', 'items/0/id/videoId', 'jNQXAC9IVRw']];
    // example: https://youtu.be/mrJachWLjHU
    // example: https://youtu.be/DyDfgMOUjCI

include_once 'common.php';

$realOptions = ['snippet', 'replies'];

foreach ($realOptions as $realOption) {
    $options[$realOption] = false;
}

if (isset($_GET['part'], $_GET['videoId'])) {
    $part = $_GET['part'];
    $parts = explode(',', $part, count($realOptions));
    foreach ($parts as $part) {
        if (!in_array($part, $realOptions)) {
            die('invalid part ' . $part);
        } else {
            $options[$part] = true;
        }
    }

    $videoId = $_GET['videoId'];
    if (!isVideoId($videoId)) {
        die('invalid videoId');
    }
    $continuationToken = '';
    if (isset($_GET['pageToken'])) {
        $continuationToken = $_GET['pageToken'];
        if (!isContinuationToken($continuationToken)) {
            die('invalid continuationToken');
        }
    }
    echo getAPI($videoId, $continuationToken);
}

function getAPI($videoId, $continuationToken)
{
    $continuationTokenProvided = $continuationToken != '';
    $result = null;
    $nextContinuationToken = '';
    if ($continuationTokenProvided) {
        $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . CLIENT_VERSION . '"}},"continuation":"' . $continuationToken . '"}';
        $opts = [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json",
                "content" => $rawData,
            ]
        ];
        //die(getRemote('https://www.youtube.com/youtubei/v1/next?key=' . UI_KEY, $opts));
        $result = getJSON('https://www.youtube.com/youtubei/v1/next?key=' . UI_KEY, $opts);
    } else {
        $result = getJSONFromHTML('https://www.youtube.com/watch?v=' . $videoId);
        $continuationToken = $result['contents']['twoColumnWatchNextResults']['results']['results']['contents'][2]['itemSectionRenderer']['contents'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'];
        die($continuationToken);
        return getAPI($videoId, $continuationToken); // for user-friendliness
    }
    die(json_encode($result, JSON_PRETTY_PRINT));

    $answerItems = [];
    $items = $result['onResponseReceivedEndpoints'][1]['reloadContinuationItemsCommand']['continuationItems'];
    $itemsCount = count($items);
    if ($itemsCount == 20) {
        var_dump($result['onResponseReceivedEndpoints'][1]['reloadContinuationItemsCommand']['continuationItems'][19]);
        $nextContinuationToken = $result['onResponseReceivedEndpoints'][1]['reloadContinuationItemsCommand']['continuationItems'][20]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'];
        die($nextContinuationToken);
    }
    for ($itemsIndex = 0; $itemsIndex < $itemsCount; $itemsIndex++) {
        $item = $items[$itemsIndex];
        $commentThread = $item['commentThreadRenderer'];
        $comment = $commentThread['comment']['commentRenderer'];
        $text = '';
        $texts = $comment['contentText']['runs'];
        $textsCount = count($texts);
        //var_dump($commentThread['replies']['commentRepliesRenderer']['contents'][0]);
        //die($commentThread['replies']['commentRepliesRenderer']['contents'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']);
        for ($textsIndex = 0; $textsIndex < $textsCount; $textsIndex++) {
            $text .= $texts[$textsIndex]['text'];
            if ($textsIndex < $textsCount - 1) {
                $text .= "\n";
            }
        }
        $commentId = $comment['commentId'];
        $isHearted = array_key_exists('creatorHeart', $comment['actionButtons']['commentActionButtonsRenderer']);
        $answerItem = [
            'kind' => 'youtube#commentThread',
            'etag' => 'NotImplemented',
            'id' => $commentId,
            'snippet' => [
                'topLevelComment' => [
                    'kind' => 'youtube#comment',
                    'etag' => 'NotImplemented',
                    'id' => $commentId,
                    'snippet' => [
                        'textOriginal' => $text, // not exactly the same as official for â for instance (different from ')
                        'isHearted' => $isHearted
                    ]
                ]
            ]
        ];
        array_push($answerItems, $answerItem);
    }
    $nextContinuationToken = count($items) > 30 ? str_replace('%3D', '=', $items[30]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']) : '';
    $answer = [
        'kind' => 'youtube#commentThreadListResponse',
        'etag' => 'NotImplemented'
    ];
    if ($nextContinuationToken != '') {
        $answer['nextPageToken'] = $nextContinuationToken;
    }
    $answer['items'] = $answerItems;

    return json_encode($answer, JSON_PRETTY_PRINT);
}
