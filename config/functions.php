<?php
function lang_standard_item( $ITEM_LANG, $content, $w ) {
  $content = trim($content);
  if (empty($content) AND LANG != PRIMARY_LANG) {
    return $ITEM_LANG["$w"];
  } else {
    return $content;
  }
}


function price_minus_dph( $price ) {
  return round($price / (1 + (DPH / 100)), 2);
}

function location_home() {
	header('Location: /', true, 301);
	exit;
}

function e($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


function generate_PAGE_BREADCRUMBS($link, $name) {
  global $content;

  $content["PAGE_BREADCRUMBS"][] = [
    "link" => "/$link",
    "name" => strip_tags($name)
  ];
}


function print_PAGE_BREADCRUMBS( $breadcrumbs_arr ) {
  if ( !empty($breadcrumbs_arr)) {
    echo "<div class='breadcrumbs'><div class='scrollable scrollbar_hide' data-simplebar data-simplebar-auto-hide='false'><ul>";
    echo "<li><a href='".LANG_URL."/'>Domov</a></li>";
    foreach ($breadcrumbs_arr as $item) {
      if ( isset($item["pomocna"]) AND $item["pomocna"] == 1 ) {
        echo "<li>{$item["name"]}</li>";
      } else {
        echo "<li><a href='".LANG_URL."{$item["link"]}'>{$item["name"]}</a></li>";
      }
    }
    echo "</ul></div></div><!-- breadcrumbs -->";
  }
}

///////////////////////////////////////////////////////////////

function parseString( $input, $separator ) {
  $parsed = [];

  // Rozdelíme reťazec podľa znaku &
  $pairs = explode($separator, $input);
  
  foreach ($pairs as $pair) {
      // Rozdelíme kľúč a hodnotu podľa znaku =
      list($key, $value) = explode('=', $pair, 2);
      $parsed[$key] = $value;
  }
  
  return $parsed;
}


function price_format( $cena, $decimal=2, $mena="€", $smenou=true ) {
  $cena = number_format($cena, $decimal, ",", " ");
  $cena = str_replace(" ", "&nbsp;", $cena);
  if ($smenou) $cena = $cena."&nbsp;".$mena;
  return $cena;
}


function price_czk_conversion_format( $cena ) {
  $cena_czk = $cena * CURR_CZK;
  $cena_czk = number_format($cena_czk, 0, ",", " ");
  $cena_czk = str_replace(" ", "&nbsp;", $cena_czk);
  $cena_czk = $cena_czk."&nbsp;"."CZK";
  return $cena_czk;
}


///////////////////////////////////////////////////////////////


function form_sessions_HSC( $hsc ) {
  echo "<input type='hidden' name='hsc' value='{$hsc}' />";
}


///////////////////////////////////////////////////////////////


function string_to_number( $string ) {
  $string = str_replace(",", ".", $string);
  $string = str_replace(" ", "", $string);
  return $string;
}


///////////////////////////////////////////////////////////////


function location_to_page( $page ) {
  header("Location: ".LANG_URL."$page");
  exit;
}


///////////////////////////////////////////////////////////////


class Css_Js_Meta {
  private $files_arr = [];
  private $files_result_arr = [];

  public function __construct( $files_arr = [] ) {
    $this->files_arr = $files_arr;
  }

  public function merge() {
    foreach ( $this->files_arr as $file ) {
      $ext = explode(".", $file);
      $ext = strtolower(end($ext));
      if ( $ext == "css" ) {
        $this->files_result_arr[] = '<link href="/'.$file.'?v'.filemtime(BASE_ROOT . "$file").'" type="text/css" rel="stylesheet" />';
      } elseif ( $ext == "js" ) {
        $this->files_result_arr[] = '<script src="/'.$file.'?v'.filemtime(BASE_ROOT . "$file").'" type="text/javascript"></script>';
      }
    }
    return $this->files_result_arr["0"] ? implode( " ", $this->files_result_arr ) . "\n" : "";
  }
}


///////////////////////////////////////////////////////////////


function meta_for_web( $title, $h1, $description, $fb_image ) {

  $data_metas = [
    "title" => plain( $title ),
    "h1" => plain( $h1 ),
    "description" => plain( $description ),
    "og_image" => $fb_image
  ];

  return $data_metas;
}


///////////////////////////////////////////////////////////////


function string_sanitaze( $value ) {
  return filter_var($value ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
}


function string_sanitaze_html( $value ) {
  return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


///////////////////////////////////////////////////////////////


function get_params($w="") {
  global $PARAM, $PARAMQ;

  $PARAM = [];
  $PARAMQ = [];

  $url_ = is_ajax() ? $_SERVER["HTTP_REFERER"] : $_SERVER["REQUEST_URI"];

  $ru = filter_var( $url_, FILTER_SANITIZE_URL );
  $param_path = explode( "/", trim( parse_url( $ru, PHP_URL_PATH ), '/' ) );
  $param_query = @explode( "&", trim( parse_url( $ru, PHP_URL_QUERY ) ) );

  $n = LANG_URL ? -1 : 0;
  if ( !empty( array_filter ( $param_path ) ) )
  foreach ($param_path as $item) { 
    $n++;
    $PARAM["p".$n] = $item;
  }

  $param_query_ = parse_url( $url_ );
  if ( isset($param_query_['query']) ) {
    parse_str($param_query_['query'], $param_query);

    if ( !empty( array_filter ( $param_query ) ) ) {
      foreach ($param_query as $key => $item) {
        $PARAMQ["$key"] = $item;
      }
    }
  }

  if ( $w == "PARAM" ) return $PARAM;
  if ( $w == "PARAMQ" ) return $PARAMQ;
}


///////////////////////////////////////////////////////////////


function plain( $string ) {

  return htmlspecialchars( $string, ENT_QUOTES );

  return $string;
}


///////////////////////////////////////////////////////////////


function word_limiter($str, $limit, $remove_last_word = true, $end_char = '&#8230;') {

  if (trim($str) === '') return $str;

  if (mb_strlen($str, 'UTF-8') + 3 > $limit) {
    $str_ = mb_substr($str, 0, $limit, "utf-8");

    if ($remove_last_word == true) {
      $words = explode(" ", $str_);
      array_splice($words, -1);
      $str_ = implode(" ", $words);
    }

    return $str_.$end_char;
  } else {
    return $str;
  }
}


///////////////////////////////////////////////////////////////


function is_ajax() {
	return ( !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' );
}


///////////////////////////////////////////////////////////////


// bool
function isDate($date, $format = 'Y-m-d') {
  $d = DateTime::createFromFormat($format, $date);
  return $d && $d->format($format) === $date;
}


///////////////////////////////////////////////////////////////


function fn_isset( $value, $w="string" ) {
  if ( $w === "array" ) {
    return isset($value) ? $value : [];
  } else {
    return isset($value) ? $value : "";
  }
}


///////////////////////////////////////////////////////////////


function getBetweenDates($startDate, $endDate) {
  $rangArray = [];
  $startDate = strtotime($startDate);
  $endDate = strtotime($endDate);
  for ($currentDate = $startDate; $currentDate <= $endDate; $currentDate += (86400)) {
    $date = date('Y-m-d', $currentDate);
    $rangArray[] = $date;
  }
  return $rangArray;
}


///////////////////////////////////////////////////////////////


function getRandomString($n) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < $n; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }

    return $randomString;
}


///////////////////////////////////////////////////////////////


function months_word( $month ) {
  $month = ( substr($month,0,1)==0 ? substr($month,-1) : $month );

  $months_sk = [ 1 => "január", 2 => "február", 3 => "marec", 4 => "apríl", 5 => "máj", 6 => "jún", 7 => "júl", 8 => "august", 9 => "september", 10 => "október", 11 => "november", 12 => "december" ];

  return ( isset($months_sk["$month"]) ? $months_sk["$month"] : "error" );
}


///////////////////////////////////////////////////////////////


function slugify($text){
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = preg_replace('~[^-\w]+~', '', $text);
  $text = trim($text, '-');
  $text = preg_replace('~-+~', '-', $text);
  $text = strtolower($text);
  if (empty($text)) {
    return 'n-a';
  }
  return $text;
}


///////////////////////////////////////////////////////////////


function string_between_two_string($str, $starting_word, $ending_word) {
  $subtring_start = strpos($str, $starting_word);
  $subtring_start += strlen($starting_word);
  $size = strpos($str, $ending_word, $subtring_start) - $subtring_start;
  return substr($str, $subtring_start, $size);
}


///////////////////////////////////////////////////////////////


////////////// FASTER IMAGE SIZES
class FastImage
{
	private $strpos = 0;
	private $str;
	private $type;
	private $handle;

	public function __construct($uri = null)
	{
		if ($uri) $this->load($uri);
	}
	public function load($uri)
	{
		if ($this->handle) $this->close();

		$this->handle = fopen($uri, 'r');
	}
	public function close()
	{
		if ($this->handle)
		{
			fclose($this->handle);
			$this->handle = null;
			$this->type = null;
			$this->str = null;
		}
	}
	public function getSize()
	{
		$this->strpos = 0;
		if ($this->getType())
		{
			return array_values($this->parseSize());
		}

		return false;
	}
	public function getType()
	{
		$this->strpos = 0;

		if (!$this->type)
		{
			switch ($this->getChars(2))
			{
				case "BM":
					return $this->type = 'bmp';
				case "GI":
					return $this->type = 'gif';
				case chr(0xFF).chr(0xd8):
					return $this->type = 'jpeg';
				case chr(0x89).'P':
					return $this->type = 'png';
				default:
					return false;
			}
		}
		return $this->type;
	}
	private function parseSize()
	{
		$this->strpos = 0;

		switch ($this->type)
		{
			case 'png':
				return $this->parseSizeForPNG();
			case 'gif':
				return $this->parseSizeForGIF();
			case 'bmp':
				return $this->parseSizeForBMP();
			case 'jpeg':
				return $this->parseSizeForJPEG();
		}

		return null;
	}
	private function parseSizeForPNG()
	{
		$chars = $this->getChars(25);
		return unpack("N*", substr($chars, 16, 8));
	}
	private function parseSizeForGIF()
	{
		$chars = $this->getChars(11);
		return unpack("S*", substr($chars, 6, 4));
	}
	private function parseSizeForBMP()
	{
		$chars = $this->getChars(29);
	 	$chars = substr($chars, 14, 14);
		$type = unpack('C', $chars);

		return (reset($type) == 40) ? unpack('L*', substr($chars, 4)) : unpack('L*', substr($chars, 4, 8));
	}
	private function parseSizeForJPEG()
	{
		$state = null;
		$i = 0;
		while (true)
		{
			switch ($state)
			{
				default:
					$this->getChars(2);
					$state = 'started';
					break;

				case 'started':
					$b = $this->getByte();
					if ($b === false) return false;

					$state = $b == 0xFF ? 'sof' : 'started';
					break;

				case 'sof':
					$b = $this->getByte();
					if (in_array($b, range(0xe0, 0xef)))
					{
						$state = 'skipframe';
					}
					elseif (in_array($b, array_merge(range(0xC0,0xC3), range(0xC5,0xC7), range(0xC9,0xCB), range(0xCD,0xCF))))
					{
						$state = 'readsize';
					}
					elseif ($b == 0xFF)
					{
						$state = 'sof';
					}
					else
					{
						$state = 'skipframe';
					}
					break;

				case 'skipframe':
					$skip = $this->readInt($this->getChars(2)) - 2;
					$state = 'doskip';
					break;

				case 'doskip':
					$this->getChars($skip);
					$state = 'started';
					break;

				case 'readsize':
					$c = $this->getChars(7);

					return array($this->readInt(substr($c, 5, 2)), $this->readInt(substr($c, 3, 2)));
			}
		}
	}
	private function getChars($n)
	{
		$response = null;

		// do we need more data?
		ini_set('display_errors', 0);
		ini_set('display_startup_errors', 0);

		if ($this->strpos + $n -1 >= strlen($this->str))
		{
			$end = ($this->strpos + $n);
			while (strlen($this->str) < $end && $response !== false)
			{
				// read more from the file handle
				$need = $end - ftell($this->handle);
				if ($response = fread($this->handle, $need))
				{
					$this->str .= $response;
				}
				else
				{
					return false;
				}
			}
		}

		$result = substr($this->str, $this->strpos, $n);
		$this->strpos += $n;

		return $result;
	}
	private function getByte()
	{
		$c = $this->getChars(1);
		$b = unpack("C", $c);

		return reset($b);
	}
	private function readInt($str)
	{
		$size = unpack("C*", $str);

		return ($size[1] << 8) + $size[2];
	}
	public function __destruct()
	{
		$this->close();
	}
}
 //$image = new FastImage($obrazok);
 //list($width, $height) = $image->getSize();
////////////// FASTER IMAGE SIZES


///////////////////////////////////////////////////////////////













///////////////////////////////////////////////////////////////


function delete_folders_files($dir) {
  foreach(glob($dir . '/*') as $file) {
      if (is_dir($file))
          delete_folders_files($file);
      else
          unlink($file);
  }
  rmdir($dir);
}


///////////////////////////////////////////////////////////////


function create_folder( $w, $folderpath, $name, $parent_id, $fix, $fortable ) {
  global $db, $admins_data;

  if ( $parent_id === 0 ) {
    $FOLDER = DATAS_ROOT;
  } else {
    $query = $db->prepare( "SELECT * FROM filemanager WHERE id=:id" );
    $query->execute( [ "id" => $parent_id ] );
    $FOLDERDATA = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];
    $FOLDER = DATAS_ROOT . $FOLDERDATA["folder"] . $FOLDERDATA["file"] . "/";
  }

  $foldernamenew = trim($name);
  $foldernamenew = htmlspecialchars( $foldernamenew );

  if ( $w === "automatic" ) {
    $foldernamenew_link = $folderpath;
  } else {
    $foldernamenew_link = slugify($foldernamenew);
  }

  if ( is_dir($FOLDER . $foldernamenew_link) ) {
    $foldernamenew = $foldernamenew . "(" . time() . ")";
    $foldernamenew_link = $foldernamenew_link . "(" . time() . ")";
  }

  if ( mkdir($FOLDER . $foldernamenew_link, 0755) ) {

    $folderhash = md5($foldernamenew . time());

    $FOLDER_SAVE = str_replace( DATAS_ROOT, "", $FOLDER );
    if ( substr($FOLDER_SAVE, 0,1) == "/" ) $FOLDER_SAVE = substr($FOLDER_SAVE, 1);

    $query = $db->prepare( "INSERT INTO filemanager SET parent_id=:parent_id, type=:type, fortable=:fortable, filehash=:filehash, file=:file, filename=:filename, filetype=:filetype, extension=:extension, folder=:folder, size=:size, dimension_x=:dimension_x, dimension_y=:dimension_y, fix=:fix, user_id=:user_id" );
    $query->execute([ 
        "parent_id" => "$parent_id",
        "type" => "folder",
        "fortable" => $fortable,
        "filehash" => "$folderhash",
        "file" => "$foldernamenew_link",
        "filename" => "$foldernamenew",
        "filetype" => "",
        "extension" => "",
        "folder" => $FOLDER_SAVE,
        "size" => "",
        "dimension_x" => "",
        "dimension_y" => "",
        "fix" => $fix,
        "user_id" => $admins_data["id"]
    ]);

    return $folderhash;
  }




  // $folderfordb = "";
  // if ( !empty($parentfolder) ) {
  //   $query = $db->prepare( "SELECT * FROM filemanager WHERE id=:id" );
  //   $query->execute([ "id" => $parentfolder ]);
  //   $parentdata = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];
  //   $folderfordb = $parentdata["file"] . "/";
  // }

  // $parentfolder = $parentfolder ?? 0;

  // $folderlink = basename($folder);
  // $folderhash = md5($name . time());

  // if ( !file_exists($folder) ) {
  //   if ( !mkdir($folder, 0755, true) ) {
  //     echo '<div style="color:#ff0000">Priečinok <strong>['.$folder.']</strong> sa nepodarilo vytvoriť.</div>';
  //   } else {
  //     $query = $db->prepare( "INSERT INTO filemanager SET parent_id=:parent_id, type=:type, filehash=:filehash, file=:file, filename=:filename, folder=:folder, fix='1'" );
  //     $query->execute([ 
  //         "parent_id" => $parentfolder,
  //         "type" => "folder",
  //         "filehash" => "$folderhash",
  //         "file" => "$folderlink",
  //         "filename" => "$name",
  //         "folder" => "$folderfordb"
  //     ]);
  //   }
  // }
}


///////////////////////////////////////////////////////////////


function file_filemanager( $filehash, $sett_arr ) {
  global $db;
  
  $query = $db->prepare( "SELECT * FROM filemanager_data WHERE lang=:lang AND filehash=:filehash" );
  $query->execute([ "lang" => LANG, "filehash" => $filehash ]);
  $file_data = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];   

  if ( !isset($file_data["item_id"]) ) return false;

  $query = $db->prepare( "SELECT * FROM filemanager WHERE id=:id" );
  $query->execute([ "id" => $file_data["item_id"] ]);
  $file_result = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];   

  return [ 
    "path" => DATAS_ROOT . $file_result["folder"] . $file_result["file"],
    "pathshort" => "/" . DATAS_ROOT_SHORT . $file_result["folder"] . $file_result["file"],
    "alttext" => $file_data["alttext"],
    "description" => $file_data["description"]
  ];
}


