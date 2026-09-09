 <?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");

$token_login_request = isset($_GET["token"]);
if ($token_login_request) {
  unset($_COOKIE["loginADMIN"], $_COOKIE["loginADMIN_unique_code"]);
}

require_once("config/common.php");

// echo hash('sha512', "cyrilsiman"."".HASH);

$result_log = "";
$page_mode = (string) ($_GET["p"] ?? "login");
$form_gen_1_result = false;
$forgot_2 = "no";
$h = "";
$e = "";
$i = "";

function login_admin(array $admin, PDO $db): void {
  $login = (string) $admin["login"];
  setcookie("loginADMIN", $login, time() + 60 * 60 * 24, "/");

  $remote_address = (string) ($_SERVER["REMOTE_ADDR"] ?? "");
  $user_agent = (string) ($_SERVER["HTTP_USER_AGENT"] ?? "");
  $unique_code = unique_code_admin_login($login, $remote_address, $user_agent, session_id());
  setcookie("loginADMIN_unique_code", $unique_code, time() + 60 * 60 * 24, "/");

  $db->prepare("UPDATE admins SET date_login_last=NOW() WHERE login=:login")->execute(["login" => $login]);
  $db->prepare("DELETE FROM admins_logs WHERE unique_code=:unique_code")->execute(["unique_code" => $unique_code]);

  $query = $db->prepare("INSERT INTO admins_logs SET login=:login, session_id=:session_id, user_agent=:user_agent, ip=:ip, unique_code=:unique_code, date_login=NOW(), date_last_do=NOW()");
  $query->execute([
    "login" => $login,
    "session_id" => session_id(),
    "user_agent" => $user_agent,
    "ip" => $remote_address,
    "unique_code" => $unique_code
  ]);

  $location = (string) ($_SESSION["lastpage"] ?? "/index.php");
  if ($location === "" || preg_match("~^(?:https?:)?//~i", $location)) {
    $location = "/index.php";
  } elseif ($location[0] !== "/") {
    $location = "/" . ltrim($location, "/");
  }

  header("Location: " . $location);
  exit;
}

$query = $db->prepare("SELECT id FROM admins WHERE password_forgotten_to < NOW() AND password_forgotten_to != '0000-00-00 00:00:00'");
$query->execute();
$expired_password_requests = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($expired_password_requests as $expired_password_request) {
  $db->prepare("UPDATE admins SET password_forgotten_hash='', password_forgotten_to='0000-00-00 00:00:00' WHERE id=:id")->execute([
    "id" => $expired_password_request["id"]
  ]);
}

if (isset($_GET["token"])) {
  $token = trim((string) $_GET["token"]);
  $token_is_valid = $token !== "" && strlen($token) <= 255 && preg_match("~^[A-Za-z0-9_-]+$~", $token);
  $token_admins = [];

  if ($token_is_valid) {
    $query = $db->prepare("SELECT * FROM admins WHERE token_login=:token_login AND token_login!='' AND active='1' LIMIT 2");
    $query->execute(["token_login" => $token]);
    $token_admins = $query->fetchAll(PDO::FETCH_ASSOC);
  }

  if (count($token_admins) === 1) {
    login_admin($token_admins[0], $db);
  }

  $result_log = '<div class="alert alert-danger" role="alert">Prihlasovací odkaz nie je platný.</div>';
}

if (($_POST["form"] ?? "") === "1") {
  $login = trim((string) ($_POST["login"] ?? ""));
  $password = (string) ($_POST["heslo"] ?? "");

  $query = $db->prepare("SELECT * FROM admins WHERE login=:login AND active='1'");
  $query->execute(["login" => $login]);
  $admin = $query->fetch(PDO::FETCH_ASSOC) ?: [];

  $password_hash = "";
  if (!empty($admin["login"]) && $password !== "") {
    $password_hash = hash("sha512", $admin["login"] . $password . HASH);
  }

  $login_is_valid = $login !== "" && $password !== "" && isset($admin["login"], $admin["password"]) && hash_equals((string) $admin["password"], $password_hash);

  if ($login_is_valid) {
    login_admin($admin, $db);
  }

  $result_log = '<div class="alert alert-danger" role="alert">Zadali ste nesprávne prihlasovacie meno alebo heslo.</div>';
}

