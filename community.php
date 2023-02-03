<?php

header('Content-Type: application/json; charset=UTF-8');

include_once 'common.php';

$realOptions = ['snippet'];

foreach ($realOptions as $realOption) {
    $options[$realOption] = false;
}

if (isset($_GET['part'], $_GET['id'])) {
    $part = $_GET['part'];
    $parts = explode(',', $part, count($realOptions));
    foreach ($parts as $part) {
        if (!in_array($part, $realOptions)) {
            dieWithJsonMessage("Invalid part $part");
        } else {
            $options[$part] = true;
        }
    }

    $postId = $_GET['id'];
    if (!isPostId($postId)) {
        dieWithJsonMessage('Invalid postId');
    }

    $order = isset($_GET['order']) ? $_GET['order'] : 'relevance';
    if (!in_array($order, ['relevance', 'time'])) {
        dieWithJsonMessage('Invalid order');
    }

    echo getAPI($postId, $order);
} else {
    dieWithJsonMessage("Required parameters not provided");
}

function getAPI($postId, $order)
{
    $http = [
        'header' => ['Accept-Language: en']
    ];

    $options = [
        'http' => $http
    ];

    $result = getJSONFromHTML("https://www.youtube.com/post/$postId", $options);
    $contents = $result['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'];
    $content = $contents[0]['itemSectionRenderer']['contents'][0];
    $post = getCommunityPostFromContent($content);
    $continuationToken = urldecode($contents[1]['itemSectionRenderer']['contents'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']);

   if ($order === 'time') {
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
        $continuationToken = urldecode($result['onResponseReceivedEndpoints'][0]['reloadContinuationItemsCommand']['continuationItems'][0]['commentsHeaderRenderer']['sortMenu']['sortFilterSubMenuRenderer']['subMenuItems'][1]['serviceEndpoint']['continuationCommand']['token']);
    }

    $comments = [
        'nextPageToken' => $continuationToken
    ];
    $post['comments'] = $comments;

    $answerItem = [
        'kind' => 'youtube#community',
        'etag' => 'NotImplemented',
        'id' => $postId,
        'snippet' => $post
    ];
    $answer = [
        'kind' => 'youtube#communityListResponse',
        'etag' => 'NotImplemented'
    ];
    $answer['items'] = [$answerItem];

    return json_encode($answer, JSON_PRETTY_PRINT);
}
