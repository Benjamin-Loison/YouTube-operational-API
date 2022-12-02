<title>YouTube operational API</title>

<?php

    include_once 'common.php';

    function url($url, $name = '')
    {
        if ($name === '') {
            $name = $url;
        }
        return '<a href="' . $url . '">' . $name . '</a>';
    }

    function yt()
    {
        echo '<a href="https://developers.google.com/youtube/v3">YouTube Data API v3</a>';
    }

    function feature($feature)
    {
        $suburl = $feature[0];
        $webpage = explode('/', $suburl, 2)[0];
        $url = $feature[1];
        $name = ucfirst(str_replace('/', ': ', $suburl));
        echo 'Based on <a href="https://developers.google.com/youtube/v3/docs/' . $suburl . '">' . $name . '</a>: ' . url(WEBSITE_URL . $webpage . '?part=' . $url) . '<br/>';
    }

    $features = [['channels/list', 'status,premieres,shorts,community,channels,about&forUsername=USERNAME&id=CHANNEL_ID&handle=HANDLE'],
                 ['commentThreads/list', 'snippet,replies&videoId=VIDEO_ID(&pageToken=PAGE_TOKEN)'],
                 ['playlists/list', 'statistics&id=PLAYLIST_ID'],
                 ['playlistItems/list', 'snippet&playlistId=PLAYLIST_ID(&pageToken=PAGE_TOKEN)'],
                 ['search/list', 'id,snippet&q=QUERY&channelId=CHANNEL_ID&eventType=upcoming&hashTag=HASH_TAG&type=video&order=viewCount,relevance(&pageToken=PAGE_TOKEN)'],
                 ['videos/list', 'id,status,contentDetails,music,short,impressions,musics,isPaidPromotion,isPremium,isMemberOnly,mostReplayed,qualities,chapters,isOriginal&id=VIDEO_ID&clipId=CLIP_ID&SAPISIDHASH=YOUR_SAPISIDHASH']];

?>

<h1>YouTube operational API works when <?php yt(); ?> fails.</h1>

<h2>Current implemented features:</h2>
<?php

    foreach ($features as $feature) {
        feature($feature);
    }

    echo '<br/>';
    echo url(WEBSITE_URL . 'lives' . '?part=' . 'donations&id=VIDEO_ID') . '<br/>';
    echo url(WEBSITE_URL . 'liveChats' . '?part=' . 'snippet,participants&id=VIDEO_ID&time=TIME_MS') . '<br/>';

?>

<h2>Make <?php yt(); ?> request WITHOUT ANY KEY:</h2>

To make <strong>ANY <?php yt(); ?> request WITHOUT ANY KEY/USING YOUR QUOTA</strong>, you can use: <?php $noKey = 'noKey'/*used to be yt*/; echo url(WEBSITE_URL . $noKey . '/YOUR_REQUEST'); ?><br/>
For instance you can use: <?php $example = 'videos?part=snippet&id=VIDEO_ID'; echo url(WEBSITE_URL . $noKey . '/' . $example); ?> instead of <?php echo url('https://www.googleapis.com/youtube/v3/' . $example); ?><br/>
I may add in the future limitation per IP etc if the quota need to be better shared among the persons using this API.<br/>
<?php

    $keysFile = '/var/www/ytPrivate/keys.txt';
    $keysCount = substr_count(file_get_contents($keysFile), "\n") + 1;

?>
Currently this service is <a href="keys.php">powered by <?php echo $keysCount; ?> keys</a>.<br/>
<script>

function share() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            alert(xhttp.responseText);
            youtubeDataAPIV3KeyInput.value = "";
        }
    };
    var youtubeDataAPIV3KeyInput = document.getElementById("youtubeDataAPIV3Key");
    const key = youtubeDataAPIV3KeyInput.value;
    xhttp.open("GET", "addKey.php?key=" + key);
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
        echo '<br/><br/>This instance uses version: <a href="https://github.com/Benjamin-Loison/YouTube-operational-API/commit/' . $hash . '">' . $hash . '</a>';
    }

?>
