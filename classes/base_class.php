<?php
require_once('config.php');

class DB_connection {
    protected $db_conn;
    protected $db = 'stylersonline_test';
    
    function __construct()
    {
        self::connect_to_db();
    }
    
    function get_connection(){
        return $this->db_conn;
    }
    
    private function create_database(){
        try {
            $dbh = new PDO("mysql:host=localhost", 'root');

            $dbh->exec("CREATE DATABASE `".$this->db."`")
            or die(print_r($dbh->errorInfo(), true));

        } catch (PDOException $e) {
            die("DB ERROR: ". $e->getMessage());
        }
    }
    
    private function pdo_connection_with_db_name(){
        return new PDO('mysql:host=localhost;dbname='.$this->db.'', 'root');
    }
    
    private function connect_to_db(){
        try {
            return self::pdo_connection_with_db_name();
        } catch (PDOException $e) {
            self::create_database();
            $this->db_conn = self::pdo_connection_with_db_name();
            new Table_creator($this->db_conn);
        }
    }
}

class Table_creator {
    function __construct($db_conn)
    {
        self::create_leaves_table($db_conn);
        self::create_items_of_leaf_table($db_conn);
    }
    
    private function create_leaves_table($db){
        $db->exec("CREATE TABLE `leaves` (`id` int(6) unsigned NOT NULL AUTO_INCREMENT,`parent_id` int(6),`level` int(4), PRIMARY KEY (`id`)) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;");
    }
    private function create_items_of_leaf_table($db){
        $db->exec("CREATE TABLE `items_of_leaf` (`id` int(6) unsigned NOT NULL AUTO_INCREMENT,`leaf_id` int(6),`key` VARCHAR(10),`value` VARCHAR(10), PRIMARY KEY (`id`)) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;");
    }
}

class DownloadTree {
    protected $tree;

    function __construct()
    { 
        //CHANGE THIS
        $username='tesztfeladat';
        $password='tesztfeladat';
        $url='http://stylersdev.com/teszt_feladat/adat.json';


        $ch = curl_init ();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_USERPWD,"$username:$password");
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec ($ch); 
        $this->tree = json_decode(utf8_encode($result),True);
    }

    function get_tree(){
        return $this->tree;
    }
}

class StoreTree extends DownloadTree {
    protected $tree;
    protected $key_word = Array();
    protected $db_conn;
    
    function __construct()
    {
        parent::__construct();
        $db_conn_obj = new DB_connection();
        $this->db_conn = $db_conn_obj->get_connection();
        $this->tree = parent::get_tree();
    }
    
    private function do_binary_action($leaf,$level){
        $numbers = array();
        foreach ($leaf as $key=>$value) {
            if ($key != 'childs') {
                array_push($numbers,$value);
            }
        }
        
        if ($numbers[0] & $numbers[1] & $level) {
            foreach ($leaf as $key=>$value) {
                if ($value == $numbers[1]) {
                    return $key;
                }
            }
        }
        else {
            foreach ($leaf as $key=>$value) {
                if ($value == $numbers[0]) {
                    return $key;
                }
            }
        }
    }
    
    private function store_tree_into_db($leaf,$parent_id,$level){
        $sql = 'INSERT INTO leaves (parent_id,level) values(:parent_id,:level)';
        $prepared_sql = $this->db_conn->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $prepared_sql->execute(array(':parent_id' => $parent_id,':level' => $level));
        $leaf_id =  $this->db_conn->lastInsertId();
        
        foreach ($leaf as $key=>$value) {
            if ($key != 'childs'){
                $sql = 'INSERT INTO items_of_leaf (leaf_id,key,value) values(:leaf_id,:key,:value)';
                $prepared_sql = $this->db_conn->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                $prepared_sql->execute(array(':leaf_id' => $leaf_id,':key' => $key, ':value' => $value));
            }
        }
        
        return $leaf_id;
    }
    
    private function iterate_on_tree($root, $parent_id = 0, $level = 1, $do_binary_action = False, $do_store = False){
        $level++;
        foreach ($root as $value) {
            if ($do_binary_action) {
                array_push($this->key_word,$this->do_binary_action($value,$level));
            }
            if ($do_store){
                $parent_id = $this->store_tree_into_db($value,$parent_id,$level);
            }
            
            if ($value['childs']) {
                $this->iterate_on_tree($value['childs'], $parent_id, $level, $do_binary_action, $do_store);
            }
        }
    }
    
    function get_tree(){
        /*Get word and send it*/
        $this->iterate_on_tree($this->tree,0,0,True);

        $to      = EMAIL_ADDRESS;
        $subject = 'Próbafeladat eredménye';
        $message = join('',$this->key_word);
        $headers = 'From: kerner.orsi@c9.io';
        mail($to, $subject, $message, $headers);

        /*Store the tree in db*/
        //$this->iterate_on_tree($this->tree,0,0,False,True);

        return $this->tree;
    }
}

?>