<title>YouTube operational API</title>

<!-- could add https://stackoverflow.com/a/70756481/7123660 and https://stackoverflow.com/a/70660765/7123660 -->
<!-- could force one request at a time in order not to get banned by YouTube -->
<!-- could use my YT Data API v3 keys likewise don't have to code that much but I'm currently using my keys ^^' -->
<!-- from google credentials can generate a YT Data API v3 key from a random project just by using curl ? -->
<!-- may think about using compressed parameter to decrease server workload -->
<!-- indeed there is a bug sometimes just to have quota with the official API so maybe recoding some of their features but let's try to give a shot to encapsulated keys -->
<!-- if have to provide an error to the end user try to make like the official API, JSON and try to respect their format for such use -->

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

    // don't know if already written but making a table may be nice
    $features = [['channels/list', 'snippet,premieres,about&forUsername=USERNAME&id=CHANNEL_ID'], // could use ',' instead of '&' to describe that `forUsername` and `id` have the same aim
                 ['commentThreads/list', 'snippet,replies&videoId=VIDEO_ID(&pageToken=PAGE_TOKEN)'],
                 ['playlists/list', 'statistics&id=PLAYLIST_ID'],
                 ['playlistItems/list', 'snippet&playlistId=PLAYLIST_ID(&pageToken=PAGE_TOKEN)'],
                 ['search/list', 'id,snippet&q=QUERY&channelId=CHANNEL_ID&eventType=upcoming&hashTag=HASH_TAG&type=video&order=viewCount,relevance(&pageToken=PAGE_TOKEN)'],
                 ['videos/list', 'id,status,contentDetails,music,short,impressions,containsMusic,isPaidPromotion,isPremium,isMemberOnly,mostReplayed,qualities&id=VIDEO_ID&clipId=CLIP_ID&SAPISIDHASH=YOUR_SAPISIDHASH']];
    // adding some comments may be useful later (not useful if in native documenation I would say) - maybe adding an example could be nice too

?>

<!-- could provide a detailed list of where YouTube fails but this API listed quite well these even if all aren't listed because not implemented -->
<h1>YouTube operational API works when <?php yt(); ?> fails.</h1>

<h2>Current implemented features:</h2>
<?php

    foreach ($features as $feature) {
        feature($feature);
    }

    echo '<br/>';
    echo url(WEBSITE_URL . 'lives' . '?part=' . 'donations&id=VIDEO_ID') . '<br/>';
    echo url(WEBSITE_URL . 'liveChats' . '?part=' . 'snippet&id=VIDEO_ID&time=TIME_MS') . '<br/>';

?>

<!--<br/>We provide also webhooks:<br/><br/>-->

<!---For triggering an event when a live is started:<br/>-->
<!--<?php echo url(WEBSITE_URL . 'webhooks?event=live&channelId=CHANNEL_ID&endpoint=YOUR_ENDPOINT'); ?> don't forget to replace CHANNEL_ID and YOUR_ENDPOINT (for instance <?php echo url('https://yourwebsite.com/listener.php'); ?> for the endpoint), the latter will receive after maximum one minute when a live started, a request from the IP resolved from <?php echo DOMAIN_NAME; ?> with POST values event and channelId as precised in the request webhook URL.<br/>-->
<!--An example of listener.php is available here: <?php echo url(WEBSITE_URL . 'listener.php?code'); ?>-->

<!-- making anchor to this part for instance for https://stackoverflow.com/questions/70739465/youtube-data-api-i-get-a-quota-exceeded-error-with-a-new-project?noredirect=1#comment125389223_70739465 -->

<h2>Make <?php yt(); ?> request WITHOUT ANY KEY:</h2>

To make <strong>ANY <?php yt(); ?> request WITHOUT ANY KEY/USING YOUR QUOTA</strong>, you can use: <?php $noKey = 'noKey'/*used to be yt*/; echo url(WEBSITE_URL . $noKey . '/YOUR_REQUEST'); ?><br/>
For instance you can use: <?php $example = 'videos?part=snippet&id=VIDEO_ID'; echo url(WEBSITE_URL . $noKey . '/' . $example); ?> instead of <?php echo url('https://www.googleapis.com/youtube/v3/' . $example); ?><br/>
I may add in the future limitation per IP etc if the quota need to be better shared among the persons using this API.<br/>
<?php

    // could use supervariable or something like that instead ?
    //$keysCountFile = '/var/www/ytPrivate/keysCount.txt';
    //$keysCount = file_get_contents($keysCountFile);

    $keysFile = '/var/www/ytPrivate/keys.txt';
    $keysCount = substr_count(file_get_contents($keysFile), "\n") + 1;

?>
Currently this service is <a href="keys.php">powered by <?php echo $keysCount; ?> keys</a>.
<!-- given a YT Data API v3 key let's allow any request likewise nothing to code for each feature (likewise people can't steal the API key and there isn't any private data access, let's make a form allowing people to add their keys if they want to share it) -->
<!-- add possibility to share/remove any key -->
<!-- could add an email linked (not Google mean) to the key if need to contact for future modification -->

<h2>Open-source:</h2>
The source code is available on GitHub: <?php echo url('https://github.com/Benjamin-Loison/YouTube-operational-API'); ?>

<h2>Contact:</h2>
If a feature you are looking for which isn't working on <?php yt(); ?>, ask kindly with the below contact:<br/>
- <?php echo url('https://matrix.to/#/#youtube-operational-api:matrix.org', 'Matrix'); ?><br/>
- <?php echo url('https://discord.gg/pDzafhGWzf', 'Discord'); ?>
