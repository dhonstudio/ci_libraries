<?php

class DhonJson
{
    public $db_name;
    public $table;
    public $command;
    public $id;
    protected $db;
    protected $db_total;
    protected $db_hit;
    protected $fields;
    protected $json_response;
    protected $data;
    public $basic_auth;
    public $api_db;
    public $method;
    public $sort;
    public $filter;
    public $limit;
    protected $user;
    protected $env;
    protected $db_path;

    public function __construct()
    {
        $this->dhonjson = &get_instance();

        $this->uri  = $this->dhonjson->uri;

        $this->load         = $this->dhonjson->load;
        $this->input        = $this->dhonjson->input;

        $this->env          = ENVIRONMENT == 'production' ? '' : '_dev';
        $this->db_path      = ENVIRONMENT == 'production' ? 'production' : 'testing';
    }

    /**
     * Authorize API User
     *
     * @param	string	$api_db_name
     * @return	void
     */
    private function basic_auth(string $api_db_name)
    {
        include APPPATH . "config/{$this->db_path}/database.php";

        if (in_array($api_db_name, array_keys($db))) {
            $this->load->dbutil();
            if (ENVIRONMENT == 'development' && !$this->dhonjson->dbutil->database_exists($db[$api_db_name]['database'])) {
                $status     = 404;
                $message    = "API db not found";
                $this->send(['status' => $status, 'message' => $message]);
            } else {
                $api_db = $this->load->database($api_db_name . $this->env, TRUE);

                if ($api_db->table_exists('api_users')) {
                    if (isset($_SERVER['PHP_AUTH_USER'])) {
                        $user = $api_db->get_where('api_users', ['username' => $_SERVER['PHP_AUTH_USER']])->row_array();
                        if (!$user || !password_verify($_SERVER['PHP_AUTH_PW'], $user['password'])) {
                            $this->_unauthorized();
                        } else {
                            $this->user = $user;
                        }
                    } else {
                        $this->_unauthorized();
                    }
                } else {
                    $status     = 404;
                    $message    = "API table not found";
                    $this->send(['status' => $status, 'message' => $message]);
                }
            }
        } else {
            $status     = 404;
            $message    = "API db name not found";
            $this->send(['status' => $status, 'message' => $message]);
        }
    }

    /**
     * Return Unauthorize
     *
     * @return	void
     */
    private function _unauthorized()
    {
        $this->user['id_user'] = 1;
        $status     = 401;
        $this->send(['status' => $status]);
    }

