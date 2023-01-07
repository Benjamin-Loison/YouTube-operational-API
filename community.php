<?php

include_once 'common.php';

$realOptions = ['snippet'];

foreach ($realOptions as $realOption) {
    $options[$realOption] = false;
}

if (isset($_GET['part'], $_GET['id'])) {
    $part = $_GET['part'];
    $parts = explode(',', $part, count($realOptions));
    foreach ($parts as $part) {
        if (!in_array($part, $realOptions)) {
            die("invalid part $part");
        } else {
            $options[$part] = true;
        }
    }

    $postId = $_GET['id'];
    if (!isPostId($postId)) {
        die('invalid postId');
    }
    echo getAPI($postId);
}

function getAPI($postId)
{
	$http = [
		'header' => ['Accept-Language: en']
	];

	$options = [
		'http' => $http
	];

	$result = getJSONFromHTML("https://www.youtube.com/post/$postId", $options);
	$content = $result['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0];
	$post = getCommunityPostFromContent($content);

    $answerItem = [
        'kind' => 'youtube#community',
        'etag' => 'NotImplemented',
        'id' => $postId,
        'snippet' => $post
    ];
    $answer = [
        'kind' => 'youtube#communityListResponse',
        'etag' => 'NotImplemented'
    ];
    $answer['items'] = [$answerItem];

    return json_encode($answer, JSON_PRETTY_PRINT);
}
