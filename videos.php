<?php

	// StackOverflow contentDetails source: https://stackoverflow.com/a/70908689/7123660
	// StackOverflow status source: https://stackoverflow.com/a/70894799/7123660
	// StackOveflow music source: https://stackoverflow.com/a/71012426/7123660
	$videosTests = [['contentDetails&id=g5xNzUA5Qf8', 'items/0/contentDetails/duration', '213'],
	                ['status&id=J8ZVxDK11Jo', 'items/0/status/embeddable', false],
					['status&id=g5xNzUA5Qf8', 'items/0/status/embeddable', true], // could allow subarray for JSON check in response likewise in a single request can check several features
					['music&id=Xge20AqKSRE', 'items/0/music/available', false],
					['music&id=ntG3GQdY_Ok', 'items/0/music/available', true]];

	include_once 'common.php';

	$realOptions = ['id', 'status', 'contentDetails', 'music', 'short', 'impressions', 'containsMusic', 'isPaidPromotion', 'isPremium', 'isMemberOnly']; // could load index.php from that

	// really necessary ?
	foreach($realOptions as $realOption)
		$options[$realOption] = false;

	if(isset($_GET['part']) && (isset($_GET['id']) || isset($_GET['clipId'])))
	{
		$part = $_GET['part'];
		$parts = explode(',', $part, count($realOptions));
		foreach($parts as $part)
			if(!in_array($part, $realOptions))
				die('invalid part ' . $part);
			else
				$options[$part] = true;

		$isClip = isset($_GET['clipId']);
		$field = $isClip ? 'clipId' : 'id';
		$ids = $_GET[$field];
		$realIds = str_contains($ids, ',') ? explode(',', $ids, 50) : [$ids];
		if(count($realIds) == 0)
			die('invalid id');
		foreach($realIds as $realId)
			if((!$isClip && !isVideoId($realId)) && !isClipId($realId))
				die('invalid ' . $field);

		if($options['impressions'] && (!isset($_GET['SAPISIDHASH']) || !isSAPISIDHASH($_GET['SAPISIDHASH'])))
			die('invalid SAPISIDHASH');
		echo getAPI($realIds);
	}

	function getJSONFunc($rawData, $music = false)
	{
		$headers = [
			"Content-Type: application/json"
		];
		if($music)
			array_push($headers, 'Referer: https://music.youtube.com');
		$opts = [
            "http" => [
                "method" => "POST",
                "header" => $headers,
                "content" => $rawData,
            ]
        ];
		return getJSON('https://' . ($music ? 'music' : 'www') . '.youtube.com/youtubei/v1/player?key=' . UI_KEY, $opts);
	}

	function getItem($id)
	{
		global $options;
		$result = '';
		if($options['status'] || $options['contentDetails'])
		{
			$rawData = '{"videoId":"' . $id . '","context":{"client":{"clientName":"WEB_EMBEDDED_PLAYER","clientVersion":"' . CLIENT_VERSION . '"}}}';

			$result = getJSONFunc($rawData);
		}

		$item = [
            'kind' => 'youtube#video',
            'etag' => 'NotImplemented',
            'id' => $id
        ];

		if($options['status'])
		{
			$status = [
            	'embeddable' => $result['playabilityStatus']['status'] === 'OK'
        	];
			$item['status'] = $status;
		}

		if($options['contentDetails'])
        {
            $contentDetails = [
                'duration' => $result['videoDetails']['lengthSeconds']
            ];
            $item['contentDetails'] = $contentDetails;
        }

		if($options['music'])
		{
			// music request doesn't provide embeddable info - could not make a request if only music and contentDetails
			$rawData = '{"videoId":"' . $id . '","context":{"client":{"clientName":"WEB_REMIX","clientVersion":"' . MUSIC_VERSION . '"}}}';
			$resultMusic = getJSONFunc($rawData, true);
			$music = [
            	'available' => $resultMusic['playabilityStatus']['status'] === "OK"
        	];
			$item['music'] = $music;
		}

		if($options['short'])
		{
			$short = [
				'available' => !isRedirection('https://www.youtube.com/shorts/' . $id)
			];
			$item['short'] = $short;
		}

		if($options['impressions'])
		{
			$headers = [
	            "x-origin: https://studio.youtube.com",
				"authorization: SAPISIDHASH " . $_GET['SAPISIDHASH'],
				"Content-Type:",
				"cookie: HSID=A4BqSu4moNA0Be1N9; SSID=AA0tycmNyGWo-Z_5v; APISID=a; SAPISID=zRbK-_14V7wIAieP/Ab_wY1sjLVrKQUM2c; SID=HwhYm6rJKOn_3R9oOrTNDJjpHIiq9Uos0F5fv4LPdMRSqyVHA1EDZwbLXo0kuUYAIN_MUQ."
    	    ];
			$rawData = '{"screenConfig":{"entity":{"videoId":"' . $id . '"}},"desktopState":{"tabId":"ANALYTICS_TAB_ID_REACH"}}';
        	$opts = [
            	"http" => [
                	"method" => "POST",
	                "header" => $headers,
    	            "content" => $rawData,
        	    ]
        	];
        	$json = getJSON('https://studio.youtube.com/youtubei/v1/analytics_data/get_screen?key=' . UI_KEY, $opts);
			$impressions = $json['cards'][0]['keyMetricCardData']['keyMetricTabs'][0]['primaryContent']['total']; 
			$item['impressions'] = $impressions;
		}

		if($options['containsMusic'])
		{
			$opts = [
				"http" => [
					"header" => 'Accept-Language: en'
				]
			];
			$json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id, $opts);
		    $containsMusic = $json['contents']['twoColumnWatchNextResults']['results']['results']['contents'][1]['videoSecondaryInfoRenderer']['metadataRowContainer']['metadataRowContainerRenderer']['rows'] !== null;
		    $item['containsMusic'] = $containsMusic;
		}

		if($options['id'] && isset($_GET['clipId']))
		{
			$json = getJSONFromHTML('https://www.youtube.com/clip/' . $id);
			$videoId = $json['currentVideoEndpoint']['watchEndpoint']['videoId'];
			$item['videoId'] = $videoId;
		}

		if($options['isPaidPromotion'])
		{
			$json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id, [], 'ytInitialPlayerResponse');
			$isPaidPromotion = array_key_exists('paidContentOverlay', $json);
			$item['isPaidPromotion'] = $isPaidPromotion;
		}

		if($options['isPremium'])
		{
			$json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id);
			$isPremium = array_key_exists('offerModule', $json['contents']['twoColumnWatchNextResults']['secondaryResults']['secondaryResults']);
			$item['isPremium'] = $isPremium;
		}

		if($options['isMemberOnly'])
		{
			$json = getJSONFromHTML('https://www.youtube.com/watch?v=' . $id, $opts);
			$isMemberOnly = array_key_exists('badges', $json['contents']['twoColumnWatchNextResults']['results']['results']['contents'][0]['videoPrimaryInfoRenderer']);
			$item['isMemberOnly'] = $isMemberOnly;
		}

		return $item;
	}

	function getAPI($ids)
	{
		$items = [];
		foreach($ids as $id)
			array_push($items, getItem($id));

    	$answer = [
        	'kind' => 'youtube#videoListResponse',
        	'etag' => 'NotImplemented',
			'items' => $items
    	];

    	return json_encode($answer, JSON_PRETTY_PRINT);
	}

?>
