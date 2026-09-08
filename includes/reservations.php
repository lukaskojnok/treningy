<?php
// Spolocna logika pre stranku a JSON API. PHP 7.4+; PDO MySQL; InnoDB.
class ReservationError extends RuntimeException {
  public $status;
  public function __construct($message, $status = 422) {
    parent::__construct($message);
    $this->status = $status;
  }
}

function reservation_duplicate_logins() {
  // PHP duplicitne kluce potichu prepise. Zistime ich pred povolenim zapisu.
  $tokens = token_get_all(file_get_contents(__DIR__ . '/../config/consts.php'));
  $inside = false;
  $depth = 0;
  $seen = [];
  $duplicates = [];
  foreach ($tokens as $index => $token) {
    if (!$inside) {
      if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && trim($token[1], "'\"") === 'TRENERI') $inside = true;
      continue;
    }
    if ($token === '[') { $depth++; continue; }
    if ($token === ']') { $depth--; if ($depth === 0) break; continue; }
    if ($depth !== 1 || !is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
    $next = $index + 1;
    while (isset($tokens[$next]) && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $next++;
    if (!isset($tokens[$next]) || !is_array($tokens[$next]) || $tokens[$next][0] !== T_DOUBLE_ARROW) continue;
    $login = trim($token[1], "'\"");
    if (isset($seen[$login])) $duplicates[] = $login;
    $seen[$login] = true;
  }
  return array_unique($duplicates);
}

function reservation_context(PDO $db) {
  $login = $_COOKIE['loginADMIN'] ?? '';
  $token = $_COOKIE['loginADMIN_unique_code'] ?? '';
  if (!is_string($login) || !is_string($token) || $login === '' || !preg_match('/^[a-f0-9]{128}$/D', $token)) throw new ReservationError('Prihlásenie vypršalo. Prihlás sa znova.', 401);
  $query = $db->prepare( "SELECT a.login FROM admins a INNER JOIN admins_logs l ON l.login=a.login WHERE a.login=:login AND a.active=1 AND l.unique_code=:token AND l.date_last_do > NOW() - INTERVAL 1 DAY LIMIT 1" );
  $query->execute([ 'login' => $login, 'token' => $token ]);
  $admin = $query->fetch(PDO::FETCH_ASSOC);
  // Overeny login z DB, nie samostatna hodnota cookie.
  if (!$admin || $admin['login'] !== $login) throw new ReservationError('Prihlásenie vypršalo. Prihlás sa znova.', 401);
  $query = $db->prepare( "UPDATE admins_logs SET date_last_do=NOW() WHERE login=:login AND unique_code=:token" );
  $query->execute([ 'login' => $login, 'token' => $token ]);
  $coach = TRENERI[$login] ?? [];
  $teams = [];
  $team_limits = [];
  $warnings = [];
  if (!$coach) $warnings[] = 'Tvoj login nemá záznam v TRENERI. Rezervácie môžeš iba prezerať.';
  if (in_array($login, reservation_duplicate_logins(), true)) {
    $warnings[] = 'Tvoj login je v TRENERI uvedený viackrát. Kým sa opraví konfigurácia, zápis je zablokovaný.';
  } else {
    foreach (array_keys($coach['timy'] ?? []) as $key) {
      if (isset(TEAMS[$key])) { $teams[$key] = TEAMS[$key]; $team_limits[$key] = reservation_team_a_limit($key); }
      else $warnings[] = 'Tím ' . $key . ' chýba v TEAMS a nemožno ho rezervovať.';
    }
  }
  if (empty($coach['meno'])) { $teams = []; $warnings[] = 'V TRENERI chýba meno trénera.'; }
  if (empty($_SESSION['reservations_csrf'])) $_SESSION['reservations_csrf'] = bin2hex(random_bytes(32));
  return [ 'login' => $login, 'coach' => $coach['meno'] ?? '', 'teams' => (object) $teams, 'teamLimits' => (object) $team_limits, 'csrf' => $_SESSION['reservations_csrf'], 'warnings' => $warnings, 'trainingWindow' => reservation_training_window() ];
}

function reservation_team_allowed($context, $team) {
  return isset(((array) $context['teams'])[$team]);
}

function reservation_date($value) {
  if (!is_string($value)) return false;
  $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
  return $date && $date->format('Y-m-d') === $value && $value >= '2000-01-01' && $value <= '2100-12-31';
}

function reservation_timezone() {
  static $timezone;
  if (!$timezone) $timezone = new DateTimeZone(defined('REZERVACIE_CASOVE_PASMO') ? REZERVACIE_CASOVE_PASMO : 'Europe/Bratislava');
  return $timezone;
}

function reservation_now() {
  return new DateTimeImmutable('now', reservation_timezone());
}

function reservation_training_window() {
  $today = reservation_now()->setTime(0, 0);
  $open_day = defined('TRENINGY_DEN_OTVORENIA_DALSIEHO_TYZDNA') ? (int) TRENINGY_DEN_OTVORENIA_DALSIEHO_TYZDNA : 4;
  if ($open_day < 1 || $open_day > 7) $open_day = 4;
  $day_names = [ 1 => 'pondelka', 2 => 'utorka', 3 => 'stredy', 4 => 'štvrtka', 5 => 'piatka', 6 => 'soboty', 7 => 'nedele' ];
  $from = $today->modify('monday next week');
  return [ 'isOpen' => (int) $today->format('N') >= $open_day, 'openDay' => $open_day, 'openDayLabel' => $day_names[$open_day], 'from' => $from->format('Y-m-d'), 'to' => $from->modify('+6 days')->format('Y-m-d') ];
}

function reservation_is_started($date, $start) {
  $event_start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . substr($start, 0, 5), reservation_timezone());
  return !$event_start || $event_start <= reservation_now();
}

