<?php
class Database {

	public $conn;

	public function getConnection() {

		$host = $GLOBALS["db_host"];
		$db_name = $GLOBALS["db_name"];
		$username = $GLOBALS["db_username"];
		$password = $GLOBALS["db_password"];

		$this->conn = null;

		try {
			$this->conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
		} catch(PDOException $exception){
			echo "Connection error: " . $exception->getMessage();
		}
		$this->conn->exec("set names utf8");

		$this->conn->prepare("SET SESSION sql_mode=''")->execute();

		return $this->conn;
	}

}


function pdo_prepare_insert( $data ) {
  $prepare = [];
  foreach ( array_keys($data) as $key => $value ) {
    $prepare[] = "$value = :$value";
  }
  return implode( ", ", $prepare );
}