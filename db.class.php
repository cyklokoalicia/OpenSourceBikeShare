<?php

class Database
   {

   function __construct($dbserver,$dbuser,$dbpassword,$dbname)
      {
         $this->dbserver=$dbserver;
         $this->dbuser=$dbuser;
         $this->dbpassword=$dbpassword;
         $this->dbname=$dbname;
      }

   function connect()
      {
         $this->conn=new mysqli($this->dbserver,$this->dbuser,$this->dbpassword,$this->dbname);
         $this->conn->autocommit(FALSE);
         if (!$this->conn) error('db connection error!');
         return $this->conn;
      }

   function query($query)
      {
         $result=$this->conn->query($query);
         return $result;
      }

}

?>