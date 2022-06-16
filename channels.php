<?php

    // StackOverflow source: https://stackoverflow.com/a/71067222/7123660
    $channelsTests = [['snippet&forUsername=FolkartTr', 'items/0/id', 'UCnS--2e1yzQCm5r4ClrMJBg']];

    include_once 'common.php';

    $realOptions = ['snippet', 'premieres', 'about'];

    // really necessary ?
    foreach ($realOptions as $realOption) {
        $options[$realOption] = false;
    }

    if (isset($_GET['part']) && (isset($_GET['forUsername']) || isset($_GET['id']))) {
        $part = $_GET['part'];
        $parts = explode(',', $part, count($realOptions));
        foreach ($parts as $part) {
            if (!in_array($part, $realOptions)) {
                die('invalid part ' . $part);
            } else {
                $options[$part] = true;
            }
        }
        $id = '';
        if (isset($_GET['forUsername'])) {
            $forUsername = $_GET['forUsername'];
            if (!isUsername($forUsername)) { // what's minimal length ?
                die('invalid forUsername');
            }
            $opts = [
                "http" => [
                    "header" => 'Cookie: CONSENT=YES+'
                ]
            ];
            $result = getJSONFromHTML('https://www.youtube.com/c/' . $forUsername . '/about', $opts);
            $id = $result['header']['c4TabbedHeaderRenderer']['channelId'];
        } else {
            $id = $_GET['id'];
            if (!isChannelId($id)) {
                die('invalid id'); // could directly die within the function
            }
        }
        echo getAPI($id);
    }

    function getItem($id)
    {
        global $options;
        $item = [
            'kind' => 'youtube#video',
            'etag' => 'NotImplemented',
            'id' => $id
        ];

        if ($options['premieres']) {
            $premieres = [];
            $result = getJSONFromHTML('https://www.youtube.com/channel/' . $id);
            $subItems = $result['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['shelfRenderer']['content']['horizontalListRenderer']['items'];
            foreach ($subItems as $subItem) {
                $subItem = $subItem['gridVideoRenderer'];
                if (array_key_exists('upcomingEventData', $subItem)) {
                    foreach (['navigationEndpoint', 'menu', 'trackingParams', 'thumbnailOverlays'] as $toRemove) {
                        unset($subItem[$toRemove]);
                    }
                    array_push($premieres, $subItem);
                }
            }
            $item['premieres'] = $premieres;
        }

        if ($options['about']) {
            $result = getJSONFromHTML('https://www.youtube.com/channel/' . $id . '/about');
            $linksCommon = $result['header']['c4TabbedHeaderRenderer']['headerLinks']['channelHeaderLinksRenderer'];
            $linksObjects = array_merge($linksCommon['primaryLinks'], (array)$linksCommon['secondaryLinks']);
            $links = [];
            foreach ($linksObjects as $linkObject) {
                $link = [];
                $link['url'] = $linkObject['navigationEndpoint']['urlEndpoint']['url'];
                $link['thumbnail'] = $linkObject['icon']['thumbnails'][0]['url'];
                $link['title'] = $linkObject['title']['simpleText'];
                array_push($links, $link);
            }
            $about['links'] = $links;
            $item['about'] = $about;
        }

        return $item;
    }

    function getAPI($id)
    {
        $items = [];
        array_push($items, getItem($id));

        $answer = [
            'kind' => 'youtube#channelListResponse',
            'etag' => 'NotImplemented',
            'items' => $items
        ];
        // should add in some way the pageInfo ?

        return json_encode($answer, JSON_PRETTY_PRINT);
    }
