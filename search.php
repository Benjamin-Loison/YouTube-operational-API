<?php

// copy YT perfectly (answers and arguments) - slower because not always everything from answer in one request for me
// make an API based on one request I receive involves one request on my side - more precise in terms of complexity
// can from this last model also just include "the interesting data" and nothing repetitive with the YouTube Data API v3, I mean that from the videoId we can get all details we want from the official API so maybe no need to repeat some here even if there are in the answer of my request

if(isset($_GET['part'], $_GET['channelId'], $_GET['order']))
{
	// TODO: check parameters no hack
	$part = $_GET['part'];
	if(!in_array($part, ['snippet']))
		die('invalid part');
	$channelId = $_GET['channelId'];
	
	if(preg_match('/^[a-zA-Z0-9-_]{24}$/', $channelId) !== 1)
    	die('invalid channelId');
	$order = $_GET['order'];
	if(!in_array($order, ['viewCount']))
		die('invalid order');
	$continuationToken = '';
	if(isset($_GET['pageToken']))
	{
		$continuationToken = $_GET['pageToken'];
		// what checks to do ?
		if(!preg_match('/^[A-Za-z0-9=]+$/', $continuationToken))
			die('invalid continuationToken');
	}
	echo getAPI($channelId, $order, $continuationToken);
}

function getAPI($channelId, $order, $continuationToken)
{
	$orderBase64 = 'EgZ2aWRlb3MYASAAMAE=';
	$continuationTokenProvided = $continuationToken != '';
	$rawData = '{"context":{"client":{"clientName":"WEB","clientVersion":"2.2022011"}},"' . ($continuationTokenProvided ? 'continuation":"' . $continuationToken : 'browseId":"' . $channelId . '","params":"' . $orderBase64) . '"}';
	$opts = [
    	"http" => [
        	"method" => "POST",
        	"header" => "Content-Type: application/json",
            "content" => $rawData,
    	]
	];

	$context = stream_context_create($opts);

	$res = file_get_contents('https://www.youtube.com/youtubei/v1/browse?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8', false, $context);

	$result = json_decode($res, true);
	// repeated on official API but not in UI requests
	//if(!$continuationTokenProvided)
	//	$regionCode = $result['topbar']['desktopTopbarRenderer']['countryCode'];
	$answerItems = [];
	$items = $continuationTokenProvided ? $result['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems'] : $result['contents']['twoColumnBrowseResultsRenderer']['tabs'][1]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['gridRenderer']['items'];
	$itemsCount = count($items);
	for($itemsIndex = 0; $itemsIndex < $itemsCount - 1; $itemsIndex++)
	{
		$item = $items[$itemsIndex];
		$gridVideoRenderer = $item['gridVideoRenderer'];
		$videoId = $gridVideoRenderer['videoId'];
		$title = $gridVideoRenderer['title']['runs'][0]['text'];
		$answerItem = [
			'kind' => 'youtube#searchResult',
			'etag' => 'NotImplemented',
			'id' => [
				'kind' => 'youtube#video',
				'videoId' => $videoId
			],
			'snippet' => [
				'channelId' => $channelId,
				'title' => $title
			]
		];
		array_push($answerItems, $answerItem);
	}
	$nextContinuationToken = str_replace('%3D', '=', $items[30]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']); // it doesn't seem random but hard to reverse-engineer
	$answer = [
		'kind' => 'youtube#searchListResponse',
		'etag' => 'NotImplemented'
	];
	// order matter or could afterwards sort by an (official YT API) arbitrary order (not alphabetical)
	// seems to be this behavior with the official API
	if($nextContinuationToken != '')
		$answer['nextPageToken'] = $nextContinuationToken;
	//if(!$continuationTokenProvided) // doesn't seem accurate
	//	$answer['regionCode'] = $regionCode;
	$answer['items'] = $answerItems;

	return json_encode($answer, JSON_PRETTY_PRINT);
}

?>
