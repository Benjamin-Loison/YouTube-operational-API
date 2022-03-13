<?php

	// StackOverflow source: https://stackoverflow.com/questions/71457319

	include_once 'common.php';

	$realOptions = ['statistics'];

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
		$id = $_GET['id'];
		if(!isPlaylistId($id))
			die('invalid id');
		echo getAPI($id);
	}
	// could provide an error message if such fields aren't provided

	function getItem($id)
	{
		global $options;
		if($options['statistics'])
		{
			$opts = [
            	"http" => [
                	"header" => ['Cookie: CONSENT=YES+', 'Accept-Language: en']
            	]
        	];
        	$result = getJSONFromHTML('https://www.youtube.com/playlist?list=' . $id, $opts);
			$viewCount = $result['sidebar']['playlistSidebarRenderer']['items'][0]['playlistSidebarPrimaryInfoRenderer']['stats'][1]['simpleText'];
			if($viewCount === 'No views')
				$viewCount = 0;
			else
				$viewCount = str_replace(' views', '', str_replace(' view', '', str_replace(',', '', $viewCount))); // don't know if the 1 view case is useful
			$viewCount = intval($viewCount);
		}

		$item = [
            'kind' => 'youtube#playlist',
            'etag' => 'NotImplemented'
        ];

		if($options['statistics'])
		{
			$item['statistics'] = ['viewCount' => $viewCount];
		}

		return $item;
	}

	function getAPI($id)
	{
		$items = [];
		array_push($items, getItem($id));

    	$answer = [
        	'kind' => 'youtube#playlistListResponse',
        	'etag' => 'NotImplemented',
			'items' => $items
    	];

    	return json_encode($answer, JSON_PRETTY_PRINT);
	}

?>
