<?php

Class DhonAuth {
    public function unauthorized()
    {
        header('WWW-Authenticate: Basic realm="My Realm"');
        header('HTTP/1.0 401 Unauthorized');
        header('Content-Type: application/json');
		header('Access-Control-Allow-Origin: *');
        $response       = 'unauthorized';
        $json_response 	= ['response' => $response, 'status' => '401'];
        echo json_encode($json_response, JSON_NUMERIC_CHECK);
        exit;
    }
}