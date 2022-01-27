<?php

Class DhonDB {
    public $version;
    public $table;
    public $constraint;
    public $unique;
    public $ai;
    public $default;
    public $fields = [];

    public function __construct()
	{
        $this->dhondb =& get_instance();

        $this->dhondb->load->dbforge();

        $this->dbforge  = $this->dhondb->dbforge;
        $this->db       = $this->dhondb->db;
    }

    public function constraint(string $value)
    {
        $this->constraint = $value;
        return $this;
    }

    public function unique()
    {
        $this->unique = TRUE;
        return $this;
    }

    public function ai()
    {
        $this->ai = TRUE;
        return $this;
    }

    public function default($value)
    {
        $this->default = $value;
        return $this;
    }

    public function field(string $field_name, string $type, string $nullable = '')
    {
        $field_data['type'] = $type;

        if ($this->constraint !== '')   $field_data['constraint']       = $this->constraint;
        if ($this->unique === TRUE)     $field_data['unique']           = $this->unique;
        if ($this->ai === TRUE)         $field_data['auto_increment']   = $this->ai;
        if ($this->default !== '')      $field_data['default']          = $this->default;
        if ($nullable === 'nullable')   $field_data['null']             = TRUE;

        $field_element = [
            $field_name => $field_data
        ];

        $this->fields = array_merge($this->fields, $field_element);
        $this->constraint = '';
        $this->unique = FALSE;
        $this->ai = FALSE;
        $this->default = '';
    }

    public function add_key(string $field_name)
    {
        $this->dbforge->add_key($field_name, TRUE);
    }

    public function create_table()
    {
        $this->db->table_exists($this->table) ? $this->dbforge->drop_table($this->table) : false;
        $this->dbforge->add_field($this->fields);
        $this->dbforge->create_table($this->table);

        $this->fields = [];
    }

    public function insert(array $value)
    {
        $fields = $this->db->list_fields($this->table);
        $values = in_array('stamp', $fields) ? array_merge($value, ['stamp' => time()]) : $value;
        $this->db->insert($this->table, $values);
    }

    public function migrate(string $classname)
    {
        $path = ENVIRONMENT == 'testing' || ENVIRONMENT == 'development' ? "\\" : "/";
        require APPPATH."migrations{$path}{$this->version}_{$classname}.php";
        $migration_name = "Migration_{$classname}";
        $migration      = new $migration_name();

        $this->table = 'migrations';
        $this->constraint('20')->field('version', 'BIGINT');
        $this->create_table();
        $this->db->insert($this->table, ['version' => $this->version]);

        $migration->up();
    }
}