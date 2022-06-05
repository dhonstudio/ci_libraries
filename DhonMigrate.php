<?php

class DhonMigrate
{
    protected $database;
    public $version;
    protected $db;
    protected $dbforge;
    public $table;
    protected $constraint;
    protected $ai;
    protected $unique;
    protected $default;
    protected $fields = [];
    protected $db_path;

    public function __construct(array $params)
    {
        $this->dhonmigrate = &get_instance();

        require_once APPPATH . 'libraries/DhonJson.php';
        $this->dhonjson = new DhonJson;

        $this->database = $params['database'];
        $this->db_path  = ENVIRONMENT == 'production' ? 'production' : 'testing';
        include APPPATH . "config/{$this->db_path}/database.php";

        $env            = ENVIRONMENT == 'production' ? '' : '_dev';
        if (!in_array($this->database . $env, array_keys($db))) {
            $status     = 404;
            $message    = 'Database name not found';
            $this->dhonjson->send(['no_hit' => true, 'status' => $status, 'message' => $message]);
        }

        $this->load = $this->dhonmigrate->load;

        $this->db       = $this->load->database($this->database . $env, TRUE);
        $this->dbforge  = $this->load->dbforge($this->db, TRUE);
    }

    /**
     * Initialize maxlenght of column
     *
     * @param	string|int	$value
     * @return	$this
     */
    public function constraint($value)
    {
        $this->constraint = $value;
        return $this;
    }

    /**
     * Initialize auto-increment column
     *
     * @return	$this
     */
    public function ai()
    {
        $this->ai = TRUE;
        return $this;
    }

    /**
     * Initialize unique column
     *
     * @return	$this
     */
    public function unique()
    {
        $this->unique = TRUE;
        return $this;
    }

    /**
     * Initialize default value of column
     *
     * @param	string|int	$value
     * @return	$this
     */
    public function default($value)
    {
        $this->default = $value;
        return $this;
    }

    /**
     * Initialize one field
     *
     * @param	string|array	$field_name send array if want to change field [0 => 'old_fieldname', 1 => 'new_fieldname']
     * @param	string	        $type
     * @param	string	        $nullable optional ('nullable')
     * @return	void
     */
    public function field($field_name, string $type, string $nullable = '')
    {
        $field_data['type'] = $type;

        if ($this->constraint !== '')   $field_data['constraint']       = $this->constraint;
        if ($this->ai === TRUE)         $field_data['auto_increment']   = $this->ai;
        if ($this->unique === TRUE)     $field_data['unique']           = $this->unique;
        if ($this->default !== '')      $field_data['default']          = $this->default;
        if ($nullable === 'nullable')   $field_data['null']             = TRUE;

        if (is_array($field_name)) {
            $field_data['name'] = $field_name[1];

            $field_element = [
                $field_name[0] => $field_data
            ];
        } else {
            $field_element = [
                $field_name => $field_data
            ];
        }

        $this->fields = array_merge($this->fields, $field_element);
        $this->constraint = '';
        $this->ai = FALSE;
        $this->unique = FALSE;
        $this->default = '';
    }

    /**
     * Initialize primary key
     *
     * @param	string  $field_name
     * @return	void
     */
    public function add_key(string $field_name)
    {
        $this->dbforge->add_key($field_name, TRUE);
    }

    /**
     * Initialize key
     *
     * @param	string  $field_name
     * @return	void
     */
    public function add_index(string $field_name)
    {
        $this->dbforge->add_key($field_name);
    }

    /**
     * To add new field on existing table
     *
     * @return	void
     */
    public function add_field()
    {
        $this->dbforge->add_column($this->table, $this->fields);

        $this->fields = [];
    }

    /**
     * To change field on existing table
     *
     * @return	void
     */
    public function change_field()
    {
        $this->dbforge->modify_column($this->table, $this->fields);

        $this->fields = [];
    }

    /**
     * To delete field on existing table
     *
     * @return	void
     */
    public function drop_field(string $field)
    {
        $this->dbforge->drop_column($this->table, $field);
    }

    /**
     * Create a table
     *
     * @param	string  $force optional ('force')
     * @return	void
     */
    public function create_table(string $force = '')
    {
        if ($this->db->table_exists($this->table)) {
            if ($force == 'force') {
                $this->dbforge->drop_table($this->table);
            } else {
                $status     = 406;
                $message    = "Table `{$this->table}` exist";

                $this->dhonjson->send(['no_hit' => true, 'status' => $status, 'message' => $message]);
            }
        }
        $this->dbforge->add_field($this->fields);
        $this->dbforge->create_table($this->table);

        $this->fields = [];
    }

