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
            die("invalid part $part");
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
            die('invalid pageToken');
        }
    }
    echo getAPI($videoId, $order, $continuationToken);
}

function getAPI($videoId, $order, $continuationToken)
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
        if ($order === 'time') {
            $continuationToken = $result['onResponseReceivedEndpoints'][0]['reloadContinuationItemsCommand']['continuationItems'][0]['commentsHeaderRenderer']['sortMenu']['sortFilterSubMenuRenderer']['subMenuItems'][1]['serviceEndpoint']['continuationCommand']['token'];
            return getAPI($videoId, null, $continuationToken);
        }
    } else {
        $result = getJSONFromHTML("https://www.youtube.com/watch?v=$videoId");
        $continuationToken = end($result['contents']['twoColumnWatchNextResults']['results']['results']['contents'])['itemSectionRenderer']['contents'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'];
        return getAPI($videoId, $order, $continuationToken);
    }

    $answerItems = [];
    $onResponseReceivedEndpoints = $result['onResponseReceivedEndpoints'];
    $reloadContinuationItems = $onResponseReceivedEndpoints[1]['reloadContinuationItemsCommand']['continuationItems'];
    $appendContinuationItems = $onResponseReceivedEndpoints[0]['appendContinuationItemsAction']['continuationItems'];
    $items = array_merge($reloadContinuationItems !== null ? $reloadContinuationItems : [], $appendContinuationItems !== null ? $appendContinuationItems : []);
    if ($items !== [] && array_key_exists('continuationItemRenderer', end($items))) {
        $continuationItemRenderer = end($items)['continuationItemRenderer'];
        $nextContinuationToken = urldecode(getValue($continuationItemRenderer, (array_key_exists('continuationEndpoint', $continuationItemRenderer) ? 'continuationEndpoint' : 'button/buttonRenderer/command') . '/continuationCommand/token'));
        $items = array_slice($items, 0, count($items) - 1);
    }
    $isTopLevelComment = true;
    foreach ($items as $item) {
        $commentThread = $item['commentThreadRenderer'];
        $isTopLevelComment = array_key_exists('commentThreadRenderer', $item);
        $comment = ($isTopLevelComment ? $commentThread['comment'] : $item)['commentRenderer'];
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
        $internalSnippet = [
            'textOriginal' => $text,
            'isHearted' => $isHearted,
            'authorDisplayName' => $comment['authorText']['simpleText'],
            'authorHandle' => $comment['authorText']['simpleText'],
            'authorProfileImageUrls' => $comment['authorThumbnail']['thumbnails'],
            'authorChannelId' => ['value' => $comment['authorEndpoint']['browseEndpoint']['browseId']],
            'likeCount' => getIntValue($comment['voteCount']['simpleText']),
            'publishedAt' => $publishedAt,
            'wasEdited' => $wasEdited,
            'isPinned' => array_key_exists('pinnedCommentBadge', $comment),
            'authorIsChannelOwner' => $comment['authorIsChannelOwner'],
            'videoCreatorHasReplied' => $commentRepliesRenderer !== null && array_key_exists('viewRepliesCreatorThumbnail', $commentRepliesRenderer),
            // Could add the video creator thumbnails.
            'totalReplyCount' => $replyCount !== null ? intval($replyCount) : 0,
            'nextPageToken' => urldecode($replies['commentRepliesRenderer']['contents'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'])
        ];
        $answerItem = [
            'kind' => 'youtube#comment' . ($isTopLevelComment ? 'Thread' : ''),
            'etag' => 'NotImplemented',
            'id' => $commentId,
            'snippet' => ($isTopLevelComment ? [
                'topLevelComment' => [
                    'kind' => 'youtube#comment',
                    'etag' => 'NotImplemented',
                    'id' => $commentId,
                    'snippet' => $internalSnippet
                ]
            ] : $internalSnippet)
        ];
        array_push($answerItems, $answerItem);
    }
    $answer = [
        'kind' => 'youtube#comment' . ($isTopLevelComment ? 'Thread' : '') . 'ListResponse',
        'etag' => 'NotImplemented',
        'pageInfo' => [
            'totalResults' => intval($result['onResponseReceivedEndpoints'][0]['reloadContinuationItemsCommand']['continuationItems'][0]['commentsHeaderRenderer']['countText']['runs'][0]['text']),
            'resultsPerPage' => $isTopLevelComment ? 20 : 10
        ]
    ];
    if ($nextContinuationToken != '') {
        $answer['nextPageToken'] = $nextContinuationToken;
    }
    $answer['items'] = $answerItems;

    return json_encode($answer, JSON_PRETTY_PRINT);
}