function reservation_week_range($date) {
  $day = new DateTimeImmutable($date, reservation_timezone());
  $from = $day->modify('monday this week');
  return [ $from->format('Y-m-d'), $from->modify('+6 days')->format('Y-m-d') ];
}

function reservation_training_date_allowed($valid, $old) {
  if ($valid['type'] !== 'training') return;
  if ($old && $old['event_type'] === 'training') {
    [ $old_week_from, $old_week_to ] = reservation_week_range($old['reservation_date']);
    if ($valid['date'] >= $old_week_from && $valid['date'] <= $old_week_to) return;
  }
  $window = reservation_training_window();
  if (!$window['isOpen']) throw new ReservationError('Tréningy na budúci týždeň môžeš pridávať až od ' . $window['openDayLabel'] . '.', 403);
  if ($valid['date'] < $window['from'] || $valid['date'] > $window['to']) throw new ReservationError('Tréning môžeš pridať iba na budúci týždeň od pondelka do nedele.', 403);
}

function reservation_team_a_limit($team) {
  $rules = defined('TEAMS_PODMIENKY') ? (TEAMS_PODMIENKY[$team] ?? []) : [];
  if (in_array('Acko_viackrat_za_tyzden', $rules, true)) return null;
  if (in_array('Acko_1x_za_tyzden', $rules, true)) return 1;
  if (in_array('Acko_2x_za_tyzden', $rules, true)) return 2;
  return 0;
}

