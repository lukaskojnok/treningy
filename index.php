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
  $query = $db->prepare("DELETE FROM admins_logs WHERE unique_code=:unique_code")->execute(["unique_code" => ($_COOKIE["loginADMIN_unique_code"] ?? "")]);

  setcookie("loginADMIN", "", time(), "/");
  setcookie("loginADMIN_unique_code", "", time(), "/");
  header("Location: login.php");
  exit;
}

if (!isset($_COOKIE["loginADMIN"]) or !isset($_COOKIE["loginADMIN_unique_code"])) {
  header("Location:?logout=1");
  exit;
} else {
  setcookie("loginADMIN", "$_COOKIE[loginADMIN]", time() + 60 * 60 * 3, "/");
}

require_once __DIR__ . '/includes/reservations.php';
try {
  $reservation_context = reservation_context($db);
} catch (ReservationError $error) {
  header('Location: login.php');
  exit;
}

?>
<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Rezervácie ihrísk | MFK Revúca</title>
  <link rel="stylesheet" href="css/css.css?v=20260908-rules1">
  <script type="application/json" id="pitch-config"><?= json_encode(IHRISKA, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <script type="application/json" id="reservation-config"><?= json_encode($reservation_context, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <script src="js/calendar.js?v=20260908-match-a" defer></script>
</head>
<body class="planner-body">
  <main class="planner">
    <header class="topbar">
      <div class="heading"><span class="eyebrow">MFK REVÚCA / PLÁNOVANIE</span><h1>Rezervácie ihrísk</h1></div>
      <span class="demo-label">Prihlásený: <?= htmlspecialchars($reservation_context["coach"] ?: $reservation_context["login"], ENT_QUOTES, "UTF-8") ?></span>
      <button class="button primary" id="add-event">＋ Nová rezervácia</button>
      <div class="menu-wrap">
        <button class="button menu-toggle" id="menu-toggle" aria-label="Hlavné menu" aria-expanded="false" aria-controls="main-menu"><span></span><span></span><span></span></button>
        <nav id="main-menu" hidden aria-label="Hlavná navigácia"><strong>Tréningy · MFK Revúca</strong><a href="index.php">Prehľad</a><button type="button" id="menu-calendar">Kalendár rezervácií</button><button type="button" id="menu-day">Denný kalendár častí</button><button type="button" id="menu-fields">Prehľad ihrísk</button><a href="?logout=1">Odhlásiť sa ↗</a></nav>
      </div>
    </header>
    <div id="calendar-status" role="status" hidden></div><button type="button" class="button" id="reload-calendar" hidden>Obnoviť kalendár</button>
    <section class="toolbar" aria-label="Ovládanie kalendára">
      <div class="switch"><button class="button selected" id="view-calendar" aria-pressed="true">Týždeň</button><button class="button" id="view-day" aria-pressed="false">Deň · časti ihrísk</button><button class="button" id="view-fields" aria-pressed="false">Ihriská a plochy</button></div>
      <div class="type-filter" role="group" aria-label="Filtrovať typ udalosti"><button type="button" class="button selected" data-type-filter="all" aria-pressed="true">Všetko</button><button type="button" class="button" data-type-filter="training" aria-pressed="false">Tréningy</button><button type="button" class="button match-filter" data-type-filter="match" aria-pressed="false">Zápasy</button></div>
      <div class="date-nav"><button class="button" id="prev" aria-label="Predchádzajúci týždeň">←</button><button class="button" id="today">Dnes</button><button class="button" id="next" aria-label="Nasledujúci týždeň">→</button><h2 id="date-heading"></h2></div>
      <div class="filter"><button type="button" class="button" id="open-field-filter" aria-haspopup="dialog">Ihriská a časti: <span id="filter-summary">Všetky ihriská</span> ▦</button></div>
    </section>
    <div class="legend"><div id="field-legend"></div><span class="legend-tip" id="calendar-tip">Rezervácie ihrísk</span><button class="button" id="morning">Od 08:00</button><button class="button" id="afternoon">Od 14:00</button></div>
    <section id="calendar-view" aria-label="Týždenný kalendár"><div id="calendar"></div></section>
    <section id="fields-view" hidden aria-label="Obsadenosť ihrísk">
      <div class="field-controls"><div><h2>Obsadenosť plôch</h2><p>Vyber dátum a čas. Rezervácie sa zobrazia priamo na ihrisku.</p></div><label>Dátum <input type="date" id="field-date"></label><input type="hidden" id="field-time" value="16:00"><div class="time-stepper"><button type="button" class="button" id="time-prev" aria-label="O 30 minút skôr">←</button><strong id="selected-time"></strong><button type="button" class="button" id="time-next" aria-label="O 30 minút neskôr">→</button></div></div>
      <div id="time-slots" class="time-slots" role="group" aria-label="Čas obsadenosti po 30 minútach"></div><div id="pitches"></div>
    </section>
    <footer class="planner-footer"><span>Spoločný kalendár trénerov</span><span>08:00 – 22:00 · rezervácie ihrísk</span></footer>
  </main>
  <dialog id="event-dialog" aria-labelledby="dialog-title">
    <form id="event-form">
      <div class="dialog-heading"><div><p class="eyebrow">REZERVÁCIA PLOCHY</p><h2 id="dialog-title">Nová rezervácia</h2></div><button class="button" type="button" id="close-dialog" aria-label="Zavrieť">✕</button></div>
      <p class="dialog-note">Upravovať môžeš rezervácie tímov, ktoré máš priradené. Obsadenosť sa overí pri uložení.</p>
      <fieldset class="event-type-picker"><legend>Čo pridávaš?</legend><label><input type="radio" name="type" value="training" required><span>Tréning<small>Len budúci týždeň, po otvorení termínov</small></span></label><label><input type="radio" name="type" value="match" required><span>Zápas<small>Bez časového predstihu a limitu Áčka</small></span></label></fieldset>
      <p id="type-help" class="type-help"></p>
      <label>Tím<select name="team" required></select></label>
      <label>Tréner<input name="coach" readonly></label>
      <fieldset class="area-picker"><legend>Ihrisko a plocha</legend><button type="button" class="button" id="open-booking-map" aria-haspopup="dialog">▦ Vybrať z mapy ihrísk</button><strong id="booking-area-summary">Vyber plochu</strong><input type="hidden" name="field"><input type="hidden" name="area"></fieldset>
      <div class="form-grid three"><label>Dátum<input type="date" name="date" required></label><label>Od<input type="time" name="start" min="08:00" max="21:30" step="1800" required></label><label>Do<input type="time" name="end" min="08:30" max="22:00" step="1800" required></label></div>
      <label>Poznámka<textarea name="note" rows="2" maxlength="300" placeholder="Pomôcky, zameranie tréningu…"></textarea></label>
      <p id="form-error" role="alert"></p>
      <div class="dialog-actions"><button class="button danger" type="button" id="delete-event" hidden>Vymazať</button><span></span><button class="button" type="button" id="cancel-dialog">Zrušiť</button><button class="button primary" id="save-event" type="submit">Uložiť rezerváciu</button></div>
    </form>
  </dialog>
  <dialog id="pitch-picker" aria-labelledby="picker-title">
    <div class="dialog-heading"><h2 id="picker-title">Výber ihrísk a častí</h2><button type="button" class="button" id="picker-close" aria-label="Zavrieť mapu">✕</button></div>
    <p id="picker-help" class="dialog-note"></p>
    <div id="picker-map" class="area-map"></div>
    <p id="picker-summary" aria-live="polite"></p>
    <p id="picker-error" role="alert" class="danger"></p>
    <div class="dialog-actions"><button type="button" class="button" id="picker-all">Všetky ihriská</button><button type="button" class="button" id="picker-clear">Zrušiť výber</button><span></span><button type="button" class="button" id="picker-cancel">Zavrieť bez zmeny</button><button type="button" class="button primary" id="picker-apply">Použiť výber</button></div>
  </dialog>
</body>
</html>
