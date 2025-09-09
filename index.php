<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <title>YouTube operational API</title>
        <style>
            body {
                max-width: 73%;
                margin: 5% auto;
                font-family: sans-serif;
                color: #444;
                padding: 0;
            }
            h1,
            h2,
            h3 {
                line-height: 1.2;
            }
            p {
                word-break: break-word;
            }
            @media (prefers-color-scheme: dark) {
                body {
                    color: #c9d1d9;
                    background: #0d1117;
                }
                a:link {
                    color: #58a6ff;
                }
                a:visited {
                    color: #8e96f0;
                }
            }
        </style>
    </head>
    <body>
<?php

    include_once 'common.php';

    function url($url, $name = '')
    {
        if ($name === '') {
            $name = $url;
        }
        return "<a href=\"$url\">$name</a>";
    }

    function yt()
    {
        echo '<a href="https://developers.google.com/youtube/v3">YouTube Data API v3</a>';
    }

    function getUrl($parameters)
    {
        return urldecode(http_build_query(array_combine(array_keys($parameters), array_map(fn($parameterValue) => gettype($parameterValue) === 'string' ? $parameterValue : implode(',', $parameterValue), array_values($parameters)))));
    }

    function feature($feature)
    {
        $suburl = "$feature[0]/list";
        $webpage = explode('/', $suburl, 2)[0];
        $url = getUrl($feature[1]) . (count($feature) >= 3 ? '(&' . getUrl($feature[2]) . ')' : '');
        $name = ucfirst(str_replace('/', ': ', $suburl));
        echo "<p>Based on <a href=\"https://developers.google.com/youtube/v3/docs/$suburl\">$name</a>: " . url(WEBSITE_URL . "$webpage?$url") . '</p>';
    }

    $features = [
        [
            'channels',
            [
                'part' => [
                    'status',
                    'upcomingEvents',
                    'shorts',
                    'community',
                    'channels',
                    'about',
                    'approval',
                    'playlists',
                    'snippet',
                    'membership',
                    'popular',
                    'recent',
                    'letsPlay',
                ],
                'cId' => 'C_ID',
                'id' => 'CHANNEL_ID',
                'handle' => 'HANDLE',
                'forUsername' => 'USERNAME',
                'raw' => 'RAW',
                'order' => 'viewCount',
            ],
            [
                'pageToken' => 'PAGE_TOKEN',
            ],
        ],
        [
            'commentThreads',
            [
                'part' => [
                    'snippet',
                    'replies',
                ],
                'id' => 'COMMENT_ID',
                'videoId' => 'VIDEO_ID',
                'order' => [
                    'relevance',
                    'time',
                ],
            ],
            [
                'pageToken' => 'PAGE_TOKEN',
            ],
        ],
        [
            'playlists',
            [
                'part' => [
                    'snippet',
                    'statistics',
                ],
                'id' => 'PLAYLIST_ID',
            ],
        ],
        [
            'playlistItems',
            [
                'part' => [
                    'snippet',
                ],
                'playlistId' => 'PLAYLIST_ID',
            ],
            [
                'pageToken' => 'PAGE_TOKEN',
            ],
        ],
        [
            'search',
            [
                'part' => [
                    'id',
                    'snippet',
                ],
                'q' => 'QUERY',
                'channelId' => 'CHANNEL_ID',
                'eventType' => 'upcoming',
                'hashtag' => 'HASH_TAG',
                'type' => [
                    'video',
                    'short',
                ],
                'order' => [
                    'viewCount',
                    'relevance',
                ],
            ],
            [
                'pageToken' => 'PAGE_TOKEN',
            ],
        ],
        [
            'videos',
            [
                'part' => [
                    'id',
                    'status',
                    'contentDetails',
                    'music',
                    'short',
                    'impressions',
                    'musics',
                    'isPaidPromotion',
                    'isPremium',
                    'isMemberOnly',
                    'mostReplayed',
                    'qualities',
                    'captions',
                    'chapters',
                    'isOriginal',
                    'isRestricted',
                    'snippet',
                    'clip',
                    'activity',
                    'explicitLyrics',
                    'statistics',
                ],
                'id' => 'VIDEO_ID',
                'clipId' => 'CLIP_ID',
                'SAPISIDHASH' => 'YOUR_SAPISIDHASH',
            ],
        ]
    ];

