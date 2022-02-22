<?php

Class DhonJSON {
    public $response;
    public $json_response;
    public $db;
    public $db_total;
    public $db_default;
    public $table;
    public $fields;
    public $data;
    public $id;

    public function __construct()
	{
        $this->dhonjson =& get_instance();

        $this->load = $this->dhonjson->load;
        $this->uri  = $this->dhonjson->uri;
    }

    public function collect()
    {
        $this->db           = $this->load->database($this->uri->segment(1), TRUE);
        $this->db_total     = $this->load->database($this->uri->segment(1), TRUE);
        $this->db_default   = $this->load->database($this->uri->segment(1), TRUE);
        $this->table        = $this->uri->segment(2);
        $this->id           = $this->uri->segment(4);
        $this->fields       = $this->db->list_fields($this->table);

        $response   = 'success';
        $status     = '200';
        $this->json_response = ['response' => $response, 'status' => $status];
        
        if ($this->uri->segment(3) == 'password_verify') $this->password_verify();
        else if ($_GET) $this->get_where();
        else if ($_POST) $this->post();
        else if ($this->uri->segment(3) == 'delete') $this->delete();
        else $this->get();

        $this->send();
    }

    private function get()
    {
        $this->db = $this->db->get($this->table);
        $this->json_response['total'] = $this->db->num_rows();
        $this->json_response['data'] = $this->db->result_array();
    }

    private function get_where()
    {
        foreach ($_GET as $key => $value) {
            if (strpos($key, '__more') !== false) {
				$get_verified = str_replace("__more","",$key);
				if (in_array($get_verified, $this->fields)) $get_where[$get_verified.' >'] = $value;
			} else if (strpos($key, '__less') !== false) {
				$get_verified = str_replace("__less","",$key);
				if (in_array($get_verified, $this->fields)) $get_where[$get_verified.' <'] = $value;
			} else {
                if (in_array($key, $this->fields)) $get_where[$key] = $value;
            }
		}

        if (array_key_exists('sort_by', $_GET)) {
			$sort_by 		= $_GET['sort_by'];
			$sort_method	= $_GET['sort_method'];

			$this->db 		= $this->db->order_by($sort_by, $sort_method);
			$this->db_total	= $this->db_total->order_by($sort_by, $sort_method);
		}

        if (array_key_exists('keyword', $_GET)) {
			$keyword = $_GET['keyword'];
			
			foreach ($this->fields as $key => $value) {
				if ($key == 0)  {
                    $this->db       = $this->db->like($value, $keyword);
                    $this->db_total = $this->db_total->like($value, $keyword);
                } else {
                    $this->db       = $this->db->or_like($value, $keyword);
                    $this->db_total = $this->db_total->or_like($value, $keyword);
                }
			}
		}

        if (array_key_exists('limit', $_GET)) {
            $limit 	  = $_GET['limit'];
			$offset	  = $_GET['offset']*$limit;

            $this->db = $this->db->limit($limit, $offset);
            $this->json_response['paging'] = TRUE;
            $this->json_response['page'] = $_GET['offset']+1;
        }
        
        $this->json_response['total']   = $this->db_total->get_where($this->table, $get_where)->num_rows();
        $this->data                     = $this->db->get_where($this->table, $get_where)->result_array();

        // $this->custom_data();

		$this->json_response['data'] = $this->data;
    }

    private function post()
    {
        foreach ($_POST as $key => $value) {
            if (in_array($key, $this->fields)) $posts[$key] = $value;
		}
        !isset($_POST[$this->fields[0]]) && in_array('stamp', $this->fields) ? $posts['stamp'] = time() : false;
        !isset($_POST[$this->fields[0]]) && in_array('created_at', $this->fields) ? $posts['created_at'] = time() : 
        (in_array('modified_at', $this->fields) ? $posts['modified_at'] = time() : false);
        if (isset($_POST[$this->fields[0]])) {
            $id = $posts[$this->fields[0]];
            $this->db->update($this->table, $posts, [$this->fields[0] => $id]);
        } else {
            $this->db->insert($this->table, $posts);
            $id = $this->db->insert_id();
        }
        $this->json_response['data'] = $this->db->get_where($this->table, [$this->fields[0] => $id])->row_array();
    }

    private function delete()
    {
        $this->db->delete($this->table, [$this->fields[0] => $this->id]);
    }

    public function password_verify()
    {
        foreach ($_GET as $key => $value) {
            if (in_array($key, $this->fields)) {
                if ($key == 'password' || $key == 'password_hash') {
                    $password_field_name = $key;
                } else {
                    $get_where[$key] = $value;
                }
            }
		}
        $this->data = $this->db->get_where($this->table, $get_where)->row_array();
        $result = $this->data ? (password_verify($_GET[$password_field_name], $this->data[$password_field_name]) ? true : false) : false;
        $this->json_response['data'] = $result;
    }

    public function send(string $response = '', string $status = '', $data = [])
    {
        header('Content-Type: application/json');
		header('Access-Control-Allow-Origin: *');
        
        $json_response = $response ? ['response' => $response, 'status' => $status] : $this->json_response;
        if (count($data) > 0) $json_response['data'] = $data;
        echo json_encode($json_response, JSON_NUMERIC_CHECK);
    }
}