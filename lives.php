<?php

    header('Content-Type: application/json; charset=UTF-8');

    $liveTests = [];

    include_once 'common.php';

    $realOptions = ['donations', 'sponsorshipGifts', 'memberships'];

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

        $opts = [
            'http' => [
                'user_agent' => USER_AGENT,
                'header' => ['Accept-Language: en'],
            ]
        ];
        $result = getJSONFromHTML("https://www.youtube.com/live_chat?v=$id", $opts, 'window["ytInitialData"]', '');

        $item = [
            'kind' => 'youtube#video',
            'etag' => 'NotImplemented',
            'id' => $id
        ];

        $actions = $result['contents']['liveChatRenderer']['actions'];

        if ($options['donations']) {
            $donations = [];
            foreach ($actions as $action) {
                $donation = $action['addLiveChatTickerItemAction']['item']['liveChatTickerPaidMessageItemRenderer']['showItemEndpoint']['showLiveChatItemEndpoint']['renderer']['liveChatPaidMessageRenderer'];
                if ($donation != null) {
                    array_push($donations, $donation);
                }
            }
            $item['donations'] = $donations;
        }

        if ($options['sponsorshipGifts']) {
            function getCleanAuthorBadge($authorBadgeRaw)
            {
                $liveChatAuthorBadgeRenderer = $authorBadgeRaw['liveChatAuthorBadgeRenderer'];
                $authorBadge = [
                    'tooltip' => $liveChatAuthorBadgeRenderer['tooltip'],
                    'customThumbnail' => $liveChatAuthorBadgeRenderer['customThumbnail']['thumbnails']
                ];
                return $authorBadge;
            }

            function cleanMembershipOrSponsorship($raw, $isMembership) {
                $common = $isMembership ? $raw : $raw['header']['liveChatSponsorshipsHeaderRenderer'];
                $primaryText = implode('', array_map(fn($run) => $run['text'], $common[$isMembership ? 'headerPrimaryText' : 'primaryText']['runs']));
                $subText = $raw['headerSubtext']['simpleText'];

                $authorBadges = array_map('getCleanAuthorBadge', $common['authorBadges']);

                $clean = [
                    'id' => $raw['id'],
                    'timestamp' => intval($raw['timestampUsec']),
                    'authorChannelId' => $raw['authorExternalChannelId'],
                    'authorName' => $common['authorName']['simpleText'],
                    'authorPhoto' => $common['authorPhoto']['thumbnails'],
                    'primaryText' => $primaryText,
                    'subText' => $subText,
                    'authorBadges' => $authorBadges,
                ];
                return $clean;
            }

            $sponsorshipGifts = [];
            foreach ($actions as $action) {
                $sponsorshipGift = $action['addChatItemAction']['item']['liveChatSponsorshipsGiftPurchaseAnnouncementRenderer'];
                if ($sponsorshipGift != null)
                {
                    array_push($sponsorshipGifts, cleanMembershipOrSponsorship($sponsorshipGift, false));
                }
            }
            $item['sponsorshipGifts'] = $sponsorshipGifts;
        }

        if ($options['memberships']) {
            $memberships = [];
            foreach ($actions as $action) {
                $membership = $action['addChatItemAction']['item']['liveChatMembershipItemRenderer'];
                if ($membership != null)
                {
                    array_push($memberships, cleanMembershipOrSponsorship($membership, true));
                }
            }
            $item['memberships'] = $memberships;
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