function reservation_validate($data, $context) {
  foreach (['type', 'team', 'field', 'area', 'date', 'start', 'end', 'note'] as $key) {
    if (isset($data[$key]) && !is_string($data[$key])) throw new ReservationError('Neplatný formát údajov.');
  }
  $type = $data['type'] ?? '';
  if (!in_array($type, ['training', 'match'], true)) throw new ReservationError('Vyber, či ide o tréning alebo zápas.');
  $team = $data['team'] ?? '';
  if (!reservation_team_allowed($context, $team)) throw new ReservationError('Tento tím nemáš priradený v TRENERI.', 403);
  $date = $data['date'] ?? '';
  $start = $data['start'] ?? '';
  $end = $data['end'] ?? '';
  if (!reservation_date($date) || !preg_match('/^(?:[01][0-9]|2[0-3]):(?:00|30)$/D', $start) || !preg_match('/^(?:[01][0-9]|2[0-3]):(?:00|30)$/D', $end) || $start < '08:00' || $end > '22:00' || $start >= $end) throw new ReservationError('Vyber dátum a čas od 08:00 do 22:00 po 30 minútach. Koniec musí byť po začiatku.');
  $field = $data['field'] ?? '';
  if (!isset(IHRISKA[$field])) throw new ReservationError('Vyber ihrisko.');
  $parts = array_keys(IHRISKA[$field]['parts']);
  $chosen = ($data['area'] ?? '') === 'full' ? $parts : explode(',', $data['area'] ?? '');
  if (!$chosen || count(array_unique($chosen)) !== count($chosen) || array_diff($chosen, $parts)) throw new ReservationError('Neplatný výber častí ihriska.');
  $valid = count($chosen) === 1 || count($chosen) === count($parts);
  if (count($parts) === 4 && count($chosen) === 2) {
    foreach ([[0, 1], [2, 3], [0, 2], [1, 3]] as $pair) {
      if (in_array($parts[$pair[0]], $chosen, true) && in_array($parts[$pair[1]], $chosen, true)) $valid = true;
    }
  }
  if (!$valid) throw new ReservationError('Vyber štvrtinu, dve susedné časti alebo celé ihrisko.');
  $note = trim($data['note'] ?? '');
  $note_length = function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') : preg_match_all('/./us', $note, $matches);
  if ($note_length === false || $note_length > 300) throw new ReservationError('Poznámka je príliš dlhá (najviac 300 znakov).');
  return [ 'type' => $type, 'team' => $team, 'date' => $date, 'start' => $start, 'end' => $end, 'field' => $field, 'parts' => array_values(array_intersect($parts, $chosen)), 'note' => $note ];
}

function reservation_list(PDO $db, $context, $from, $to) {
  if (!reservation_date($from) || !reservation_date($to) || $to < $from || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 62) throw new ReservationError('Neplatný rozsah dátumov (najviac 63 dní).');
  $query = $db->prepare( "SELECT r.*, p.part_key FROM reservations r INNER JOIN reservation_parts p ON p.reservation_id=r.id WHERE r.reservation_date BETWEEN :date_from AND :date_to ORDER BY r.reservation_date, r.start_time, r.id, p.part_key" );
  $query->execute([ 'date_from' => $from, 'date_to' => $to ]);
  $events = [];
  while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $id = (int) $row['id'];
    if (!isset($events[$id])) $events[$id] = [ 'id' => $id, 'version' => (int) $row['version'], 'type' => $row['event_type'], 'team' => $row['team_key'], 'title' => TEAMS[$row['team_key']] ?? $row['team_key'], 'coach' => $row['coach_name'], 'date' => $row['reservation_date'], 'start' => substr($row['start_time'], 0, 5), 'end' => substr($row['end_time'], 0, 5), 'field' => $row['field_key'], 'area' => '', 'note' => $row['note'], 'canEdit' => reservation_team_allowed($context, $row['team_key']) && !reservation_is_started($row['reservation_date'], $row['start_time']) ];
    $events[$id]['area'] .= ($events[$id]['area'] === '' ? '' : ',') . $row['part_key'];
  }
  return array_values($events);
}

