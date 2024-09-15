<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/vendor/autoload.php';

include_once 'common.php';

includeOnceProtos(['Browse', 'SubBrowse']);

$realOptions = [
    'snippet',
];

foreach ($realOptions as $realOption) {
    $options[$realOption] = false;
}

if (isset($_GET['part'], $_GET['id'], $_GET['channelId'])) {
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

    $channelId = $_GET['channelId'];
    if (!isChannelId($channelId)) {
        dieWithJsonMessage('Invalid channelId');
    }

    $order = isset($_GET['order']) ? $_GET['order'] : 'relevance';
    if (!in_array($order, ['relevance', 'time'])) {
        dieWithJsonMessage('Invalid order');
    }

    echo getAPI($postId, $channelId, $order);
} else if(!test()) {
    dieWithJsonMessage('Required parameters not provided');
}

function implodeArray($anArray, $separator)
{
    return array_map(fn($k, $v) => "${k}${separator}${v}", array_keys($anArray), array_values($anArray));
}

function getAPI($postId, $channelId, $order)
{
    $currentTime = time();
    $SAPISID = 'CENSORED';
    $__Secure_3PSID = 'CENSORED';
    $ORIGIN = 'https://www.youtube.com';
    $SAPISIDHASH = "${currentTime}_" . sha1("$currentTime $SAPISID $ORIGIN");

    $subBrowse = new \SubBrowse();
    $subBrowse->setPostId($postId);

    $browse = new \Browse();
    $browse->setEndpoint('community');
    $browse->setSubBrowse($subBrowse);

    $params = base64_encode($browse->serializeToString());

    $rawData = [
        'context' => [
            'client' => [
                'clientName' => 'WEB',
                'clientVersion' => MUSIC_VERSION
            ]
        ],
        'browseId' => $channelId,
        'params' => $params,
    ];

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => implodeArray([
                'Content-Type' => 'application/json',
                'Origin' => $ORIGIN,
                'Authorization' => "SAPISIDHASH $SAPISIDHASH",
                'Cookie' => implode('; ', implodeArray([
                    '__Secure-3PSID' => $__Secure_3PSID,
                    '__Secure-3PAPISID' => $SAPISID,
                ], '=')),
            ], ': '),
            'content' => json_encode($rawData),
        ]
    ];
    $result = getJSON('https://www.youtube.com/youtubei/v1/browse', $opts);
    $contents = getTabByName($result, 'Community')['tabRenderer']['content']['sectionListRenderer']['contents'];
    $content = $contents[0]['itemSectionRenderer']['contents'][0];
    $post = getCommunityPostFromContent($content);
    $continuationToken = urldecode($contents[1]['itemSectionRenderer']['contents'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']);

   if ($order === 'time') {
        $result = getContinuationJson($continuationToken);
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
