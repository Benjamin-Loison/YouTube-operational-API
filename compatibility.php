<?php

	if (!function_exists('str_contains')) {
        function str_contains($haystack, $needle)
        {
            return strpos($haystack, $needle) !== false;
        }
    }

    if (!function_exists('str_starts_with')) {
        function str_starts_with($haystack, $needle)
        {
            return strpos($haystack, $needle) === 0;
        }
    }

    if (!function_exists('str_ends_with')) {
        function str_ends_with($haystack, $needle)
        {
            $length = strlen($needle);
            return $length > 0 ? substr($haystack, -$length) === $needle : true;
        }
    }

?>