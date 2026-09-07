<?php
session_start();

// if ($_SERVER["REMOTE_ADDR"] != "45.152.96.7" and !isset($_COOKIE["doo"])) exit;

define('CMSshopDEFINE', '1');

require_once("config/common.php");

$page = string_sanitaze($_GET["page"]);
$page2 = string_sanitaze($_GET["page2"]);
$page3 = string_sanitaze($_GET["page3"]);

if ($_GET["logout"] == 1) {
  $query = $GLOBALS["db"]->prepare("DELETE FROM admins_logs WHERE unique_code=:unique_code")->execute(["unique_code" => $_COOKIE["loginADMIN_unique_code"]]);

  setcookie("loginADMIN", "", time(), "/");
  setcookie("loginADMIN_unique_code", "", time(), "/");
  header("Location: login.php");
  exit;
}

if (!isset($_COOKIE["loginADMIN"]) or !isset($_COOKIE["loginADMIN_unique_code"])) {
  header("Location:?logout=1");
} else {
  setcookie("loginADMIN", "$_COOKIE[loginADMIN]", time() + 60 * 60 * 3, "/");
}

echo "oook";