    /**
     * Relation between table
     *
     * @param	int     $number
     * @param	string  $foreign_key
     * @param	string  $relation_table
     * @param	string  $primary_key
     * @return	void
     */
    public function relate(int $number, string $foreign_key, string $relation_table, string $primary_key)
    {
        $this->db->query("ALTER TABLE `{$this->table}` ADD CONSTRAINT `{$this->table}_ibfk_{$number}` FOREIGN KEY (`{$foreign_key}`) REFERENCES `{$relation_table}`(`{$primary_key}`) ON DELETE CASCADE ON UPDATE CASCADE");
    }

    /**
     * Insert data to table
     *
     * @param	array  $value multidimentional array
     * @return	void
     */
    public function insert(array $value)
    {
        $fields = $this->db->list_fields($this->table);
        if (in_array('created_at', $fields)) {
            if ($this->db->field_data($this->table)[array_search('created_at', $fields)]->type == 'INT') {
                $values = array_merge($value, ['created_at' => time()]);
            } else {
                $values = array_merge($value, ['created_at' => date('Y-m-d H:i:s', time())]);
            }
        } else {
            $values = $value;
        }

        $this->db->insert($this->table, $values);
    }

    /**
     * Do Migrate
     *
     * @param	string  $classname
     * @param	string  $action optional ('' | 'change' | 'drop')
     * @return	void
     */
    public function migrate(string $classname, string $action = '')
    {
        // $path = ENVIRONMENT == 'testing' || ENVIRONMENT == 'development' ? "\\" : "/";
        $migration_file = APPPATH . "migrations/{$this->version}_{$classname}.php";

        if (!file_exists($migration_file)) {
            $status     = 404;
            $message    = 'Migration file not found';
            $this->dhonjson->send(['no_hit' => true, 'status' => $status, 'message' => $message]);
        }

        require $migration_file;

        $migration_name = "Migration_{$classname}";
        $migration      = new $migration_name(['database' => $this->database]);

        $this->table = 'migrations';
        $this->constraint(20)->field('version', 'BIGINT');
        if (!$this->db->table_exists($this->table)) {
            $this->create_table();
        }
        $this->db->insert($this->table, ['version' => date('YmdHis', time())]);

        $action == 'change' ? $migration->change() : ($action == 'drop' ? $migration->drop() : ($action == 'relate' ? $migration->relate() : $migration->up()));

        rename($migration_file, APPPATH . "migrations/" . date('YmdHis_', time()) . "{$classname}.php");

        $status     = 200;
        $message    = 'Migration success';
        $this->dhonjson->send(['no_hit' => true, 'status' => $status, 'message' => $message]);
    }