if (($_POST["form_gen_1"] ?? "") === "1") {
  $email = trim((string) ($_POST["email"] ?? ""));

  $query = $db->prepare("SELECT * FROM admins WHERE email=:email");
  $query->execute(["email" => $email]);
  $admin = $query->fetch(PDO::FETCH_ASSOC) ?: [];

  if (empty($admin["email"])) {
    $result_log = '<div class="alert alert-danger" role="alert">Daný e-mail nie je v databáze.</div>';
  } else {
    $password_forgotten_hash = getRandomString("20");

    $query = $db->prepare("UPDATE admins SET password_forgotten_hash=:password_forgotten_hash, password_forgotten_to=NOW() + INTERVAL 1 HOUR WHERE email=:email");
    $query->execute([
      "password_forgotten_hash" => $password_forgotten_hash,
      "email" => $email
    ]);

    $password_link = WEB_URL . "/login.php?p=forgot2&h=" . urlencode($password_forgotten_hash) . "&e=" . urlencode($admin["email"]) . "&i=" . urlencode((string) $admin["id"]);
    $text_mail = 'Po kliknutí na tento odkaz si zadáte nové heslo:<br><a href="' . htmlspecialchars($password_link, ENT_QUOTES, "UTF-8") . '">' . htmlspecialchars($password_link, ENT_QUOTES, "UTF-8") . '</a>';

    $emailMy = new MailsMy();
    $emailMy->EMAIL_DATA = [
      "to" => $admin["email"],
      "subject" => "Správa z " . WEB_URL_NAME,
      "text" => $text_mail,
      "attachments" => []
    ];
    $emailMy->sendMail();

    $result_log = '<div class="alert alert-success" role="status">Odoslali sme vám e-mail na vytvorenie nového hesla. Odkaz platí jednu hodinu.</div>';
    $form_gen_1_result = true;
  }
}

if ($page_mode === "forgot2") {
  $h = (string) ($_GET["h"] ?? "");
  $e = (string) ($_GET["e"] ?? "");
  $i = (string) ($_GET["i"] ?? "");

  if ($h !== "" && $e !== "" && $i !== "") {
    $query = $db->prepare("SELECT * FROM admins WHERE password_forgotten_hash=:password_forgotten_hash AND email=:email AND id=:id AND password_forgotten_to > NOW()");
    $query->execute([
      "password_forgotten_hash" => $h,
      "email" => $e,
      "id" => $i
    ]);
    $admin_forgot2 = $query->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!empty($admin_forgot2["id"])) {
      $forgot_2 = "yes";
    }
  }

  if ($forgot_2 === "yes" && ($_POST["form_gen_2"] ?? "") === "1") {
    $password_1 = (string) ($_POST["password_1"] ?? "");
    $password_2 = (string) ($_POST["password_2"] ?? "");

    if ($password_1 !== $password_2) {
      $result_log = '<div class="alert alert-danger" role="alert">Heslá sa nezhodujú.</div>';
    } elseif (mb_strlen($password_1, "UTF-8") < 6) {
      $result_log = '<div class="alert alert-danger" role="alert">Heslo musí mať minimálne 6 znakov.</div>';
    } else {
      $password_save = hash("sha512", $admin_forgot2["login"] . $password_1 . HASH);

      $query = $db->prepare("UPDATE admins SET password_forgotten_hash='', password_forgotten_to='0000-00-00 00:00:00', password=:password WHERE id=:id");
      $query->execute([
        "password" => $password_save,
        "id" => $admin_forgot2["id"]
      ]);

      header("Location: login.php?pok=1");
      exit;
    }
  }

  if ($forgot_2 === "no") {
    $result_log = '<div class="alert alert-danger" role="alert">Platnosť odkazu na vytvorenie hesla vypršala.</div>';
  }
}

