<?php

    $endpoints = ['channels', 'playlistItems', 'search', 'videos'];
    foreach ($endpoints as $endpoint) {
        system("php test.php $endpoint");
    } // that way don't have to reset context from a test to the other
    // deepen some tests would be nice
