<?php

Class DhonHit {
    public $api_url;
    public $username;
    public $password;

    /*
    | -------------------------------------------------------------------
    | $db is $active_group database name in API which you want to post hit
    */
    public $db;
    /*
    | -------------------------------------------------------------------
    | $table is table name on hit database which you want to post every hit component (address, entity, session, source, page, and hit)
    */
    public $table;
    
    public $id_address;
    public $id_entity;
    public $id_session;
    public $id_source;

    public function __construct()
	{
        $this->dhonhit =& get_instance();

        require_once __DIR__ . '/../../assets/ci_libraries/DhonAPI.php';
        $this->dhonapi = new DhonAPI;

        $this->dhonapi->api_url['development'] = $this->api_url['development'];
        $this->dhonapi->api_url['production'] = $this->api_url['production'];
        $this->dhonapi->username = $this->username;
        $this->dhonapi->password = $this->password;

        $this->load     = $this->dhonhit->load;
        $this->input    = $this->dhonhit->input;
        $this->uri      = $this->dhonhit->uri;

        $this->get_ip();
        $this->get_entity();
        $this->get_session();
        $this->get_source();
        $this->get_page();
        $this->create_hit();
    }

    private function get_ip()
    {
        $ip_address = 
            !empty($_SERVER["HTTP_X_CLUSTER_CLIENT_IP"]) ? $_SERVER["HTTP_X_CLUSTER_CLIENT_IP"] : 
            (!empty($_SERVER["HTTP_X_CLIENT_IP"]) ? $_SERVER["HTTP_X_CLIENT_IP"] :
            (!empty($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] :
            (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] :
            (!empty($_SERVER["HTTP_X_FORWARDED"]) ? $_SERVER["HTTP_X_FORWARDED"] :
            (!empty($_SERVER["HTTP_FORWARDED_FOR"]) ? $_SERVER["HTTP_FORWARDED_FOR"] :
            (!empty($_SERVER["HTTP_FORWARDED"]) ? $_SERVER["HTTP_FORWARDED"] :
            $_SERVER["REMOTE_ADDR"]
        ))))));

        $address_av     = $this->dhonapi->get($this->db, $this->table['address'], ['ip_address' => $ip_address]);
        $this->id_address = empty($address_av) ? $this->dhonapi->post($this->db, $this->table['address'], [
            'ip_address'    => $ip_address,
            'ip_info'       => $this->dhonapi->curl("http://ip-api.com/json/{$ip_address}")
        ])['id_address'] : $address_av[0]['id_address'];
    }

    private function get_entity()
    {
        $entity = htmlentities($_SERVER['HTTP_USER_AGENT']);

        $entities       = $this->dhonapi->get($this->db, $this->table['entity']);
        $entity_key     = array_search($entity, array_column($entities, 'entity'));
        $entity_av      = !empty($entities) ? ($entity_key > -1 ? $entities[$entity_key] : 0) : 0;
        $this->id_entity = $entity_av === 0 ? $this->dhonapi->post($this->db, $this->table['entity'], [
            'entity' => $entity,
        ])['id'] : $entity_av['id'];
    }

    private function get_session()
    {
        $this->load->helper('cookie');
        $this->load->helper('string');

        $session_cookie = array(
            'name'   => 'DShC13v',
            'value'  => random_string('alnum', 32),
            'expire' => 2 * 60 * 60,
        );
        $session = $this->input->cookie('DShC13v') ? $this->input->cookie('DShC13v') : set_cookie($session_cookie);

        $session_av         = $this->dhonapi->get($this->db, $this->table['session'], ['session' => $session]);
        $this->id_session   = empty($session_av) ? $this->dhonapi->post($this->db, $this->table['session'], [
            'session' => $session,
        ])['id_session'] : $session_av[0]['id_session'];
    }

    private function get_source()
    {
        $source = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : base_url();
        
        $source_av          = $this->dhonapi->get($this->db, $this->table['source'], ['source' => $source]);
        $this->id_source    = empty($source_av) ? (substr($source, 0, strlen(base_url())) === base_url() ? 0 : $this->dhonapi->post($this->db, $this->table['source'], [
            'source' => $source,
        ])['id_source']) : $source_av[0]['id_source'];
    }

    private function get_page()
    {
        if (
            $this->uri->segment(1) != 'assets'
        ) {
            for ($i=1; $i < 100; $i++) {
                if ($this->uri->segment($i)) {
                    $page_result[] = '/'.$this->uri->segment($i);
                }
            }
            $page = $this->uri->segment(1) ? implode('', $page_result) : '/home';

            $page_av        = $this->dhonapi->get($this->db, $this->table['page'], ['page' => $page]);
            $this->id_page  = empty($page_av) ? $this->dhonapi->post($this->db, $this->table['page'], [
                'page' => $page,
            ])['id_page'] : $page_av[0]['id_page'];
        }
    }

    private function create_hit()
    {
        if (
            $this->uri->segment(1) != 'assets'
        ) {
            $this->dhonapi->post($this->db, $this->table['hit'], [
                'address'   => $this->id_address,
                'entity'    => $this->id_entity,
                'session'   => $this->id_session,
                'source'    => $this->id_source,
                'page'      => $this->id_page
            ]);
        }
    }
}