function files_filemanager( $filehashs ) {
  global $db;

  $item_ids = explode(",", $filehashs);
  $placeholders = rtrim(str_repeat('?,', count($item_ids)), ',');

  $query = $db->prepare( "SELECT alttext, description, filehash FROM filemanager_data WHERE lang = ? AND filehash IN ($placeholders) ORDER BY FIELD(filehash, $placeholders)" );
  $query->execute( array_merge( [LANG], $item_ids, $item_ids ) );
  $files_data = $query->rowCount() ? $query->fetchAll( PDO::FETCH_ASSOC ) : []; 
  $files_data = array_column($files_data, null, 'filehash');

  // printf("<pre>%s</pre>", print_r( $files_data , true));

  $query = $db->prepare( "SELECT file, filename, filetype, extension, folder, size, dimension_x, dimension_y, filehash FROM filemanager WHERE filehash IN ($placeholders)" );
  $query->execute( $item_ids );
  $files_result = $query->rowCount() ? $query->fetchAll( PDO::FETCH_ASSOC ) : []; 
  $files_result = array_column($files_result, null, 'filehash');

  // printf("<pre>%s</pre>", print_r( $files_result , true));
  
  $mergedArray = [];
  $p= 0;
  foreach ($files_data as $key => $value) {
      if (isset($files_result[$key])) {
          $mergedArray[$p] = array_merge($value, $files_result[$key]);
          $mergedArray[$p]['path'] = DATAS_ROOT . $mergedArray[$p]['folder'] . $mergedArray[$p]['file'];
          $mergedArray[$p]['pathshort'] = "/" . DATAS_ROOT_SHORT . $mergedArray[$p]['folder'] . $mergedArray[$p]['file'];
          $p++;
      }
  }
  
  // printf("<pre>%s</pre>", print_r( $mergedArray , true));

  return $mergedArray;
}


