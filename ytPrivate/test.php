<?php

/**
 * Get array intersect assoc recursive.
 * Note that this intersection is not commutative due to `$intersectValues[$key] = $value1[$key]`.
 *
 * @param mixed $value1
 * @param mixed $value2
 *
 * @return array|bool
 */
function array_intersect_assoc_recursive(&$value1, &$value2)
{
    if ((!is_array($value1) || !is_array($value2)) || ($value1 == [] && $value2 == [])) {
        return $value1 === $value2;
    }

    $intersectKeys = array_intersect(array_keys($value1), array_keys($value2));

    $intersectValues = [];
    foreach ($intersectKeys as $key) {
        if (array_intersect_assoc_recursive($value1[$key], $value2[$key])) {
            $intersectValues[$key] = $value1[$key];
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

        $isGroundTruthValueAnArrayAndEqualToRetrievedContentValue = is_array($groundTruthValue) && array_intersect_assoc_recursive($retrievedContentValue, $groundTruthValue) == $groundTruthValue;
        $isGroundTruthValueNotAnArrayAndEqualToRetrievedContentValue = !is_array($groundTruthValue) && $retrievedContentValue === $groundTruthValue;
        $valueInclusion = $isGroundTruthValueAnArrayAndEqualToRetrievedContentValue || $isGroundTruthValueNotAnArrayAndEqualToRetrievedContentValue;
        $testSuccessful = $jsonPathExistsInRetrievedContentJson && $valueInclusion;
        $groundTruthValue = is_array($groundTruthValue) ? 'Array' : $groundTruthValue;
        $retrievedContentValue = is_array($retrievedContentValue) ? 'Array' : $retrievedContentValue;
        echo($testSuccessful ? 'PASS' : 'FAIL') . " $endpoint $url $jsonPath $groundTruthValue" . ($testSuccessful ? '' : " $retrievedContentValue") . "\n";
    }
