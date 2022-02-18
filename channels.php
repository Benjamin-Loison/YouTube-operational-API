<?php

	$realOptions = ['snippet'];

	// really necessary ?
	foreach($realOptions as $realOption)
		$options[$realOption] = false;

	if(isset($_GET['part'], $_GET['forUsername']))
	{
		$part = $_GET['part'];
		$parts = explode(',', $part, count($realOptions));
		foreach($parts as $part)
			if(!in_array($part, $realOptions))
				die('invalid part ' . $part);
			else
				$options[$part] = true;
		$forUsername = $_GET['forUsername'];
		if(preg_match('/^[a-zA-Z0-9]+$/', $forUsername) !== 1) // what's minimal length ?
			die('invalid forUsername');
		echo getAPI($forUsername);
	}

	function getItem($forUsername)
	{
		global $options;
		if($options['snippet'])
		{
			$opts = [
            	"http" => [
                	"header" => 'Cookie: CONSENT=YES+'
            	]
        	];
        	$context = stream_context_create($opts);
        	$res = file_get_contents('https://www.youtube.com/c/' . $forUsername . '/about', false, $context);
			$res = explode(';</script>', explode('">var ytInitialData = ', $res)[1])[0]; // otherwise having troubles with people using ';' in their channel description
        	$result = json_decode($res, true);
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

?>