    /**
     * Create migration file
     *
     * @param	string  $migration_name
     * @param	string  $dev optional ('dev' | '')
     * @return	void
     */
    public function create(string $migration_name)
    {
        $this->load->helper('file');

        $folder_location = APPPATH . 'migrations/';
        if (!is_dir($folder_location)) {
            mkdir($folder_location, 0777, true);
        }
        $timestamp      = date('YmdHis_', time());
        $file_location  = $folder_location . $timestamp . $migration_name . '.php';
        fopen($file_location, "w");

        $data = "<?php

class Migration_" . ucfirst($migration_name) . "
{
    public function __construct(array \$params)
    {
        \$this->database = \$params['database'];

        require_once APPPATH . 'libraries/DhonMigrate.php';
        \$this->dhonmigrate = new DhonMigrate(['database' => \$this->database]);
    }
    
    public function up()
    {
        \$this->dhonmigrate->table = '$migration_name';
        \$this->dhonmigrate->ai()->field('id_user', 'INT');
        \$this->dhonmigrate->constraint('100')->unique()->field('username', 'VARCHAR');
        \$this->dhonmigrate->constraint('200')->field('password', 'VARCHAR');
        \$this->dhonmigrate->default(0)->field('level', 'INT');
        \$this->dhonmigrate->field('created_at', 'DATETIME');
        \$this->dhonmigrate->field('updated_at', 'DATETIME');
        \$this->dhonmigrate->add_key('id_user');
        \$this->dhonmigrate->create_table();

        \$this->dhonmigrate->insert(['username' => 'admin', 'password' => password_hash('admin', PASSWORD_DEFAULT), 'level' => 4]);
        \$this->dhonmigrate->insert(['username' => 'no_access', 'password' => password_hash('admin', PASSWORD_DEFAULT), 'level' => 0]);
        \$this->dhonmigrate->insert(['username' => 'only_get', 'password' => password_hash('admin', PASSWORD_DEFAULT), 'level' => 1]);
        \$this->dhonmigrate->insert(['username' => 'only_getpost', 'password' => password_hash('admin', PASSWORD_DEFAULT), 'level' => 2]);
        \$this->dhonmigrate->insert(['username' => 'only_getpostput', 'password' => password_hash('admin', PASSWORD_DEFAULT), 'level' => 3]);

        \$this->dhonmigrate->table = 'api_log';
        \$this->dhonmigrate->ai()->field('id_log', 'INT');
        \$this->dhonmigrate->field('id_user', 'INT');
        \$this->dhonmigrate->field('address', 'INT');
        \$this->dhonmigrate->field('entity', 'INT');
        \$this->dhonmigrate->field('session', 'INT');
        \$this->dhonmigrate->field('endpoint', 'INT');
        \$this->dhonmigrate->field('action', 'INT');
        \$this->dhonmigrate->field('success', 'INT');
        \$this->dhonmigrate->field('error', 'INT');
        \$this->dhonmigrate->constraint('200')->field('message', 'VARCHAR');
        \$this->dhonmigrate->field('created_at', 'DATETIME');
        \$this->dhonmigrate->add_key('id_log');
        \$this->dhonmigrate->add_index('id_user');
        \$this->dhonmigrate->add_index('address');
        \$this->dhonmigrate->add_index('entity');
        \$this->dhonmigrate->add_index('session');
        \$this->dhonmigrate->add_index('endpoint');
        \$this->dhonmigrate->create_table('force');

        \$this->dhonmigrate->table = 'api_address';
        \$this->dhonmigrate->ai()->field('id_address', 'INT');
        \$this->dhonmigrate->constraint('50')->unique()->field('ip_address', 'VARCHAR', 'nullable');
        \$this->dhonmigrate->constraint('1500')->field('ip_info', 'VARCHAR', 'nullable');
        \$this->dhonmigrate->add_key('id_address');
        \$this->dhonmigrate->create_table('force');

        \$this->dhonmigrate->table = 'api_entity';
        \$this->dhonmigrate->ai()->field('id', 'INT');
        \$this->dhonmigrate->constraint('1000')->unique()->field('entity', 'VARCHAR', 'nullable');
        \$this->dhonmigrate->add_key('id');
        \$this->dhonmigrate->create_table('force');

        \$this->dhonmigrate->table = 'api_session';
        \$this->dhonmigrate->ai()->field('id_session', 'INT');
        \$this->dhonmigrate->constraint('100')->unique()->field('session', 'VARCHAR', 'nullable');
        \$this->dhonmigrate->add_key('id_session');
        \$this->dhonmigrate->create_table('force');

        \$this->dhonmigrate->table = 'api_endpoint';
        \$this->dhonmigrate->ai()->field('id_endpoint', 'INT');
        \$this->dhonmigrate->constraint('500')->unique()->field('endpoint', 'VARCHAR', 'nullable');
        \$this->dhonmigrate->add_key('id_endpoint');
        \$this->dhonmigrate->create_table('force');
    }

    public function change()
    {
        # code...
    }

    public function drop()
    {
        # code...
    }

    public function relate()
    {
        \$table_indexed  = 'api_log';
        \$relations      = [
            [
                'foreign_key' => 'id_user',
                'relation_table' => 'api_users',
                'primary_key' => 'id_user'
            ],
            [
                'foreign_key' => 'address',
                'relation_table' => 'api_address',
                'primary_key' => 'id_address'
            ],
            [
                'foreign_key' => 'entity',
                'relation_table' => 'api_entity',
                'primary_key' => 'id'
            ],
            [
                'foreign_key' => 'session',
                'relation_table' => 'api_session',
                'primary_key' => 'id_session'
            ],
            [
                'foreign_key' => 'endpoint',
                'relation_table' => 'api_endpoint',
                'primary_key' => 'id_endpoint'
            ],
        ];

        foreach (\$relations as \$key => \$value) {
            \$this->dhonmigrate->table = \$table_indexed;
            \$this->dhonmigrate->relate(\$key + 1, \$value['foreign_key'], \$value['relation_table'], \$value['primary_key']);
        }
    }
}
        ";
        write_file($file_location, $data, 'r+');

        $status     = 200;
        $message    = 'Migration file successfully created';
        $this->dhonjson->send(['no_hit' => true, 'status' => $status, 'message' => $message]);
    }
}
