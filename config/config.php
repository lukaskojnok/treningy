<?php
include("psw.php");

$GLOBALS["pageURL2"] = "trenigny.mfkrevuca.sk";

define("START_ROCNIK", "2026");
define("ROCNIK", "2026/2027");

if (!defined('BASE_ROOT')) {
  define('BASE_ROOT', rtrim(realpath(__DIR__ . '/../'), '/') . '/');
}



define("DATAS_ROOT_SHORT", "data/");
define("DATAS_ROOT", BASE_ROOT . DATAS_ROOT_SHORT);

// define("IP_ADDRESS", $_SERVER["HTTP_CF_CONNECTING_IP"]);
define("IP_ADDRESS", $_SERVER["REMOTE_ADDR"]);

define("COUNTRY", $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');
define("COUNTRY_NAME", $_SERVER["HTTP_GEOIP_COUNTRY_NAME"] ?? '');
define("COUNTRY_CODE", $_SERVER["GEOIP_COUNTRY_CODE"] ?? '');

define("HTTP_USER_AGENT", $_SERVER["HTTP_USER_AGENT"]);

define("DPH", 23);

define("WEB_NAME", "MFK Revúca");
// define("WEB_URL_SK", "https://www.mfkrevuca.sk");
define("WEB_URL_SK", "https://www.mfkrevuca.sk");
define("WEB_URL", $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"]);
define("WEB_URL_ACTUAL", $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
define("WEB_URL_NAME", "mfkrevuca.sk");
define("WEB_EMAIL_1", "info@mfkrevuca.sk");
define("WEB_PHONE_1", "0000 111 222");

$page = ( isset( $_GET["page"] ) ? $_GET["page"] : "" ); $page = strip_tags($page);
$page2 = ( isset( $_GET["page2"] ) ? $_GET["page2"] : "" ); $page2 = strip_tags($page2);
$page3 = ( isset( $_GET["page3"] ) ? $_GET["page3"] : "" ); $page3 = strip_tags($page3);
$page4 = ( isset( $_GET["page4"] ) ? $_GET["page4"] : "" ); $page4 = strip_tags($page4);


/////////////////////////////////////////////////////////////////////////////////////////////////////////// LANGS
$jazyky_arr["sk"] = "Slovenčina";
// $jazyky_arr["en"] = "Angličtina";
// // $jazyky_arr["pl"] = "Poľština";
// $jazyky_arr["hu"] = "Maďarčina";
// $jazyky_arr["cz"] = "Čeština";

define("JAZYKY_ARR", $jazyky_arr);

define("PRIMARY_LANG", "sk");

function SET_LANG( $page_lang ) {
  global $page, $page2, $page3, $page4;

  if ( in_array($page_lang, ["sk", "en", "hu", "pl", "cz"]) ) {
    // include($_SERVER["DOCUMENT_ROOT"] . "langs/{$page_lang}.php");

    define("LANG", $page_lang);
    define("LANG_URL", "/{$page_lang}");

    if ( $page_lang == "en" ) {
      define("LANG_META", "en-EN");
    } elseif ( $page_lang == "hu" ) {
      define("LANG_META", "hu-HU");
    } elseif ( $page_lang == "pl" ) {
      define("LANG_META", "pl-PL");
    } elseif ( $page_lang == "de" ) {
      define("LANG_META", "de-DE");
    } elseif ( $page_lang == "cz" ) {
      define("LANG_META", "cs-CZ");
    } else {
      define("LANG_META", "sk-SK");
    }

    $page = $page2;
    $page2 = $page3;
    $page3 = $page4;

  } else {
    // define("LANG", "sk"); define("LANG_META", "sk-SK"); define("LANG_URL", "");
  
    // include($_SERVER["DOCUMENT_ROOT"] . "langs/sk.php");
  }
}

function l(string $key, ?string $lang = null): string {
  static $dict = [];

  $lang = $lang ?: LANG;

  if (!isset($dict['sk'])) {
    $pathSk = BASE_ROOT . "/langs/sk.php";
    $dict['sk'] = is_file($pathSk) ? require $pathSk : [];
  }

  if (!isset($dict[$lang])) {
    $path = BASE_ROOT . "/langs/{$lang}.php";
    $dict[$lang] = is_file($path) ? require $path : [];
  }

  return $dict[$lang][$key] ?? $dict['sk'][$key] ?? "[$key]";
}

class LangProxy implements ArrayAccess {
  public function offsetExists(mixed $offset): bool {
    return true;
  }
  
  public function offsetGet(mixed $offset): mixed {
    return l((string)$offset);
  }
  
  public function offsetSet(mixed $offset, mixed $value): void {
    // readonly
  }
  
  public function offsetUnset(mixed $offset): void {
    // readonly
  }
}
$L = new LangProxy();

// define("LANG", "en"); define("LANG_META", "en-EN"); define("LANG_URL", "/en");

define("LANG", "sk"); define("LANG_META", "sk-SK"); define("LANG_URL", "");

function page_for_ajax() {
  global $page;
  if ( !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ) {
    $path  = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?? '';
    return strtok(trim($path, '/'), '/') ?: '';
  } else {
    return $page;
  }
}

SET_LANG( page_for_ajax() );

////////////////////////////////////////////////////////////////////////////// toto je pre staré

$GLOBALS["allurlPAGE"] = WEB_URL;

