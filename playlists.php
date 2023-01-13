<?php

    // StackOverflow source: https://stackoverflow.com/q/71457319

    include_once 'common.php';

    $realOptions = ['snippet', 'statistics'];

    // really necessary ?
    foreach ($realOptions as $realOption) {
        $options[$realOption] = false;
    }

    if (isset($_GET['part'], $_GET['id'])) {
        $part = $_GET['part'];
        $parts = explode(',', $part, count($realOptions));
        foreach ($parts as $part) {
            if (!in_array($part, $realOptions)) {
                die("invalid part $part");
            } else {
                $options[$part] = true;
            }
        }
        $ids = $_GET['id'];
        $realIds = str_contains($ids, ',') ? explode(',', $ids, 50) : [$ids];
        if (count($realIds) == 0) {
            die('invalid id');
        }
        foreach ($realIds as $realId) {
            if (!isPlaylistId($realId)) {
                die('invalid id');
            }
        }
        echo getAPI($realIds);
    }
    // could provide an error message if such fields aren't provided

    function getItem($id)
    {
        global $options;
        $opts = [
            "http" => [
                "header" => ['Cookie: CONSENT=YES+', 'Accept-Language: en']
            ]
        ];
        $result = getJSONFromHTML("https://www.youtube.com/playlist?list=$id", $opts);
        if ($options['snippet']) {
            $title = $result['metadata']['playlistMetadataRenderer']['title'];
        }
        if ($options['statistics']) {
            $viewCount = $result['sidebar']['playlistSidebarRenderer']['items'][0]['playlistSidebarPrimaryInfoRenderer']['stats'][1]['simpleText'];
            $viewCount = getIntFromViewCount($viewCount);
        }

        $item = [
            'kind' => 'youtube#playlist',
            'etag' => 'NotImplemented'
        ];

        if ($options['snippet']) {
            $item['snippet'] = ['title' => $title];
        }

        if ($options['statistics']) {
            $item['statistics'] = ['viewCount' => $viewCount];
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
            'kind' => 'youtube#playlistListResponse',
            'etag' => 'NotImplemented',
            'items' => $items
        ];

        return json_encode($answer, JSON_PRETTY_PRINT);
    }