    /**
     * Return Data/Response of Command
     *
     * @return	void
     */
    public function collect()
    {
        if ($this->basic_auth) $this->basic_auth($this->api_db);
        else $this->user = [
            'level'     => 4,
            'id_user'   => 1,
        ];

        if ($this->db_name) {
            include APPPATH . "config/{$this->db_path}/database.php";

            if (in_array($this->db_name . $this->env, array_keys($db))) {
                $this->db           = $this->load->database($this->db_name . $this->env, TRUE);
                $this->db_total     = $this->load->database($this->db_name . $this->env, TRUE);

                if ($this->table) {
                    if ($this->db->table_exists($this->table)) {
                        $this->fields   = $this->db->list_fields($this->table);

                        $status = 200;
                        $this->json_response = ['status' => $status];

                        if ($this->user['level'] < 1) {
                            $this->json_response['status']  = 405;
                            $this->json_response['message'] = 'Authorization issue';
                        } else {
                            if ($this->method == 'DELETE' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
                                if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->json_response['status'] = 405;
                                else {
                                    if ($this->user['level'] < 4) {
                                        $this->json_response['status'] = 405;
                                        $this->json_response['message'] = 'No authorization to DELETE';
                                    } else $this->delete();
                                }
                            } else if ($this->command == 'password_verify') {
                                if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->json_response['status']  = 405;
                                else $this->password_verify();
                            } else if ($this->command == 'insert') $this->insert();
                            else if ($this->command == '') {
                                if ($this->method == 'GET') {
                                    if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->json_response['status']  = 405;
                                    else $this->get_where();
                                } else if ($this->method == 'POST' || $this->method == 'PUT') {
                                    if ($_SERVER['REQUEST_METHOD'] === 'GET' || $this->user['level'] < 2) {
                                        $this->json_response['status']  = 405;
                                        $this->json_response['message'] = 'No authorization to POST';
                                    } else $this->post();
                                } else {
                                    if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->json_response['status']  = 405;
                                    else $this->get();
                                }
                            } else $this->json_response['status']  = 405;
                        }

                        $message = isset($this->json_response['message']) ? $this->json_response['message'] : '';
                        $total = isset($this->json_response['total']) ? $this->json_response['total'] : -1;
                        $result = isset($this->json_response['result']) ? $this->json_response['result'] : '';
                        $paging = isset($this->json_response['paging']) ? $this->json_response['paging'] : '';
                        $page = isset($this->json_response['page']) ? $this->json_response['page'] : '';
                        $data = isset($this->json_response['data']) ? $this->json_response['data'] : '';

                        $this->send(['status' => $this->json_response['status'], 'message' => $message, 'total' => $total, 'result' => $result, 'paging' => $paging, 'page' => $page, 'data' => $data]);
                    } else {
                        $status     = 404;
                        $message    = 'Table not found';
                    }
                } else {
                    $status     = 500;
                    $message    = '';
                }
            } else {
                $status     = 404;
                $message    = 'Database name not found';
            }
        } else {
            $status     = 500;
            $message    = '';
        }
        $this->send(['status' => $status, 'message' => $message]);
    }

    private function get()
    {
        if (($this->sort && isset($_GET['sort_by'])) || ($this->filter && isset($_GET['keyword'])) || ($this->limit && isset($_GET['limit']))) {
            $this->get_where();
        } else if ($_GET) {
            $this->json_response['status']  = 405;
            $this->json_response['message'] = 'Parameters not acceptable';
        } else {
            $this->db   = $this->db->get($this->table);
            $data       = $this->db->result_array();
            $this->json_response['total']   = $this->db->num_rows();
            $this->json_response['data']    = $data;
        }
    }

    private function get_where()
    {
        foreach ($_GET as $key => $value) {
            if (strpos($key, '__more') !== false) {
                $get_verified = str_replace("__more", "", $key);
                if (in_array($get_verified, $this->fields)) $get_where[$get_verified . ' >'] = $value;
            } else if (strpos($key, '__less') !== false) {
                $get_verified = str_replace("__less", "", $key);
                if (in_array($get_verified, $this->fields)) $get_where[$get_verified . ' <'] = $value;
            } else {
                if (in_array($key, $this->fields)) $get_where[$key] = $value;
            }
        }

        if ($this->sort) {
            if (array_key_exists('sort_by', $_GET)) {
                $sort_by        = $_GET['sort_by'];
                $sort_method    = isset($_GET['sort_method']) ? $_GET['sort_method'] : 'asc';

                $this->db           = $this->db->order_by($sort_by, $sort_method);
                $this->db_total     = $this->db_total->order_by($sort_by, $sort_method);
            }
        }

        if ($this->filter) {
            if (array_key_exists('keyword', $_GET)) {
                $keyword = $_GET['keyword'];

                foreach ($this->fields as $key => $value) {
                    if ($key == 0) {
                        $this->db       = $this->db->like($value, $keyword);
                        $this->db_total = $this->db_total->like($value, $keyword);
                    } else {
                        $this->db       = $this->db->or_like($value, $keyword);
                        $this->db_total = $this->db_total->or_like($value, $keyword);
                    }
                }
            }
        }

        if ($this->limit) {
            if (array_key_exists('limit', $_GET)) {
                $limit      = $_GET['limit'];
                $get_offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
                $offset     = $get_offset * $limit;

                $this->db = $this->db->limit($limit, $offset);
                $this->json_response['paging']  = TRUE;
                $this->json_response['page']    = $get_offset + 1;
            }
        }

        if (isset($get_where)) {
            $this->json_response['total']   = $this->db_total->get_where($this->table, $get_where)->num_rows();
            $this->json_response['data']    = $this->db->get_where($this->table, $get_where)->result_array();
        } else if (
            (array_key_exists('sort_by', $_GET) ||
                array_key_exists('keyword', $_GET))
            && $this->method != 'GET'
        ) {
            $this->json_response['total']   = $this->db_total->get($this->table)->num_rows();
            $this->json_response['data']    = $this->db->get($this->table)->result_array();
        } else if (array_key_exists('limit', $_GET) && $this->method != 'GET') {
            $total = $this->db_total->get($this->table)->num_rows();
            $this->json_response['total']   = $total;
            $this->json_response['result']  = $total < $limit ? $total : $limit;
            $this->json_response['data']    = $this->db->get($this->table)->result_array();
        } else {
            $this->json_response['status']  = 405;
        }
    }

