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

    function feature($feature)
    {
        $suburl = "$feature[0]/list";
        $webpage = explode('/', $suburl, 2)[0];
        $url = $feature[1];
        $name = ucfirst(str_replace('/', ': ', $suburl));
        echo "<p>Based on <a href=\"https://developers.google.com/youtube/v3/docs/$suburl\">$name</a>: " . url(WEBSITE_URL . "$webpage?part=$url") . '</p>';
    }

    $features = [['channels', 'status,upcomingEvents,shorts,community,channels,about,approval,playlists,snippet,membership&cId=C_ID&id=CHANNEL_ID&handle=HANDLE&forUsername=USERNAME&order=viewCount(&pageToken=PAGE_TOKEN)'],
                 ['commentThreads', 'snippet,replies&videoId=VIDEO_ID&order=relevance,time(&pageToken=PAGE_TOKEN)'],
                 ['playlists', 'snippet,statistics&id=PLAYLIST_ID'],
                 ['playlistItems', 'snippet&playlistId=PLAYLIST_ID(&pageToken=PAGE_TOKEN)'],
                 ['search', 'id,snippet&q=QUERY&channelId=CHANNEL_ID&eventType=upcoming&hashtag=HASH_TAG&type=video&order=viewCount,relevance(&pageToken=PAGE_TOKEN)'],
                 ['videos', 'id,status,contentDetails,music,short,impressions,musics,isPaidPromotion,isPremium,isMemberOnly,mostReplayed,qualities,chapters,isOriginal,isRestricted,snippet,clip,activity&id=VIDEO_ID&clipId=CLIP_ID&SAPISIDHASH=YOUR_SAPISIDHASH']];

?>

<h1>YouTube operational API works when <?php yt(); ?> fails.</h1>

<h2>Current implemented features:</h2>
<?php

    foreach ($features as $feature) {
        feature($feature);
    }

    $features = [['community', 'snippet&id=POST_ID&order=relevance,time'],
                 ['lives', 'donations&id=VIDEO_ID'],
                 ['liveChats', 'snippet,participants&id=VIDEO_ID&time=TIME_MS']];

    foreach ($features as $feature) {
        echo "<p>" . url(WEBSITE_URL . "$feature[0]?part=$feature[1]") . "</p>";
    }

?>

<h2>Make <?php yt(); ?> request WITHOUT ANY KEY:</h2>

<p>To make <strong>ANY <?php yt(); ?> request WITHOUT ANY KEY/USING YOUR QUOTA</strong>, you can use: <?php $noKey = 'noKey'; echo url(WEBSITE_URL . "$noKey/YOUR_REQUEST"); ?></p>
<p>For instance you can use: <?php $example = 'videos?part=snippet&id=VIDEO_ID'; echo url(WEBSITE_URL . "$noKey/$example"); ?> instead of <?php echo url("https://www.googleapis.com/youtube/v3/$example"); ?></p>
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
- <?php echo url('https://matrix.to/#/#youtube-operational-api:matrix.org', 'Matrix'); ?><br/>
- <?php echo url('https://discord.gg/pDzafhGWzf', 'Discord'); ?>

<?php

    $hash = file_get_contents('.git/refs/heads/main');
    if ($hash !== false) {
        echo "<br/><br/>This instance (" . SERVER_NAME . ") uses version: <a href=\"https://github.com/Benjamin-Loison/YouTube-operational-API/commit/$hash\">$hash</a>";
    }

?>

    </body>
</html>