///////////////////////////////////////////////////////////////


function items_select_list_by_id( $tb, $data, $s_data ) {
  global $db;

  if ( !empty($s_data["select_data"]) ) {
    $select_data = $s_data["select_data"];
  } else {
    $select_data = "data.*";
  }

  if ( !empty($s_data["select_prdata"]) ) {
    $select_prdata = $s_data["select_prdata"];
  } else {
    $select_prdata = "data.*";
  }

  $item_ids = explode(",", $data);
  $placeholders = rtrim(str_repeat('?,', count($item_ids)), ',');

  $query = $db->prepare( "SELECT $select_data, $select_prdata FROM $tb AS prdata LEFT JOIN {$tb}_data AS data ON prdata.id = data.item_id WHERE 
  data.lang=? AND prdata.id IN ($placeholders) ORDER BY FIELD(prdata.id, $placeholders)" );
  $query->execute( array_merge( [PRIMARY_LANG], $item_ids, $item_ids ) );
  return $query->rowCount() ? $query->fetchAll( PDO::FETCH_ASSOC ) : []; 
}


///////////////////////////////////////////////////////////////

class ResultFunction {
  public $result;
  public $message;

  public function __construct( $result, $message ) {
    $this->result = $result;
    $this->message = $message;
  }
}

///////////////////////////////////////////////////////////////


function result_content_1( $arr ) {
  if ( count($arr) > 0 ) {
    echo "
    <div class='result_content_1 error mb-30'>
      <div class='hdr'>error</div>
    ";
    foreach ($arr as $key => $value) {
      echo "<span>$value</span>";
    }
    echo "
    </div><!-- result_content_1 -->
    ";
  }
}

function result_content_1_js( $arr, $form_control_data ) {
  if ( isset( $arr ) ) {
    echo "<script>
    $(document).ready(function() {
    ";
    foreach (array_keys($arr) as $key) {
      echo "$.fn.control_data( '$key', '$form_control_data' );";
    }
    echo "
    });
    </script>";
  }
}


///////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////


