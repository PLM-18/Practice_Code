<?php

class Database
{
    private static $instance = null;
    private $conn;

    private $servername = "localhost";
    private $dbusername = "";
    private $dbpassword = "";
    private $dbname = "test";

    private function __construct(){
        $this->conn = new mysqli($this->servername, $this->dbusername, $this->dbpassword, $this->dbname);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public static function getInstance(){
        if(self::$instance == null){
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(){
        return $this->conn;
    }
}

