<?php

    include_once 'common.php';

    header('Content-Type: application/json; charset=UTF-8');

    if(isset($_GET['requests']))
    {
        $requestsStr = $_GET['requests'];
        $requests = explode(';', $requestsStr);
        $results = [];
        foreach($requests as $request)
        {
            $currentFolder = dirname($_SERVER['PHP_SELF']);
            if($currentFolder !== '/')
            {
                $currentFolder .= '/';
            }
            $url = WEBSITE_URL_BASE . $currentFolder . urldecode($request);
            if(isset($_GET['instanceKey']))
            {
                $url .= '&instanceKey=' . $_GET['instanceKey'];
            }
            $result = json_decode(file_get_contents($url));
            array_push($results, $result);
        }
        echo json_encode($results, JSON_PRETTY_PRINT);
    }
    else
    {
        dieWithJsonMessage("Required parameters not provided");
    }

?>