if (($_GET["pok"] ?? "") === "1") {
  $result_log = '<div class="alert alert-success" role="status">Heslo bolo úspešne zmenené. Teraz sa môžete prihlásiť.</div>';
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0b5d3b">
  <title>Prihlásenie | Tréningy MFK Revúca</title>
  <link rel="stylesheet" href="/css/css.css?v=2">
  <link rel="stylesheet" href="/css/responsive.css?v=20260908-1">
</head>
<body class="auth-body">
  <div class="wrapper">
    <header class="sp-header">
      <a class="brand" href="login.php" aria-label="Tréningy MFK Revúca">
        <span class="brand-mark" aria-hidden="true">MFK</span>
        <span class="brand-text">
          <strong>Tréningy</strong>
          <small>MFK Revúca</small>
        </span>
      </a>
    </header>
    <main class="page-wrapper auth-page">
      <div class="container-fluid">
        <div class="table-struct">
          <div class="table-cell auth-form-wrap">
            <div class="auth-form">
              <?= $result_log ?>
              <?php if ($page_mode === "forgot" && !$form_gen_1_result) { ?>
                <div class="mb-30">
                  <h3>Zabudnuté heslo</h3>
                  <p class="auth-subtitle">Obnova prístupu do správy tréningov</p>
                </div>
                <div class="form-wrap">
                  <form action="?p=forgot" method="post">
                    <div class="form-group">
                      <label class="control-label" for="email">E-mail</label>
                      <input class="form-control" id="email" name="email" type="email" autocomplete="email" required autofocus>
                    </div>
                    <div class="form-group">
                      <button class="btn btn-primary" type="submit">Poslať odkaz</button>
                    </div>
                    <input name="form_gen_1" type="hidden" value="1">
                  </form>
                  <a class="auth-back" href="login.php">Späť na prihlásenie</a>
                </div>
              <?php } elseif ($page_mode === "forgot2" && $forgot_2 === "yes") { ?>
                <div class="mb-30">
                  <h3>Nové heslo</h3>
                  <p class="auth-subtitle">Zadajte nové heslo s minimálne šiestimi znakmi</p>
                </div>
                <div class="form-wrap">
                  <form action="?p=forgot2&amp;h=<?= urlencode($h) ?>&amp;e=<?= urlencode($e) ?>&amp;i=<?= urlencode($i) ?>" method="post">
                    <div class="form-group">
                      <label class="control-label" for="password-1">Nové heslo</label>
                      <input class="form-control" id="password-1" name="password_1" type="password" minlength="6" autocomplete="new-password" required autofocus>
                    </div>
                    <div class="form-group">
                      <label class="control-label" for="password-2">Zopakujte heslo</label>
                      <input class="form-control" id="password-2" name="password_2" type="password" minlength="6" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                      <button class="btn btn-primary" type="submit">Uložiť nové heslo</button>
                    </div>
                    <input name="form_gen_2" type="hidden" value="1">
                  </form>
                  <a class="auth-back" href="login.php">Späť na prihlásenie</a>
                </div>
              <?php } elseif ($page_mode === "login" || $form_gen_1_result || isset($_GET["pok"])) { ?>
                <div class="mb-30">
                  <h3>Prihlásenie</h3>
                  <p class="auth-subtitle">Správa tréningov MFK Revúca</p>
                </div>
                <?php if (!$form_gen_1_result) { ?>
                  <div class="form-wrap">
                    <form action="login.php" method="post">
                      <div class="form-group">
                        <label class="control-label" for="login">Prihlasovacie meno</label>
                        <input class="form-control" id="login" name="login" type="text" autocomplete="username" required autofocus>
                      </div>
                      <div class="form-group">
                        <label class="control-label" for="password">Heslo</label>
                        <input class="form-control" id="password" name="heslo" type="password" autocomplete="current-password" required>
                        <a class="auth-back" href="?p=forgot">Zabudnuté heslo?</a>
                      </div>
                      <div class="form-group">
                        <button class="btn btn-primary" type="submit">Prihlásiť sa</button>
                      </div>
                      <input name="form" type="hidden" value="1">
                    </form>
                  </div>
                <?php } else { ?>
                  <a class="btn btn-primary" href="login.php">Späť na prihlásenie</a>
                <?php } ?>
              <?php } else { ?>
                <a class="btn btn-primary" href="login.php">Späť na prihlásenie</a>
              <?php } ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
