<?php

if(isset($_GET['part'], $_GET['playlistId']))
{
	$part = $_GET['part'];
	if(!in_array($part, ['snippet']))
		die('invalid part');
	$playlistId = $_GET['playlistId'];
	if(preg_match('/^[a-zA-Z0-9-_]+$/', $playlistId) !== 1)
        die('invalid playlistId');
	$continuationToken = '';
	if(isset($_GET['pageToken']))
	{
		$continuationToken = $_GET['pageToken'];
		if(!preg_match('/^[A-Za-z0-9=]+$/', $continuationToken))
			die('invalid continuationToken');
	}
	echo getAPI($playlistId, $continuationToken);
}

function getAPI($playlistId, $continuationToken)
{
	$continuationTokenProvided = $continuationToken != '';
	$http = [];
	$url = '';
	if($continuationTokenProvided)
	{
		$url = 'https://www.youtube.com/youtubei/v1/browse?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8';
		$rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"2.2022011"}},"continuation":"' . $continuationToken . '"}';
		$http['method'] = 'POST';
		$http['header'] = 'Content-Type: application/json';
		$http['content'] = $rawData;
	}
	else
	{
		$url = 'https://www.youtube.com/playlist?list=' . $playlistId;
		$http['header'] = 'Cookie: CONSENT=YES+';
	}

	$options = [
		'http' => $http
	];

	$context = stream_context_create($options);

	$res = file_get_contents($url, false, $context);

	if(!$continuationTokenProvided)
		$res = explode(';', explode('">var ytInitialData = ', $res)[1])[0];

	// mr https://stackoverflow.com/users/7838847/hypnotizd ne veut pas dépenser plus de quota...

	$result = json_decode($res, true);
	$answerItems = [];
	$items = $continuationTokenProvided ? $result['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems'] : $result['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['playlistVideoListRenderer']['contents']; 
	$itemsCount = count($items);
	for($itemsIndex = 0; $itemsIndex < $itemsCount - ($continuationTokenProvided ? 1 : 0); $itemsIndex++)
	{
		$item = $items[$itemsIndex];
		$gridVideoRenderer = $item['playlistVideoRenderer'];
		$videoId = $gridVideoRenderer['videoId'];
		$titleObject = $gridVideoRenderer['title'];
		$title = $titleObject['runs'][0]['text'];
		$publishedAtRaw = $titleObject['accessibility']['accessibilityData']['label'];

		if($continuationTokenProvided)
			$publishedAtStr = str_replace('ago', '', $publishedAtRaw);
		else
		{
			$publishedAtParts = explode(' il y a ', $publishedAtRaw);
			$publishedAtStr = $publishedAtParts[count($publishedAtParts) - 1]/*just taking 1 could allow remote code execution*/; // why french :O ?
		}
		$publishedAtStr = str_replace($continuationTokenProvided ? 'seconds' : 'secondes', '* 1 +', $publishedAtStr);
		$publishedAtStr = str_replace($continuationTokenProvided ? 'second' : 'seconde', '* 1 +', $publishedAtStr);
		$publishedAtStr = str_replace('minutes', '* 60 +', $publishedAtStr);
		$publishedAtStr = str_replace('minute', '* 60 +', $publishedAtStr);
		$publishedAtStr = str_replace($continuationTokenProvided ? 'hours' : 'heures', '* 3600 +', $publishedAtStr);
		$publishedAtStr = str_replace($continuationTokenProvided ? 'hour' : 'heure', '* 3600 +', $publishedAtStr);
		$publishedAtStr = str_replace($continuationTokenProvided ? 'days' : 'jours', '* 86400 +', $publishedAtStr);
		$publishedAtStr = str_replace($continuationTokenProvided ? 'day' : 'jour', '* 86400 +', $publishedAtStr);
		$publishedAtStr = str_replace($continuationTokenProvided ? 'weeks' : 'semaines', '* 604800 +', $publishedAtStr);
		$publishedAtStr = str_replace($continuationTokenProvided ? 'week' : 'semaine', '* 604800 +', $publishedAtStr);
		$publishedAtStr = str_replace($continuationTokenProvided ? 'months' : 'mois', '* 2592000 +', $publishedAtStr); // not sure
		if($continuationTokenProvided)
			$publishedAtStr = str_replace('month', '* 2592000 +', $publishedAtStr); // not sure
		$publishedAtStr = str_replace($continuationTokenProvided ? 'years' : 'ans', '* 31104000 +', $publishedAtStr); // not sure
		if($continuationTokenProvided)
			$publishedAtStr = str_replace('year', '* 31104000 +', $publishedAtStr); // not sure
		$publishedAtStr = substr($publishedAtStr, 0, strlen($publishedAtStr) - 2);
		$publishedAtStr = str_replace(' ', '', $publishedAtStr); // "security"
		$publishedAtStr = preg_replace('/[[:^print:]]/', '', $publishedAtStr);
		if($continuationTokenProvided)
		{
			$publishedAtStrLen = strlen($publishedAtStr);
			for($publishedAtStrIndex = $publishedAtStrLen - 1; $publishedAtStrIndex >= 0; $publishedAtStrIndex--)
			{
				$publishedAtChar = $publishedAtStr[$publishedAtStrIndex];
				if(!(strpos('+*0123456789', $publishedAtChar) !== false))
				{
					$publishedAtStr = substr($publishedAtStr, $publishedAtStrIndex + 1, $publishedAtStrLen - $publishedAtStrIndex - 1);
					break;
				}
			}
		}
		$publishedAt = time() - eval('return ' . $publishedAtStr . ';'); // could check if only +,*,digits
		// the time is not perfectly accurate this way
		// warning releasing source code may show security breaches
		$answerItem = [
			'kind' => 'youtube#playlistItem',
			'etag' => 'NotImplemented',
			'snippet' => [
				'publishedAt' => $publishedAt,
				'title' => $title,
				'resourceId' => [
					'kind' => 'youtube#video',
					'videoId' => $videoId
				]
			]
		];
		array_push($answerItems, $answerItem);
	}
	$nextContinuationToken = str_replace('%3D', '=', $items[100]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']); // it doesn't seem random but hard to reverse-engineer
	$answer = [
		'kind' => 'youtube#searchListResponse',
		'etag' => 'NotImplemented'
	];
	// order matter or could afterwards sort by an (official YT API) arbitrary order (not alphabetical)
	// seems to be this behavior with the official API
	if($nextContinuationToken != '')
		$answer['nextPageToken'] = $nextContinuationToken;
	$answer['items'] = $answerItems;

	return json_encode($answer, JSON_PRETTY_PRINT);
}

?>
