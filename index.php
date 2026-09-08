<?php
session_start();

// if ($_SERVER["REMOTE_ADDR"] != "45.152.96.7" and !isset($_COOKIE["doo"])) exit;

define('CMSshopDEFINE', '1');

require_once("config/common.php");

$page = $_GET["page"] ?? "";
$page2 = $_GET["page2"] ?? "";
$page3 = $_GET["page3"] ?? "";

$page = string_sanitaze($page);
$page2 = string_sanitaze($page2);
$page3 = string_sanitaze($page3);

if (isset($_GET["logout"]) && $_GET["logout"] == 1) {
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

?>
<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0b5d3b">
  <title>Tréningy | MFK Revúca</title>
  <link rel="stylesheet" href="css/css.css?<?=time();?>">
</head>
<body>
  <div class="site">
    <header class="site-header">
      <a class="brand" href="index.php" aria-label="Tréningy MFK Revúca – úvod">
        <span class="brand-mark" aria-hidden="true">MFK</span>
        <span class="brand-text">
          <strong>Tréningy</strong>
          <small>MFK Revúca</small>
        </span>
      </a>
      <nav class="site-nav" aria-label="Hlavná navigácia">
        <a class="site-nav-link is-active" href="index.php">Prehľad</a>
        <a class="site-nav-link" href="?logout=1">Odhlásiť sa</a>
      </nav>
    </header>
    <main class="site-main">
      <section class="content-card">
        <p class="eyebrow">MFK Revúca</p>
        <h1>Tréningy</h1>
        <p class="content-lead">Administrácia tréningov je pripravená na ďalší obsah.</p>
      </section>
    </main>
  </div>
</body>
</html>