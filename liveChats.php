<?php

    header('Content-Type: application/json; charset=UTF-8');

    $liveTests = [];

    include_once 'common.php';

    $realOptions = [
        'snippet',
        'participants',
    ];

    foreach ($realOptions as $realOption) {
        $options[$realOption] = false;
    }

    if (isset($_GET['part'], $_GET['id']) && ($_GET['part'] != 'snippet' || isset($_GET['time']))) {
        $part = $_GET['part'];
        $parts = explode(',', $part, count($realOptions));
        foreach ($parts as $part) {
            if (!in_array($part, $realOptions)) {
                dieWithJsonMessage("Invalid part $part");
            } else {
                $options[$part] = true;
            }
        }

        if ($part == 'snippet' && !isPositiveInteger($_GET['time'])) {
            dieWithJsonMessage('Invalid time');
        }

        $ids = $_GET['id'];
        $realIds = str_contains($ids, ',') ? explode(',', $ids, 50) : [$ids];
        if (count($realIds) == 0) {
            dieWithJsonMessage('Invalid id');
        }
        foreach ($realIds as $realId) {
            if ((!isVideoId($realId))) {
                dieWithJsonMessage('Invalid id');
            }
        }

        echo getAPI($realIds);
    } else if(!test()) {
        dieWithJsonMessage('Required parameters not provided');
    }

    function getItem($id)
    {
        global $options;

        $result = getJSONFromHTML("https://www.youtube.com/watch?v=$id");
        $continuation = $result['contents']['twoColumnWatchNextResults']['conversationBar']['liveChatRenderer']['continuations'][0]['reloadContinuationData']['continuation'];
        
        $rawData = [
            'context' => [
                'client' => [
                    'clientName' => 'WEB',
                    'clientVersion' => MUSIC_VERSION
                ]
            ],
            'continuation' => strval($continuation),
            'currentPlayerState' => [
                'playerOffsetMs' => $_GET['time']
            ]
        ];

        $opts = [
            'http' => [
                'header' => 'Content-Type: application/json',
                'method'  => 'POST',
                'content' => json_encode($rawData),
            ]
        ];
        $result = getJSON('https://www.youtube.com/youtubei/v1/live_chat/get_live_chat_replay?key=' . UI_KEY, $opts);

        $item = [
            'kind' => 'youtube#video',
            'etag' => 'NotImplemented',
            'id' => $id
        ];

        if ($options['snippet']) {
            $snippet = [];
            $actions = $result['continuationContents']['liveChatContinuation']['actions'];
            foreach ($actions as $action) {
                $replayChatItemAction = $action['replayChatItemAction'];
                $liveChatTextMessageRenderer = $replayChatItemAction['actions'][0]['addChatItemAction']['item']['liveChatTextMessageRenderer'];
                if ($liveChatTextMessageRenderer != null) {
                    $message = [
                        'id' => urldecode($liveChatTextMessageRenderer['id']),
                        'message' => $liveChatTextMessageRenderer['message']['runs'],
                        'authorName' => $liveChatTextMessageRenderer['authorName']['simpleText'],
                        'authorThumbnails' => $liveChatTextMessageRenderer['authorPhoto']['thumbnails'],
                        'timestampAbsoluteUsec' => intval($liveChatTextMessageRenderer['timestampUsec']),
                        'authorChannelId' => $liveChatTextMessageRenderer['authorExternalChannelId'],
                        'timestamp' => getIntFromDuration($liveChatTextMessageRenderer['timestampText']['simpleText']),
                        'videoOffsetTimeMsec' => intval($replayChatItemAction['videoOffsetTimeMsec'])
                    ];
                    array_push($snippet, $message);
                }
            }
            $item['snippet'] = $snippet;
        }

        if ($options['participants']) {
            $participants = [];
            $opts = [
                'http' => [
                    'header' => 'User-Agent: ' . USER_AGENT,
                ]
            ];

            $result = getJSONFromHTML("https://www.youtube.com/live_chat?continuation=$continuation", $opts, 'window["ytInitialData"]', '');
            $participants = array_slice($result['continuationContents']['liveChatContinuation']['actions'], 1);
            $item['participants'] = $participants;
        }

        return $item;
    }

    function getAPI($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            array_push($items, getItem($id));
        }

        $answer = [
            'kind' => 'youtube#videoListResponse',
            'etag' => 'NotImplemented',
            'items' => $items
        ];

        return json_encode($answer, JSON_PRETTY_PRINT);
    }