    private function post()
    {
        $input = $_POST ? $_POST : $_GET;
        if (count($_POST) == 0) {
            $this->json_response['status']  = 405;
            $this->json_response['message'] = 'Body can\'t empty';
        } else {
            foreach ($input as $key => $value) {
                $value = strpos($value, 'dansimbol') !== false ?
                    str_replace('dansimbol', '&', $value)
                    : ($key == 'password' || $key == 'password_hash' ? password_hash($value, PASSWORD_DEFAULT) : $value);

                if (in_array($key, $this->fields)) $posts[$key] = $value;
            }
            $fields = $this->db->list_fields($this->table);
            // !isset($input[$this->fields[0]]) && in_array('stamp', $this->fields) ?
            $this->method != 'PUT' && in_array('stamp', $this->fields) ?
                $posts['stamp'] = time() : false;
            // !isset($input[$this->fields[0]]) && in_array('created_at', $this->fields) && !isset($input['created_at'])
            $this->method != 'PUT' && in_array('created_at', $this->fields) && !isset($input['created_at'])
                ? ($this->db->field_data($this->table)[array_search('created_at', $fields)]->type == 'INT'
                    ? $posts['created_at'] = time()
                    : $posts['created_at'] = date('Y-m-d H:i:s', time()))
                : (in_array('modified_at', $this->fields)
                    ? ($this->db->field_data($this->table)[array_search('modified_at', $fields)]->type == 'INT'
                        ? $posts['modified_at'] = time()
                        : $posts['modified_at'] = date('Y-m-d H:i:s', time()))
                    : (in_array('updated_at', $this->fields)
                        ? ($this->db->field_data($this->table)[array_search('updated_at', $fields)]->type == 'INT'
                            ? $posts['updated_at'] = time()
                            : $posts['updated_at'] = date('Y-m-d H:i:s', time()))
                        : false
                    )
                );

            // if (isset($input[$this->fields[0]])) {
            if ($this->method == 'PUT') {
                // $id = $posts[$this->fields[0]];
                $id = $this->id;
                if ($this->db->get_where($this->table, [$this->fields[0] => $id])->row_array()) {
                    if ($this->user['level'] < 3) {
                        $this->json_response['status']  = 405;
                        $this->json_response['message'] = 'No authorization to PUT';
                    } else $this->db->update($this->table, $posts, [$this->fields[0] => $id]);
                } else {
                    $this->json_response['status']  = 404;
                }
            } else {
                $id = $this->db->insert($this->table, $posts) ? $this->db->insert_id() : 0;
            }

            if ($id != 0) {
                if ($this->json_response['status'] == 200)
                    $this->json_response['data'] = $this->db->get_where($this->table, [$this->fields[0] => $id])->row_array();
            } else {
                $this->json_response['status']  = 406;
                $this->json_response['data']    = [false];
                $this->json_response['message'] = $this->error_duplicate;
            }
        }
    }

