<?php
// Samostatny JSON endpoint: nespusta HTML presmerovania ani mailove triedy z common.php.
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
session_start();
require_once __DIR__ . '/../includes/reservations.php';

try {
  require_once __DIR__ . '/../config/config.php';
  require_once __DIR__ . '/../config/consts.php';
  require_once __DIR__ . '/../config/db.c.php';
  $database = new Database();
  $db = $database->getConnection();
  if (!$db instanceof PDO) throw new RuntimeException('Database unavailable');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
  $context = reservation_context($db);
  $method = $_SERVER['REQUEST_METHOD'];
  if ($method !== 'GET' && $method !== 'POST') throw new ReservationError('Nepovolená metóda.', 405);
  if ($method === 'GET') {
    session_write_close();
    $result = [ 'events' => reservation_list($db, $context, $_GET['from'] ?? '', $_GET['to'] ?? '') ];
  } else {
    if (!hash_equals($context['csrf'], (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) throw new ReservationError('Platnosť formulára vypršala. Obnov stránku.', 403);
    session_write_close();
    $body = file_get_contents('php://input', false, null, 0, 20001);
    if (strlen($body) > 20000) throw new ReservationError('Príliš veľká požiadavka.');
    $data = json_decode($body, true);
    if (!is_array($data)) throw new ReservationError('Neplatné údaje požiadavky.');
    $action = $data['action'] ?? '';
    if (!in_array($action, ['save', 'delete'], true)) throw new ReservationError('Neplatná operácia.');
    $result = reservation_mutate($db, $context, $action, $data);
  }
  echo json_encode([ 'ok' => true ] + $result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (ReservationError $error) {
  http_response_code($error->status);
  echo json_encode([ 'ok' => false, 'message' => $error->getMessage() ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
  error_log('Reservations: ' . $error->getMessage());
  http_response_code(500);
  echo json_encode([ 'ok' => false, 'message' => 'Rezervácie sa nepodarilo spracovať. Skús to znova; ak problém trvá, kontaktuj správcu.' ], JSON_UNESCAPED_UNICODE);
}
