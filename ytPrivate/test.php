<?php

function array_intersect_recursive(&$value1, &$value2)
{
    $intersectKeys = array_intersect(array_keys($value1), array_keys($value2));

    $intersectValues = [];
    foreach ($intersectKeys as $key) {
        $element1 = $value1[$key];
        $element2 = $value2[$key];
        if(is_array($element1) && is_array($element2))
        {
            $intersectValues[$key] = array_intersect_recursive($element1, $element2);
        }
        else if(!is_array($element1) && !is_array($element2) && $element1 === $element2)
        {
            $intersectValues[$key] = $element1;
        }
    }

    return $intersectValues;
}

    $endpoint = $argv[1];
    // Used in endpoint.
    $test = true;
    require_once "../$endpoint.php";
    $tests = $GLOBALS["{$endpoint}Tests"];
    foreach ($tests as $test) {
        $url = $test[0];
        $jsonPath = $test[1];
        $groundTruthValue = $test[2];
        // Should not use network but call the PHP files thanks to `include` etc and provide arguments correctly instead.
        $retrievedContent = shell_exec("php-cgi ../$endpoint.php " . escapeshellarg($url));
        $retrievedContent = str_replace('Content-Type: application/json; charset=UTF-8', '', $retrievedContent);
        $retrievedContentJson = json_decode($retrievedContent, true);
        $jsonPathExistsInRetrievedContentJson = doesPathExist($retrievedContentJson, $jsonPath);
        $retrievedContentValue = $jsonPathExistsInRetrievedContentJson ? getValue($retrievedContentJson, $jsonPath) : '';

        $isGroundTruthValueAnArrayAndEqualToRetrievedContentValue = is_array($groundTruthValue) && array_intersect_recursive($retrievedContentValue, $groundTruthValue) == $groundTruthValue;
        $isGroundTruthValueNotAnArrayAndEqualToRetrievedContentValue = !is_array($groundTruthValue) && $retrievedContentValue === $groundTruthValue;
        $valueInclusion = $isGroundTruthValueAnArrayAndEqualToRetrievedContentValue || $isGroundTruthValueNotAnArrayAndEqualToRetrievedContentValue;
        $testSuccessful = $jsonPathExistsInRetrievedContentJson && $valueInclusion;
        $groundTruthValue = is_array($groundTruthValue) ? 'Array' : $groundTruthValue;
        $retrievedContentValue = is_array($retrievedContentValue) ? 'Array' : $retrievedContentValue;
        echo($testSuccessful ? 'PASS' : 'FAIL') . " $endpoint $url $jsonPath $groundTruthValue" . ($testSuccessful ? '' : " $retrievedContentValue") . "\n";
    }
