<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

if ( $_SERVER["SCRIPT_NAME"] != "/login.php" AND !isset($_COOKIE["loginADMIN"]) ) {
  header("Location: /login.php");
  exit;
}

if ( session_status() == PHP_SESSION_NONE ) {
  session_start();
}

require_once("config.php");
require_once("consts.php");

require_once("db.c.php");

$Database = new Database();
$db = $Database->getConnection();

//pres starý admin
$GLOBALS["connect"] = mysqli_connect($GLOBALS["db_host"], $GLOBALS["db_username"], $GLOBALS["db_password"], $GLOBALS["db_name"]) or die("Failed to connect to MySQL: " . mysqli_error($GLOBALS["connect"]));
mysqli_set_charset($GLOBALS["connect"], "utf8mb4");

if (isset($_COOKIE["loginADMIN"]) AND isset($_COOKIE["loginADMIN_unique_code"])) {
  define("ADMIN_ACTIVE", true);
} else {
  define("ADMIN_ACTIVE", false);
}

require_once("functions.php");

get_params();
define("PARAM", $PARAM);
define("PARAMQ", $PARAMQ);

require_once(BASE_ROOT . "/classes/MailsMy/MailsMy.c.php");

//////////////// LOGIN admin
function unique_code_admin_login( $login, $ip, $user_agent, $session_id ) {
  //return hash('sha512', $login . $ip . $user_agent . $session_id );
  return hash('sha512', $login . $ip . $user_agent);
}

if ( isset($_COOKIE["loginADMIN"]) AND $_COOKIE["loginADMIN"] ) {
  $login = htmlspecialchars($_COOKIE["loginADMIN"]);
  $unique_code = htmlspecialchars($_COOKIE["loginADMIN_unique_code"]);

  $query = $GLOBALS["db"]->prepare( "SELECT * FROM admins_logs WHERE unique_code=:unique_code" );
  $query->execute( ["unique_code" => $unique_code] );
  $uq_control = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];

  $uq_control_code = unique_code_admin_login( $uq_control["login"], $uq_control["ip"], $uq_control["user_agent"], $uq_control["session_id"] );

  if ( $uq_control["unique_code"] == $uq_control_code ) {
    $GLOBALS["db"]->prepare( "UPDATE admins_logs SET date_last_do=NOW() WHERE unique_code=:unique_code" )->execute( ["unique_code" => $uq_control_code] );
  } else {
    $query = $GLOBALS["db"]->prepare( "DELETE FROM admins_logs WHERE unique_code=:unique_code" )->execute( [ "unique_code" => $_COOKIE["loginADMIN_unique_code"] ] );

    setcookie("loginADMIN", "", time(), "/");
    setcookie("loginADMIN_unique_code", "", time(), "/");
    header("Location:login.php");
    exit;
  }
}
//////////////// LOGIN admin


define("PRIMARY_PERMISSIONS", [
  "admins" => "Admini",

]);


$permissions_ADMIN_arr = [];
if (isset($_COOKIE["loginADMIN"])) {
  $query = $GLOBALS["db"]->prepare("SELECT * FROM admins WHERE login=:login");
  $query->execute(["login" => $_COOKIE["loginADMIN"]]);
  $admins_data = $query->rowCount() ? $query->fetch(PDO::FETCH_ASSOC) : [];

  $permissions_arr = explode(",", $admins_data["permissions"]);

  if ($admins_data["login"] === "admin") {
    $permissions_arr = array_keys(PRIMARY_PERMISSIONS);
  }

  $permissions_ADMIN_arr = $permissions_arr;
} else {

}