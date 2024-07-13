<?php

    header('Content-Type: application/json; charset=UTF-8');

    // Stack Overflow source: https://stackoverflow.com/q/71186488
    // use multiple lines, or not as they are not supposed to change
    $commentThreadsTests = [
        // How to disable people comments?
        // Otherwise should have a private set of tests
        //['part=snippet&videoId=UC4QobU6STFB0P71PMvOGN5A&order=viewCount', 'items/0/id/videoId', 'jNQXAC9IVRw'],
        //['part=snippet,replies&commentId=UgzT9BA9uQhXw05Q2Ip4AaABAg&videoId=mWdFMNQBcjs', 'items/0/id/videoId', 'jNQXAC9IVRw'],
    ];
    // example: https://youtu.be/mrJachWLjHU
    // example: https://youtu.be/DyDfgMOUjCI

include_once 'common.php';

$realOptions = [
    'snippet',
    'replies',
];

foreach ($realOptions as $realOption) {
    $options[$realOption] = false;
}

if (isset($_GET['part'])) {
    $part = $_GET['part'];
    $parts = explode(',', $part, count($realOptions));
    foreach ($parts as $part) {
        if (!in_array($part, $realOptions)) {
            dieWithJsonMessage("Invalid part $part");
        } else {
            $options[$part] = true;
        }
    }

    $videoId = null;
    if (isset($_GET['videoId'])) {
        $videoId = $_GET['videoId'];
        if (!isVideoId($videoId)) {
            dieWithJsonMessage('Invalid videoId');
        }
    }

    $commentId = null;
    if (isset($_GET['id'])) {
        $commentId = $_GET['id'];
        if (!isCommentId($commentId)) {
            dieWithJsonMessage('Invalid id');
        }
    }

    $order = isset($_GET['order']) ? $_GET['order'] : 'relevance';
    if (!in_array($order, ['relevance', 'time'])) {
        dieWithJsonMessage('Invalid order');
    }

    $continuationToken = '';
    if (isset($_GET['pageToken'])) {
        $continuationToken = $_GET['pageToken'];
        if (!isContinuationToken($continuationToken)) {
            dieWithJsonMessage('Invalid pageToken');
        }
    }
    echo getAPI($videoId, $commentId, $order, $continuationToken);
} else if(!test()) {
    dieWithJsonMessage('Required parameters not provided');
}

function getAPI($videoId, $commentId, $order, $continuationToken, $simulatedContinuation = false)
{
    if($commentId !== null)
    {
        $result = getJSONFromHTML("https://www.youtube.com/watch?v=$videoId&lc=$commentId");
        $continuationToken = $result['contents']['twoColumnWatchNextResults']['results']['results']['contents'][3]['itemSectionRenderer']['contents'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'];
    }
    $continuationTokenProvided = $continuationToken != '';
    if ($continuationTokenProvided) {
        $rawData = [
            'context' => [
                'client' => [
                    'clientName' => 'WEB',
                    'clientVersion' => MUSIC_VERSION
                ]
            ],
            'continuation' => $continuationToken
        ];
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => ['Content-Type: application/json'],
                'content' => json_encode($rawData),
            ]
        ];
        $result = getJSON('https://www.youtube.com/youtubei/v1/' . ($videoId !== null ? 'next' : 'browse') . '?key=' . UI_KEY, $opts);
        if ($order === 'time' && $simulatedContinuation) {
            $continuationToken = $result['onResponseReceivedEndpoints'][0]['reloadContinuationItemsCommand']['continuationItems'][0]['commentsHeaderRenderer']['sortMenu']['sortFilterSubMenuRenderer']['subMenuItems'][1]['serviceEndpoint']['continuationCommand']['token'];
            return getAPI($videoId, $commentId, null, $continuationToken);
        }
    } else {
        $result = getJSONFromHTML("https://www.youtube.com/watch?v=$videoId");
        $continuationToken = end($result['contents']['twoColumnWatchNextResults']['results']['results']['contents'])['itemSectionRenderer']['contents'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'];
        if($continuationToken != '') {
            return getAPI($videoId, $commentId, $order, $continuationToken, true);
        }
    }

    $answerItems = [];
    $items = $result['frameworkUpdates']['entityBatchUpdate']['mutations'];
    $isTopLevelComment = true;
    foreach ($items as $item) {
        $payload = $item['payload'];
        if (array_key_exists('engagementToolbarStateEntityPayload', $payload)) {
            $answerItems[$item['entityKey']]['snippet']['topLevelComment']['snippet']['creatorHeart'] = $payload['engagementToolbarStateEntityPayload']['heartState'] == 'TOOLBAR_HEART_STATE_HEARTED';
        }
        if (!array_key_exists('commentEntityPayload', $payload)) {
            continue;
        }
        $comment = $payload['commentEntityPayload'];
        $properties = $comment['properties'];
        $author = $comment['author'];
        $toolbar = $comment['toolbar'];
        $publishedAt = $properties['publishedTime'];
        $publishedAt = str_replace(' (edited)', '', $publishedAt, $count);
        $internalSnippet = [
            'content' => $properties['content']['content'],
            'publishedAt' => $publishedAt,
            'wasEdited' => $count > 0,
            'authorChannelId' => $author['channelId'],
            'authorHandle' => $author['displayName'],
            'authorName' => str_replace('❤ by ', '', $toolbar['heartActiveTooltip']),
            'authorAvatar' => $comment['avatar']['image']['sources'][0],
            'isCreator' => $author['isCreator'],
            'isArtist' => $author['isArtist'],
            'likeCount' => getIntValue($toolbar['likeCountLiked']),
            'totalReplyCount' => intval($toolbar['replyCount']),
            'videoCreatorHasReplied' => false,
            'isPinned' => false,
        ];

        //$replies = $commentThread['replies'];
        $commentId = $properties['commentId'];
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
        $answerItems[$properties['toolbarStateKey']] = $answerItem;
    }
    foreach ($result['onResponseReceivedEndpoints'][1]['reloadContinuationItemsCommand']['continuationItems'] as $item) {
        $commentThreadRenderer = $item['commentThreadRenderer'];
        $toolbarStateKey = $commentThreadRenderer['commentViewModel']['commentViewModel']['toolbarStateKey'];
        // How to avoid repeating path?
        if (doesPathExist($commentThreadRenderer, 'replies/commentRepliesRenderer/viewRepliesCreatorThumbnail')) {
            $answerItems[$toolbarStateKey]['snippet']['topLevelComment']['snippet']['videoCreatorHasReplied'] = true;
        }
        if (doesPathExist($commentThreadRenderer, 'commentViewModel/commentViewModel/pinnedText')) {
            $answerItems[$toolbarStateKey]['snippet']['topLevelComment']['snippet']['isPinned'] = true;
        }
    }
    $answerItems = array_values($answerItems);

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