?>

<h1>YouTube operational API works when <?php yt(); ?> fails.</h1>

<h2>Current implemented features:</h2>
<?php

    foreach ($features as $feature) {
        feature($feature);
    }

    $features = [
        [
            'community',
            [
                'part' => [
                    'snippet',
                ],
                'id' => 'POST_ID',
                'channelId' => 'CHANNEL_ID',
                'order' => [
                    'relevance',
                    'time',
                ],
            ],
        ],
        [
            'lives',
            [
                'part' => [
                    'donations',
                    'sponsorshipGifts',
                    'memberships',
                    'poll',
                ],
                'id' => 'VIDEO_ID',
            ],
        ],
        [
            'liveChats',
            [
                'part' => [
                    'snippet',
                    'participants',
                ],
                'id' => 'VIDEO_ID',
                'time' => 'TIME_MS',
            ],
        ],
    ];

    foreach ($features as $feature) {
        echo "<p>" . url(WEBSITE_URL . "$feature[0]?" . getUrl($feature[1])) . "</p>";
    }

?>

<h2>Make <?php yt(); ?> request WITHOUT ANY KEY:</h2>

<p>To make <strong>ANY <?php yt(); ?> request WITHOUT ANY KEY/USING YOUR QUOTA</strong>, you can use: <?php $noKey = 'noKey'; echo url(WEBSITE_URL . "$noKey/YOUR_REQUEST"); ?></p>
<p>For instance with <code>YOUR_REQUEST</code> being <code><?php $example = 'videos?part=snippet&id=VIDEO_ID'; echo $example; ?></code> you can use: <?php echo url(WEBSITE_URL . "$noKey/$example"); ?> instead of <?php echo url("https://www.googleapis.com/youtube/v3/$example"); ?></p>
<p>I may add in the future limitation per IP etc if the quota need to be better shared among the persons using this API.</p>
<?php

    $keysCount = file_exists(KEYS_FILE) ? substr_count(file_get_contents(KEYS_FILE), "\n") + 1 : 0;

?>
<p>Currently this service is <a href='keys.php'>powered by <?php echo $keysCount; ?> keys</a>.</p>
<script>

function share() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            alert(xhttp.responseText);
            youtubeDataAPIV3KeyInput.value = '';
        }
    };
    var youtubeDataAPIV3KeyInput = document.getElementById('youtubeDataAPIV3Key');
    const key = youtubeDataAPIV3KeyInput.value;
    xhttp.open('GET', `addKey.php?key=${key}`);
    xhttp.send();
}

</script>

<?php $YOUTUBE_DATA_API_V3_KEY_LENGTH = 39; ?>
Share your YouTube Data API v3 key to power the no-key service: <input type="text" id="youtubeDataAPIV3Key" placeholder="AIzaSy..." <?php printf('minlength="%s" maxlength="%s" size="%s"', $YOUTUBE_DATA_API_V3_KEY_LENGTH, $YOUTUBE_DATA_API_V3_KEY_LENGTH, $YOUTUBE_DATA_API_V3_KEY_LENGTH) ?>><button type="button" onClick="share()">share</button>

<h2>Open-source:</h2>
The source code is available on GitHub: <?php echo url('https://github.com/Benjamin-Loison/YouTube-operational-API'); ?>

<h2>Contact:</h2>
If a feature you are looking for which isn't working on <?php yt(); ?>, ask kindly with the below contact:<br/>
- <?php echo url('https://yt.lemnoslife.com/matrix', 'Matrix'); ?><br/>
- <?php echo url('https://yt.lemnoslife.com/discord', 'Discord'); ?>

<?php

    $version = $_ENV['VERSION'];
    if (!$version) {
        $ref = str_replace("\n", '', str_replace('ref: ', '', file_get_contents('.git/HEAD')));
        $hash = file_get_contents(".git/$ref");
        if ($hash !== false) {
            $version = "version: <a href=\"https://github.com/Benjamin-Loison/YouTube-operational-API/commit/$hash\">$hash</a>";
        } else {
            $version = 'an unknown version.';
        }
    }

    echo "<br/><br/>This instance (" . SERVER_NAME . ") uses $version";

?>

    </body>
</html>
