<?php

    $endpoint = $argv[1];
    require_once '../yt/' . $endpoint . '.php';
    $tests = $GLOBALS[$endpoint . 'Tests'];
    foreach ($tests as $test) {
        $url = $test[0];
        $jsonPath = $test[1];
        $value = $test[2];
        $content = file_get_contents(WEBSITE_URL . $endpoint . '?part=' . $url);
        $json = json_decode($content, true);
        $thePathExists = doesPathExist($json, $jsonPath);
        $theValue = $thePathExists ? getValue($json, $jsonPath) : '';
        $testSuccessful = $thePathExists && $theValue === $value;
        echo($testSuccessful ? 'X' : '_') . ' ' . $endpoint . ' ' . $url . ' ' . $jsonPath . ' ' . $value . ($testSuccessful ? '' : ' ' . $theValue) . "\n";
    }
