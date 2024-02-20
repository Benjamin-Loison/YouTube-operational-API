<?php

/**
 * Get array intersect assoc recursive.
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
        $value = $test[2];
        // Should not use network but call the PHP files thanks to `include` etc and provide arguments correctly instead.
        $content = shell_exec("php-cgi ../$endpoint.php " . escapeshellarg($url));
        $content = str_replace('Content-Type: application/json; charset=UTF-8', '', $content);
        $json = json_decode($content, true);
        $thePathExists = doesPathExist($json, $jsonPath);
        $theValue = $thePathExists ? getValue($json, $jsonPath) : '';

        $valueInclusion = (is_array($value) && array_intersect_assoc_recursive($value, $theValue) == $value) || (!is_array($value) && $theValue === $value);
        $testSuccessful = $thePathExists && $valueInclusion;
        $value = is_array($value) ? 'Array' : $value;
        $theValue = is_array($theValue) ? 'Array' : $theValue;
        echo($testSuccessful ? 'PASS' : 'FAIL') . " $endpoint $url $jsonPath $value" . ($testSuccessful ? '' : " $theValue") . "\n";
    }
