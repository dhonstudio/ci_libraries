<?php

Class DhonAPI {
    public $api_url;
    public $username;
    public $password;

    public function __construct()
	{
        $this->dhonapi =& get_instance();
    }

    public function get(string $db, string $table, array $get_where = [])
    {
        if (!empty($get_where)) {
            foreach ($get_where as $key => $value) {
                $wheres[] = $key.'='.$value;
            }
        }

        $wheres_final = !empty($get_where) ? '?'.implode('&', $wheres) : '';
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($curl, CURLOPT_URL, "{$this->api_url[ENVIRONMENT]}{$db}/{$table}{$wheres_final}");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        return json_decode(curl_exec($curl), true)['data'];
        curl_close($curl);
    }

    public function post(string $db, string $table, array $post)
    {
        foreach ($post as $key => $value) {
            $value = strpos($value, '&') !== false ? str_replace('&', 'dansimbol', $value) : $value;
            $posts[] = $key.'='.$value;
        }
        
        $posts_final = implode('&', $posts);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $posts_final);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($curl, CURLOPT_URL, "{$this->api_url[ENVIRONMENT]}{$db}/{$table}");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        return json_decode(curl_exec($curl), true)['data'];
        curl_close($curl);
    }

    public function delete(string $db, string $table, int $id)
    {        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($curl, CURLOPT_URL, "{$this->api_url[ENVIRONMENT]}{$db}/{$table}/delete/{$id}");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        return json_decode(curl_exec($curl), true)['data'];
        curl_close($curl);
    }
    
    public function curl(string $url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        return curl_exec($curl);
        curl_close($curl);
    }
}