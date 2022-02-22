<?php

Class DhonAuth {
    public function __construct()
	{
        require_once 'DhonJSON.php';
        $this->dhonjson = new DhonJSON;
    }

    public function unauthorized()
    {
        header('WWW-Authenticate: Basic realm="My Realm"');
        header('HTTP/1.0 401 Unauthorized');

        $response   = 'unauthorized';
        $status     = '401';
        $this->dhonjson->send($response, $status);
        exit;
    }
}