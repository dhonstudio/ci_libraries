<?php

class DhonHit
{
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
        $this->dhonhit = &get_instance();

        require_once 'DhonAPI.php';
        $this->dhonapi = new DhonAPI;

        $this->load     = $this->dhonhit->load;
        $this->input    = $this->dhonhit->input;
        $this->uri      = $this->dhonhit->uri;
    }

    private function get_ip()
    {
        if (ENVIRONMENT == "development") {
            $ip_address = "127.0.0.1";
        } else {
            $ip_address_pre =
                !empty($_SERVER["HTTP_X_CLUSTER_CLIENT_IP"]) ? $_SERVER["HTTP_X_CLUSTER_CLIENT_IP"] : (!empty($_SERVER["HTTP_X_CLIENT_IP"]) ? $_SERVER["HTTP_X_CLIENT_IP"] : (!empty($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] : (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : (!empty($_SERVER["HTTP_X_FORWARDED"]) ? $_SERVER["HTTP_X_FORWARDED"] : (!empty($_SERVER["HTTP_FORWARDED_FOR"]) ? $_SERVER["HTTP_FORWARDED_FOR"] : (!empty($_SERVER["HTTP_FORWARDED"]) ? $_SERVER["HTTP_FORWARDED"] :
                    $_SERVER["REMOTE_ADDR"]
                ))))));
            foreach (explode(',', $ip_address_pre) as $ip) {
                $ip = trim($ip); // just to be safe

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    $ip_address = $ip;
                }
            }
        }

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
        $session_name = 'DShC13v';

        $this->load->helper('cookie');
        $this->load->helper('string');

        $session_value  = random_string('alnum', 32);
        $session_cookie = array(
            'name'   => $session_name,
            'value'  => $session_value,
            'expire' => 2 * 60 * 60,
        );
        if (!$this->input->cookie($session_name) || ($this->input->cookie($session_name) === '' || $this->input->cookie($session_name) === null)) {
            set_cookie($session_cookie);
        } else {
            $session_value = $this->input->cookie($session_name);
        }

        $session_av         = $this->dhonapi->get($this->db, $this->table['session'], ['session' => $session_value]);
        $this->id_session   = empty($session_av) ? $this->dhonapi->post($this->db, $this->table['session'], [
            'session' => $session_value,
        ])['id_session'] : $session_av[0]['id_session'];
    }

    private function get_source()
    {
        $source = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : base_url();

        $source_av          = $this->dhonapi->get($this->db, $this->table['source'], ['source' => $source]);
        $this->id_source    = empty($source_av) ? $this->dhonapi->post($this->db, $this->table['source'], [
            'source' => $source,
        ])['id_source'] : $source_av[0]['id_source'];
    }

    private function get_page()
    {
        if (
            $this->uri->segment(1) != 'assets'
        ) {
            for ($i = 1; $i < 100; $i++) {
                if ($this->uri->segment($i)) {
                    $page_result[] = '/' . $this->uri->segment($i);
                }
            }
            $page = $this->uri->segment(1) ? implode('', $page_result) : '/home';

            $page_av        = $this->dhonapi->get($this->db, $this->table['page'], ['page' => $page]);
            $this->id_page  = empty($page_av) ? $this->dhonapi->post($this->db, $this->table['page'], [
                'page' => $page,
            ])['id_page'] : $page_av[0]['id_page'];
        }
    }

    public function create_hit()
    {
        $this->dhonapi->api_url['development'] = $this->api_url['development'];
        $this->dhonapi->api_url['testing'] = $this->api_url['testing'];
        $this->dhonapi->api_url['production'] = $this->api_url['production'];
        $this->dhonapi->username = $this->username;
        $this->dhonapi->password = $this->password;

        $this->get_ip();
        $this->get_entity();
        $this->get_session();
        $this->get_source();
        $this->get_page();

        if (
            $this->uri->segment(1) != 'assets'
        ) {
            $this->dhonapi->post($this->db, $this->table['hit'], [
                'address'   => $this->id_address,
                'entity'    => $this->id_entity,
                'session'   => $this->id_session,
                'source'    => $this->id_source,
                'page'      => $this->id_page,
                'created_at' => date('Y-m-d H:i:s', time())
            ]);
        }
    }
}