    private function delete()
    {
        if ($this->id) {
            if ($this->db->get_where($this->table, [$this->fields[0] => $this->id])->row_array()) {
                $this->db->delete($this->table, [$this->fields[0] => $this->id]);

                $query = "ALTER TABLE $this->table AUTO_INCREMENT = 1";
                $this->db->query($query);

                $this->json_response['data'] = ['id' => $this->id];
            } else {
                $this->json_response['status']  = 404;
                $this->json_response['message'] = 'ID not found';
            }
        } else {
            $this->json_response['status']  = 500;
        }
    }

    private function password_verify()
    {
        if ($_GET) {
            foreach ($_GET as $key => $value) {
                if (in_array($key, $this->fields)) {
                    if ($key == 'password' || $key == 'password_hash') {
                        $password_field_name = $key;
                    } else {
                        $get_where[$key] = $value;
                    }
                }
            }
            if (isset($password_field_name) && isset($get_where)) {
                $this->data = $this->db->get_where($this->table, $get_where)->row_array();
                $result = $this->data ? (password_verify($_GET[$password_field_name], $this->data[$password_field_name]) ? true : [false]) : [false];
                $this->json_response['data'] = $result;
            } else {
                $this->json_response['status']  = 405;
            }
        } else {
            $this->json_response['status']  = 500;
        }
    }

    private function insert()
    {
        if ($_GET) {
            $this->post();
        } else {
            $this->json_response['status']  = 500;
        }
    }

    /**
     * Send return as JSON
     *
     * @param	array   $params optional ['response' => 'string', 'status' => int, 'message' => 'string', 'total' => int, 'result' => int, 'paging' => boolean, 'page' => int, 'data' => array()]
     * @return	echo    json_encode
     */
    public function send(array $params = [])
    {
        $status     = isset($params['status']) ? $params['status'] : 500;
        $message    = isset($params['message']) ? $params['message'] : '';
        $total      = isset($params['total']) ? $params['total'] : -1;
        $result     = isset($params['result']) ? $params['result'] : '';
        $paging     = isset($params['paging']) ? $params['paging'] : '';
        $page       = isset($params['page']) ? $params['page'] : '';
        $data       = isset($params['data']) ? $params['data'] : '';
        $no_hit     = isset($params['no_hit']) ? $params['no_hit'] : false;

        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        // header('WWW-Authenticate: Basic realm="My Realm"');

        $response =
            $status == 400 ? 'Bad Request'
            : ($status == 401 ? 'Unauthorized'
                : ($status == 404 ? 'Not Found'
                    : ($status == 405 ? 'Method Not Allowed'
                        : ($status == 406 ? 'Not Acceptable'
                            : ($status == 417 ? 'Expectation Failed'
                                : ($status == 500 ? 'Internal Server Error'
                                    : 'OK'
                                )
                            )
                        )
                    )
                )
            );

        header("HTTP/1.1 {$status} {$response}");

        $this->json_response = ['response' => $response, 'status' => $status];
        if ($message != '') $this->json_response['message'] = $message;
        if ($total != -1) $this->json_response['total'] = $total;
        if ($result != '') $this->json_response['result'] = $result;
        if ($paging != '') $this->json_response['paging'] = $paging;
        if ($page != '') $this->json_response['page'] = $page;
        if ($data === [false]) $this->json_response['data'] = false;
        else if ($data != '') $this->json_response['data'] = $data;

        if (!$no_hit) $this->_hit();
        echo json_encode($this->json_response, JSON_NUMERIC_CHECK);
        exit;
    }

    private function _curl(string $url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        return curl_exec($curl);
        curl_close($curl);
    }

