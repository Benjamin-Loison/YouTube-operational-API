<?php

    // StackOverflow source: https://stackoverflow.com/a/71067222/7123660
    $channelsTests = [['snippet&forUsername=FolkartTr', 'items/0/id', 'UCnS--2e1yzQCm5r4ClrMJBg']];

    include_once 'common.php';

    $realOptions = ['snippet'];

    // really necessary ?
    foreach ($realOptions as $realOption) {
        $options[$realOption] = false;
    }

    if (isset($_GET['part'], $_GET['forUsername'])) {
        $part = $_GET['part'];
        $parts = explode(',', $part, count($realOptions));
        foreach ($parts as $part) {
            if (!in_array($part, $realOptions)) {
                die('invalid part ' . $part);
            } else {
                $options[$part] = true;
            }
        }
        $forUsername = $_GET['forUsername'];
        if (!isUsername($forUsername)) { // what's minimal length ?
            die('invalid forUsername');
        }
        echo getAPI($forUsername);
    }

    function getItem($forUsername)
    {
        global $options;
        if ($options['snippet']) {
            $opts = [
                "http" => [
                    "header" => 'Cookie: CONSENT=YES+'
                ]
            ];
            $result = getJSONFromHTML('https://www.youtube.com/c/' . $forUsername . '/about', $opts);
            $id = $result['header']['c4TabbedHeaderRenderer']['channelId'];
        }

        $item = [
            'kind' => 'youtube#video',
            'etag' => 'NotImplemented',
            'id' => $id
        ];

        return $item;
    }

    function getAPI($forUsername)
    {
        $items = [];
        array_push($items, getItem($forUsername));

        $answer = [
            'kind' => 'youtube#channelListResponse',
            'etag' => 'NotImplemented',
            'items' => $items
        ];
        // should add in some way the pageInfo ?

        return json_encode($answer, JSON_PRETTY_PRINT);
    }
