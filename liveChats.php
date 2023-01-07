<?php

    $liveTests = [];

    include_once 'common.php';

    $realOptions = ['snippet', 'participants'];

    foreach ($realOptions as $realOption) {
        $options[$realOption] = false;
    }

    if (isset($_GET['part'], $_GET['id']) && ($_GET['part'] != 'snippet' || isset($_GET['time']))) {
        $part = $_GET['part'];
        $parts = explode(',', $part, count($realOptions));
        foreach ($parts as $part) {
            if (!in_array($part, $realOptions)) {
                die("invalid part $part");
            } else {
                $options[$part] = true;
            }
        }

        if ($part == 'snippet' && !isPositiveInteger($_GET['time'])) {
            die('invalid time');
        }

        $ids = $_GET['id'];
        $realIds = str_contains($ids, ',') ? explode(',', $ids, 50) : [$ids];
        if (count($realIds) == 0) {
            die('invalid id');
        }
        foreach ($realIds as $realId) {
            if ((!isVideoId($realId))) {
                die('invalid id');
            }
        }

        echo getAPI($realIds);
    }

    function getItem($id)
    {
        global $options;

        $result = getJSONFromHTML("https://www.youtube.com/watch?v=$id");
        $continuation = $result['contents']['twoColumnWatchNextResults']['conversationBar']['liveChatRenderer']['continuations'][0]['reloadContinuationData']['continuation'];
        
        $rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"' . MUSIC_VERSION . '"}},"continuation":"' . $continuation . '","currentPlayerState":{"playerOffsetMs":"' . $_GET['time'] . '"}}';

        $opts = [
            "http" => [
                "header" => "Content-Type: application/json",
                'method'  => 'POST',
                "content" => $rawData,
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
                $snip = $action['replayChatItemAction']['actions'][0]['addChatItemAction']['item']['liveChatTextMessageRenderer'];
                if ($snip != null) {
                    array_push($snippet, $snip);
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
