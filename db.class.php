<?php

class Database
{

    function __construct($dbserver, $dbuser, $dbpassword, $dbname)
    {
        $this->dbserver=$dbserver;
        $this->dbuser=$dbuser;
        $this->dbpassword=$dbpassword;
        $this->dbname=$dbname;
    }

    function connect()
    {
        $this->conn=new mysqli($this->dbserver, $this->dbuser, $this->dbpassword, $this->dbname);
        $this->conn->set_charset("utf8");
        $this->conn->autocommit(false);
        if (!$this->conn or $this->conn->connect_errno) {
            error(_('DB connection error!'));
        }
        return $this->conn;
    }

    function query($query)
    {
        $result=$this->conn->query($query);
        if (!$result) {
            error(_('DB error').' '.$this->conn->error.' '._('in').': '.$query);
        }
        return $result;
    }

    function insertid()
    {
        return $this->conn->insert_id;
    }
}
