<?php

	function str_contains($haystack, $needle)
	{
		return strpos($haystack, $needle) !== false;
	}

	$realOptions = ['status', 'contentDetails', 'music'];

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
			if(preg_match('/^[a-zA-Z0-9-_]{11}$/', $realId) !== 1)
				die('invalid id');
		echo getAPI($realIds);
	}

	function getJSON($rawData, $music = false)
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
		$context = stream_context_create($opts);
		$res = file_get_contents('https://' . ($music ? 'music' : 'www') . '.youtube.com/youtubei/v1/player?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8', false, $context);
		return json_decode($res, true);
	}

	function getItem($id)
	{
		global $options;
		$result = '';
		if($options['status'] || $options['contentDetails'])
		{
			$rawData = '{"videoId":"' . $id . '","context":{"client":{"clientName":"WEB_EMBEDDED_PLAYER","clientVersion":"1.2022012"}}}';

			$result = getJSON($rawData);
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
			$rawData = '{"videoId":"' . $id . '","context":{"client":{"clientName":"WEB_REMIX","clientVersion":"1.2022013"}}}';
			$resultMusic = getJSON($rawData, true);
			$music = [
            	'available' => $resultMusic['playabilityStatus']['status'] === "OK"
        	];
			$item['music'] = $music;
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
