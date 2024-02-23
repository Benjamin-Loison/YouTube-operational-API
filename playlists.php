<?php

    header('Content-Type: application/json; charset=UTF-8');

    $playlistsTests = [
        ['part=snippet&id=PL8wZFyWE1ZaI2HE7PYHvpx0_yv4oJjwAZ', 'items/0/snippet/title', '4,000 times the same video'],
        ['part=statistics&id=PL8wZFyWE1ZaI2HE7PYHvpx0_yv4oJjwAZ', 'items/0/statistics', ['videoCount' => 4_000]],
    ];

    include_once 'common.php';

	$realOptions = [
		'snippet',
		'statistics',
	];

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
    } else if(!test()) {
        dieWithJsonMessage('Required parameters not provided');
    }

    function getItem($id)
    {
        global $options;
        $result = getJSONFromHTMLForcingLanguage("https://www.youtube.com/playlist?list=$id");

        $item = [
            'kind' => 'youtube#playlist',
            'etag' => 'NotImplemented'
        ];

        if ($options['snippet']) {
            $title = $result['metadata']['playlistMetadataRenderer']['title'];
            $item['snippet'] = [
                'title' => $title
            ];
        }

        if ($options['statistics']) {
            $viewCount = $result['sidebar']['playlistSidebarRenderer']['items'][0]['playlistSidebarPrimaryInfoRenderer']['stats'][1]['simpleText'];
            $viewCount = getIntFromViewCount($viewCount);
            $videoCount = intval(str_replace(',', '', $result['header']['playlistHeaderRenderer']['numVideosText']['runs'][0]['text']));
            $item['statistics'] = [
                'viewCount' => $viewCount,
                'videoCount' => $videoCount
            ];
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
