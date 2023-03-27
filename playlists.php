<?php

    header('Content-Type: application/json; charset=UTF-8');

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
                dieWithJsonMessage("Invalid part $part");
            } else {
                $options[$part] = true;
            }
        }
        $ids = $_GET['id'];
        $realIds = str_contains($ids, ',') ? explode(',', $ids, 50) : [$ids];
        if (count($realIds) == 0) {
            dieWithJsonMessage('Invalid id');
        }
        foreach ($realIds as $realId) {
            if (!isPlaylistId($realId)) {
                dieWithJsonMessage('Invalid id');
            }
        }
        echo getAPI($realIds);
    } else {
        dieWithJsonMessage("Required parameters not provided");
    }

    function getItem($id)
    {
        global $options;
        $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/playlist?list=$id");
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