    private function _hit()
    {
        $session_name = 'DShC13v';

        $this->db_hit = $this->load->database($this->api_db . $this->env, TRUE);

        //~ api_address
        $ip_address_pre =
            !empty($_SERVER["HTTP_X_CLUSTER_CLIENT_IP"]) ? $_SERVER["HTTP_X_CLUSTER_CLIENT_IP"] : (!empty($_SERVER["HTTP_X_CLIENT_IP"]) ? $_SERVER["HTTP_X_CLIENT_IP"] : (!empty($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] : (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : (!empty($_SERVER["HTTP_X_FORWARDED"]) ? $_SERVER["HTTP_X_FORWARDED"] : (!empty($_SERVER["HTTP_FORWARDED_FOR"]) ? $_SERVER["HTTP_FORWARDED_FOR"] : (!empty($_SERVER["HTTP_FORWARDED"]) ? $_SERVER["HTTP_FORWARDED"] :
                $_SERVER["REMOTE_ADDR"]
            ))))));
        foreach (explode(',', $ip_address_pre) as $ip) {
            $ip = trim($ip); // just to be safe

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                $ip_address = $ip;
            } else {
                $ip_address = $ip;
            }
        }

        $address_av = $this->db_hit->get_where('api_address', ['ip_address' => $ip_address])->result_array();
        if (empty($address_av)) {
            $this->db_hit->insert('api_address', [
                'ip_address'    => $ip_address,
                'ip_info'       => $this->_curl("http://ip-api.com/json/{$ip_address}")
            ]);
            $id_address = $this->db_hit->insert_id();
        } else {
            $id_address = $address_av[0]['id_address'];
        }

        //~ api_entity
        $entity = isset($_SERVER['HTTP_USER_AGENT']) ? htmlentities($_SERVER['HTTP_USER_AGENT']) : 'REQUEST';

        $entities       = $this->db_hit->get('api_entity')->result_array();
        $entity_key     = array_search($entity, array_column($entities, 'entity'));
        $entity_av      = !empty($entities) ? ($entity_key > -1 ? $entities[$entity_key] : 0) : 0;
        if ($entity_av === 0) {
            $this->db_hit->insert('api_entity', [
                'entity' => $entity,
            ]);
            $id_entity = $this->db_hit->insert_id();
        } else {
            $id_entity = $entity_av['id'];
        }

        //~ api_session
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
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
        } else {
            $session_value = "REQUEST";
        }

        $session_av = $this->db_hit->get_where('api_session', ['session' => $session_value])->result_array();
        if (empty($session_av)) {
            $this->db_hit->insert('api_session', [
                'session' => $session_value,
            ]);
            $id_session = $this->db_hit->insert_id();
        } else {
            $id_session = $session_av[0]['id_session'];
        }

        //~ api_endpoint
        if ($_GET) {
            $get_join = [];
            foreach ($_GET as $key => $value) {
                array_push($get_join, $key . '=' . $value);
            }
            $get = '?' . implode('&', $get_join);
        } else {
            $get = '';
        }
        $endpoint = $this->uri->uri_string() . $get;
        $endpoint_av = $this->db_hit->get_where('api_endpoint', ['endpoint' => $endpoint])->result_array();
        if (empty($endpoint_av)) {
            $this->db_hit->insert('api_endpoint', [
                'endpoint' => $endpoint,
            ]);
            $id_endpoint = $this->db_hit->insert_id();
        } else {
            $id_endpoint = $endpoint_av[0]['id_endpoint'];
        }

        $action = $this->method == 'GET' ? 2
            : ($this->method == 'POST' ? 3
                : ($this->method == 'PUT' ? 4
                    : ($this->method == 'DELETE' ? 5
                        : ($this->command == 'password_verify' ? 6 : 1))));

        $success    = $this->json_response['status'] == 200 ? 1 : 0;
        $error      = $this->json_response['status'] == 200 ? 0 : $this->json_response['status'];
        $message    = isset($this->json_response['message']) ? $this->json_response['message'] : '';

        $this->db_hit->insert('api_log', [
            'id_user'       => $this->user['id_user'],
            'address'       => $id_address,
            'entity'        => $id_entity,
            'session'       => $id_session,
            'endpoint'      => $id_endpoint,
            'action'        => $action,
            'success'       => $success,
            'error'         => $error,
            'message'       => $message,
            'created_at'    => date('Y-m-d H:i:s', time())
        ]);
    }
}
