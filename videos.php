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

	$realOptions = ['status', 'contentDetails', 'music', 'short'];

	// really necessary ?
	foreach($realOptions as $realOption)
		$options[$realOption] = false;

	if(isset($_GET['part'], $_GET['id']))
	{
		$part = $_GET['part'];
		$parts = explode(',', $part, count($realOptions));
		foreach($parts as $part)
			if(!in_array($part, $realOptions))
				die('invalid part ' . $part);
			else
				$options[$part] = true;
		$ids = $_GET['id'];
		$realIds = str_contains($ids, ',') ? explode(',', $ids, 50) : [$ids];
		if(count($realIds) == 0)
			die('invalid id');
		foreach($realIds as $realId)
			if(!isVideoId($realId))
				die('invalid id');
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