function reservation_mutate(PDO $db, $context, $action, $data) {
  $id = $data['id'] ?? 0;
  $version = $data['version'] ?? 0;
  if (filter_var($id, FILTER_VALIDATE_INT) === false || $id < 0 || ($action === 'delete' && $id < 1)) throw new ReservationError('Neplatné ID rezervácie.');
  if ($id && (filter_var($version, FILTER_VALIDATE_INT) === false || $version < 1)) throw new ReservationError('Chýba verzia rezervácie. Obnov kalendár.');
  $valid = $action === 'save' ? reservation_validate($data, $context) : null;
  $db->beginTransaction();
  try {
    $query = $db->prepare( "SELECT id FROM reservation_write_lock WHERE id=1 FOR UPDATE" );
    $query->execute();
    if (!$query->fetchColumn()) throw new RuntimeException('Missing reservation_write_lock row');
    $old = null;
    if ($id) {
      $query = $db->prepare( "SELECT * FROM reservations WHERE id=:id FOR UPDATE" );
      $query->execute([ 'id' => $id ]);
      $old = $query->fetch(PDO::FETCH_ASSOC);
      if (!$old) throw new ReservationError('Rezervácia už bola vymazaná. Obnov kalendár.', 409);
      if (!reservation_team_allowed($context, $old['team_key'])) throw new ReservationError('Túto rezerváciu nemôžeš meniť ani vymazať.', 403);
      if ((int) $old['version'] !== (int) $version) throw new ReservationError('Rezerváciu medzitým zmenil iný používateľ. Zavri detail a otvor ho znova.', 409);
      if (reservation_is_started($old['reservation_date'], $old['start_time'])) throw new ReservationError('Udalosť už začala. Nemožno ju upraviť ani vymazať.', 403);
    }
    if ($action === 'delete') {
      $query = $db->prepare( "DELETE FROM reservations WHERE id=:id" );
      $query->execute([ 'id' => $id ]);
    } else {
      if (reservation_is_started($valid['date'], $valid['start'])) throw new ReservationError('Rezerváciu nemožno uložiť do minulosti ani na čas, ktorý už začal.');
      reservation_training_date_allowed($valid, $old);
      // Aktualne citanie pod zamkom: kontrola plati aj pre dlho otvoreny prehliadac.
      $query = $db->prepare( "SELECT r.id, p.part_key FROM reservations r INNER JOIN reservation_parts p ON p.reservation_id=r.id WHERE r.reservation_date=:date AND r.field_key=:field AND r.start_time < :end AND r.end_time > :start AND r.id <> :id FOR UPDATE" );
      $query->execute([ 'date' => $valid['date'], 'field' => $valid['field'], 'end' => $valid['end'], 'start' => $valid['start'], 'id' => $id ]);
      while ($conflict = $query->fetch(PDO::FETCH_ASSOC)) {
        if (in_array($conflict['part_key'], $valid['parts'], true)) throw new ReservationError('Vybraná plocha je v tomto čase obsadená. Niekto ju mohol rezervovať počas otvorenia formulára. Vyber inú plochu alebo čas.', 409);
      }
      if ($valid['type'] === 'training' && $valid['field'] === 'A') {
        $limit = reservation_team_a_limit($valid['team']);
        if ($limit === 0) throw new ReservationError('Tím nemá v TEAMS_PODMIENKY nastavené pravidlo pre tréning na Áčku.', 403);
        if ($limit !== null) {
          [ $week_from, $week_to ] = reservation_week_range($valid['date']);
          $query = $db->prepare( "SELECT COUNT(*) FROM reservations WHERE team_key=:team AND event_type='training' AND field_key='A' AND reservation_date BETWEEN :week_from AND :week_to AND id<>:id" );
          $query->execute([ 'team' => $valid['team'], 'week_from' => $week_from, 'week_to' => $week_to, 'id' => $id ]);
          if ((int) $query->fetchColumn() >= $limit) throw new ReservationError('Tento tím už vyčerpal povolený počet tréningov na Áčku v danom týždni (' . $limit . '×).', 409);
        }
      }
      $values = [ 'type' => $valid['type'], 'team' => $valid['team'], 'field' => $valid['field'], 'date' => $valid['date'], 'start' => $valid['start'], 'end' => $valid['end'], 'note' => $valid['note'], 'updated_by' => $context['login'] ];
      if ($id) {
        $query = $db->prepare( "UPDATE reservations SET event_type=:type, team_key=:team, field_key=:field, reservation_date=:date, start_time=:start, end_time=:end, note=:note, updated_by=:updated_by, updated_at=NOW(), version=version+1 WHERE id=:id" );
        $query->execute($values + [ 'id' => $id ]);
        $query = $db->prepare( "DELETE FROM reservation_parts WHERE reservation_id=:id" );
        $query->execute([ 'id' => $id ]);
      } else {
        $query = $db->prepare( "INSERT INTO reservations SET event_type=:type, team_key=:team, field_key=:field, reservation_date=:date, start_time=:start, end_time=:end, note=:note, updated_by=:updated_by, coach_login=:coach_login, coach_name=:coach_name, created_by=:created_by" );
        $query->execute($values + [ 'coach_login' => $context['login'], 'coach_name' => $context['coach'], 'created_by' => $context['login'] ]);
        $id = (int) $db->lastInsertId();
      }
      foreach ($valid['parts'] as $part) {
        $query = $db->prepare( "INSERT INTO reservation_parts (reservation_id, part_key) VALUES (:id, :part)" );
        $query->execute([ 'id' => $id, 'part' => $part ]);
      }
    }
    $db->commit();
    return [ 'id' => (int) $id ];
  } catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
  }
}
