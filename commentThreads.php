<?php

    // StackOverflow source: https://stackoverflow.com/q/71186488
    $commentThreadsTests = [['snippet&videoId=UC4QobU6STFB0P71PMvOGN5A&order=viewCount', 'items/0/id/videoId', 'jNQXAC9IVRw']];
    // example: https://youtu.be/mrJachWLjHU
    // example: https://youtu.be/DyDfgMOUjCI

include_once 'common.php';

$realOptions = ['snippet', 'replies'];

foreach ($realOptions as $realOption) {
    $options[$realOption] = false;
}

if (isset($_GET['part'], $_GET['videoId'], $_GET['order'])) {
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

    $order = $_GET['order'];
    if (!in_array($order, ['relevance', 'time'])) {
        die('invalid order');
    }

    $continuationToken = '';
    if (isset($_GET['pageToken'])) {
        $continuationToken = $_GET['pageToken'];
        if (!isContinuationToken($continuationToken)) {
            die('invalid continuationToken');
        }
    }
    echo getAPI($videoId, $order, $continuationToken);
}

function getAPI($videoId, $order, $continuationToken, $initialResult = null)
{
    $continuationTokenProvided = $continuationToken != '';
    if ($continuationTokenProvided) {
        $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . MUSIC_VERSION . '"}},"continuation":"' . $continuationToken . '"}';
        $opts = [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json",
                "content" => $rawData,
            ]
        ];
        $result = getJSON('https://www.youtube.com/youtubei/v1/next?key=' . UI_KEY, $opts);
    } else {
        $result = getJSONFromHTML('https://www.youtube.com/watch?v=' . $videoId);
        $continuationToken = $order === 'time' ? $result['engagementPanels'][2]['engagementPanelSectionListRenderer']['header']['engagementPanelTitleHeaderRenderer']['menu']['sortFilterSubMenuRenderer']['subMenuItems'][1]['serviceEndpoint']['continuationCommand']['token'] : end($result['contents']['twoColumnWatchNextResults']['results']['results']['contents'])['itemSectionRenderer']['contents'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'];
        return getAPI($videoId, $order, $continuationToken, $result);
    }

    $answerItems = [];
    $onResponseReceivedEndpoints = $result['onResponseReceivedEndpoints'];
    $reloadContinuationItems = $onResponseReceivedEndpoints[1]['reloadContinuationItemsCommand']['continuationItems'];
    $appendContinuationItems = $onResponseReceivedEndpoints[0]['appendContinuationItemsAction']['continuationItems'];
    $items = array_merge($reloadContinuationItems !== null ? $reloadContinuationItems : [], $appendContinuationItems !== null ? $appendContinuationItems : []);
    $itemsCount = $items !== null ? count($items) : 0;
    $resultsPerPage = 20;
    if ($items !== [] && array_key_exists('continuationItemRenderer', end($items))) {//$itemsCount == $resultsPerPage + 1) {
        $continuationItemRenderer = end($items)['continuationItemRenderer'];
        $nextContinuationToken = urldecode(getValue($continuationItemRenderer, (array_key_exists('continuationEndpoint', $continuationItemRenderer) ? 'continuationEndpoint' : 'button/buttonRenderer/command') . '/continuationCommand/token'));
        $items = array_slice($items, 0, count($items) - 1);//$resultsPerPage);
    }
    foreach ($items as $item) {
        $commentThread = $item['commentThreadRenderer'];
        $comment = (array_key_exists('commentThreadRenderer', $item) ? $commentThread['comment'] : $item)['commentRenderer'];
        $texts = $comment['contentText']['runs'];
        $replies = $commentThread['replies'];
        $commentRepliesRenderer = $replies['commentRepliesRenderer'];
        $text = implode(array_map(function($text) { return $text['text']; }, $texts));
        $commentId = $comment['commentId'];
        $isHearted = array_key_exists('creatorHeart', $comment['actionButtons']['commentActionButtonsRenderer']);
        $publishedAt = $comment['publishedTimeText']['runs'][0]['text'];
        $publishedAt = str_replace(' (edited)', '', $publishedAt, $count);
        $wasEdited = $count > 0;
        $replyCount = $comment['replyCount'];
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
                        'textOriginal' => $text,
                        'isHearted' => $isHearted,
                        'authorDisplayName' => $comment['authorText']['simpleText'],
                        'authorProfileImageUrls' => $comment['authorThumbnail']['thumbnails'],
                        'authorChannelId' => ['value' => $comment['authorEndpoint']['browseEndpoint']['browseId']],
                        'likeCount' => intval($comment['voteCount']['simpleText']),
                        'publishedAt' => $publishedAt,
                        'wasEdited' => $wasEdited,
                        'isPinned' => array_key_exists('pinnedCommentBadge', $comment),
                        'authorIsChannelOwner' => $comment['authorIsChannelOwner'],
                        'videoCreatorHasReplied' => $commentRepliesRenderer !== null && array_key_exists('viewRepliesCreatorThumbnail', $commentRepliesRenderer),
                        // Could add the video creator thumbnails.
                        'nextPageToken' => urldecode($replies['commentRepliesRenderer']['contents'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'])
                    ],
                    'totalReplyCount' => $replyCount !== null ? intval($replyCount) : 0
                ]
            ]
        ];
        array_push($answerItems, $answerItem);
    }
    $answer = [
        'kind' => 'youtube#commentThreadListResponse',
        'etag' => 'NotImplemented',
        'pageInfo' => [
            'totalResults' => intval($result['onResponseReceivedEndpoints'][0]['reloadContinuationItemsCommand']['continuationItems'][0]['commentsHeaderRenderer']['countText']['runs'][0]['text']),
            'resultsPerPage' => $resultsPerPage
        ]
    ];
    if ($nextContinuationToken != '') {
        $answer['nextPageToken'] = $nextContinuationToken;
    }
    $answer['items'] = $answerItems;

    return json_encode($answer, JSON_PRETTY_PRINT);
}
