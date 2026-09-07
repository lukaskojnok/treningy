<?php
session_start();
header("Cache-control: no-cache");

include ("config/common.php");

 $query = $GLOBALS["db"]->prepare( "SELECT * FROM admins WHERE password_forgotten_to < NOW() AND password_forgotten_to!='0000-00-00 00:00:00'" );
 $query->execute();
 $RESULT_password_forgotten_to = $query->rowCount() ? $query->fetchALL( PDO::FETCH_ASSOC ) : [];
 foreach ($RESULT_password_forgotten_to as $key => $DATA_password_forgotten_to) {
   $query = $GLOBALS["db"]->prepare( "UPDATE admins SET password_forgotten_hash='', password_forgotten_to='' WHERE id=:id" );
   $query->execute( [ "id" => $DATA_password_forgotten_to["id"] ] );
 }


if (isset($_POST["form"]) AND $_POST["form"] == "1"):
  $login = (string) trim($_POST["login"]);

  $query = $GLOBALS["db"]->prepare( "SELECT * FROM admins WHERE login=:login AND active='1'" );
  $query->execute( ["login" => $login] );
  $admin = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];

  if ($admin["login"]) {
    $hesloP = hash('sha512', $admin["login"].$_POST["heslo"].$has1);
  }

  if ($login == $admin["login"] AND $hesloP == $admin["password"] AND $login AND $_POST["heslo"]):
   setcookie ("loginADMIN", "$login", time() + 60*60*24, "/");

   $unique_code = unique_code_admin_login( $admin["login"], $_SERVER["REMOTE_ADDR"], $_SERVER["HTTP_USER_AGENT"], session_id() );
   setcookie ("loginADMIN_unique_code", "$unique_code", time() + 60*60*24, "/");

   $GLOBALS["db"]->prepare( "UPDATE admins SET date_login_last=NOW() WHERE login=:login" )->execute( ["login" => $login] );

   $GLOBALS["db"]->prepare( "DELETE FROM admins_logs WHERE unique_code=:unique_code" )->execute( ["unique_code" => $unique_code] );

   $query = $GLOBALS["db"]->prepare( "INSERT INTO admins_logs SET login=:login, session_id=:session_id, user_agent=:user_agent, ip=:ip, unique_code=:unique_code, date_login=NOW(), date_last_do=NOW()" );
   $query->execute([
     "login" => $login,
     "session_id" => session_id(),
     "user_agent" => $_SERVER["HTTP_USER_AGENT"],
     "ip" => $_SERVER["REMOTE_ADDR"],
     "unique_code" => $unique_code
   ]);


   if (!$_SESSION["lastpage"]):
    $location = "index.php";
   else:
    $location = "$_SESSION[lastpage]";
   endif;

   header("Location:$location");
   exit;

  else:
   $result_log = '
   <div class="alert alert-danger alert-dismissable">
     <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
     Zadali ste nesprávne prihlasovacie meno <br/> alebo heslo!!!
   </div>';
  endif;

endif;


/////////////////////////////////////////////////////////////////////////////////////////////////


if (isset($_POST["form_gen_1"]) AND $_POST["form_gen_1"] == "1") { //form_gen_1
  $email = (string) $_POST["email"];

  $query = $GLOBALS["db"]->prepare( "SELECT * FROM admins WHERE email=:email" );
  $query->execute( ["email" => $email] );
  $admin = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];

  if ( !isset($admin["email"]) ) {
    $result_log = '
    <div class="alert alert-danger alert-dismissable">
      <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
      Daný e-mail nie je v databáze.
    </div>';
  } else {

    $password_forgotten_hash = getRandomString("20");

    $query = $GLOBALS["db"]->prepare( "UPDATE admins SET password_forgotten_hash='$password_forgotten_hash', password_forgotten_to = NOW() + INTERVAL 1 HOUR WHERE email=:email" );
    $query->execute( ["email" => $email] );

    //////////////////////////////////////// E-MAIL
    $text_mail = "
    Po kliknutí na tento link si zadáte nové heslo:<br/>
    ".WEB_URL."/admin/login.php?p=forgot2&h=$password_forgotten_hash&e={$admin["email"]}&i={$admin["id"]}
    ";

    require BASE_ROOT . "classes/MailsMy/MailsMy.c.php";
    $emailMy = new MailsMy();
    $emailMy->EMAIL_DATA = [ "to" => $admin["email"], "subject" => "Správa z " . WEB_URL_NAME, "text" => $text_mail, "attachments" => [] ];
    $emailMy->sendMail();
    //////////////////////////////////////// E-MAIL

    $result_log = '
    <div class="alert alert-success alert-dismissable">
      Bol odoslaný e-mail na vygenerovanie nového hesla.<br/><br/>
      Platnosť linku v ňom je 1 hodina.
    </div>';
    $form_gen_1_result = 1;
  }

} //form_gen_1





if ( isset($_GET["p"]) AND $_GET["p"] == "forgot2" ) { //forgot2
  $h = htmlspecialchars($_GET["h"]);
  $e = htmlspecialchars($_GET["e"]);
  $i = htmlspecialchars($_GET["i"]);

  if ( empty($h) OR empty($e) OR empty($i) ) {
    $forgot_2 = "no";
  } else {
    $query = $GLOBALS["db"]->prepare( "SELECT * FROM admins WHERE password_forgotten_hash=:password_forgotten_hash AND email=:email AND id=:id AND password_forgotten_to > NOW()" );
    $query->execute( [ "password_forgotten_hash" => $h, "email" => $e, "id" => $i ] );
    $admin_forgot2 = $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];

    if ( isset($admin_forgot2["id"]) ) {

      if ($_POST["form_gen_2"] == "1") {
        $password_1 = htmlspecialchars($_POST["password_1"]);
        $password_2 = htmlspecialchars($_POST["password_2"]);

        if ( $password_1 != $password_2 ) {
          $result_log = '
          <div class="alert alert-danger alert-dismissable">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            Heslá sa nezhodujú.
          </div>';
        } elseif ( mb_strlen($password_1, "utf8") < 6 ) {
          $result_log = '
          <div class="alert alert-danger alert-dismissable">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            Heslo musí mať minimálne 6 znakov.
          </div>';
        } else {
          $password_save = hash('sha512', $admin_forgot2["login"].$password_1.$has1);

          $query = $GLOBALS["db"]->prepare( "UPDATE admins SET password_forgotten_hash='', password_forgotten_to='', password=:password WHERE id=:id" );
          $query->execute( [ "password" => $password_save, "id" => $admin_forgot2["id"] ] );

          header("Location: ?pok=1");
          exit;
        }
      } //form_gen_2==1

    } else {
      $forgot_2 = "no";
    }
  }

  if ( $forgot_2 == "no" ) {
    $result_log = '
    <div class="alert alert-danger alert-dismissable">
      Generovanie hesla vypršalo.<br/><br/>
      <a href="?" style="font-weight:bold">Späť na prihlasovanie</a>
    </div>';
  }
} //forgot2

if ( isset($_GET["pok"]) AND $_GET["pok"] == 1 ) {
  $result_log = '
  <div class="alert alert-success alert-dismissable">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
    Heslo bolo úspešne zmenené.
  </div>';
}
?>

<!DOCTYPE html>
<html lang="sk">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
		<title>LOGIN ADMIN - <?php echo $GLOBALS["pageURL2"]; ?></title>
		<meta name="description" content="" />

		<!-- vector map CSS -->
		<!-- jQuery -->
	</head>
  <body>
		<!--Preloader-->
		<div class="preloader-it">
			<div class="la-anim-1"></div>
		</div>
		<!--/Preloader-->

		<div class="wrapper pa-0">
			<header class="sp-header">
				<div class="pull-left" style="display:flex;align-items:center;align-content:center;padding-top:0;padding-left:0;margin:15px 0 0 15px">
					<img class="mr-5" src="img/logo_favicon_fisax.png?v2" alt="brand" width="26" height="26" />
					<span style="line-height:100%;font-size:20px;color:black;font-weight:500">FISAX</span>
				</div>

				<div class="clearfix"></div>
			</header>


			<!-- Main Content -->
			<div class="page-wrapper pa-0 ma-0 auth-page">
				<div class="container-fluid">
					<!-- Row -->
 					<div class="table-struct full-width full-height">
						<div class="table-cell vertical-align-middle auth-form-wrap">
							<div class="auth-form  ml-auto mr-auto no-float">
								<div class="row">
									<div class="col-sm-12 col-xs-12">

<?php
 if (isset($result_log) AND $result_log) :
   echo $result_log;
 endif;
?>



<?php
if ( isset($_GET["p"]) AND $_GET["p"] == "forgot" ) { //forgot
  if ( !isset($form_gen_1_result) ) {
?>
  <div class="mb-30">
    <h3 class="text-center txt-dark mb-10">Zabudnuté heslo</h3>
    <h6 class="text-center nonecase-font txt-grey">admin | <?php echo $GLOBALS["pageURL2"]; ?></h6>
  </div>
  <div class="form-wrap">
    <form action="?p=forgot" method="post">
      <div class="form-group">
        <label class="control-label mb-10" for="exampleInputEmail_2">Zadať e-mail</label>
        <input type="text" name="email" class="form-control" required="" id="exampleInputEmail_2" placeholder="">
      </div>

      <div class="form-group">
        <a class="txt-primary block mb-10 pull-right font-12" href="?">prihlásiť sa, späť</a>
        <div class="clearfix"></div>
      </div>

      <div class="form-group text-center">
        <button type="submit" class="btn btn-primary">Nové heslo</button>
      </div>

      <input type="hidden" name="form_gen_1" value="1">
    </form>
  </div>

<?php
  }

} elseif ( isset($_GET["p"]) AND $_GET["p"] == "forgot2" ) { //forgot
  if ($forgot_2 != "no") {
?>

<div class="mb-30">
  <h3 class="text-center txt-dark mb-10">Zadať nové heslo</h3>
  <h6 class="text-center nonecase-font txt-grey">admin | <?php echo $GLOBALS["pageURL2"]; ?></h6>
</div>
<div class="form-wrap">
  <form action="?p=forgot2&h=<?php echo $_GET["h"]; ?>&e=<?php echo $_GET["e"]; ?>&i=<?php echo $_GET["i"]; ?>" method="post">
    <div class="form-group">
      <label class="control-label mb-10" for="exampleInputEmail_2">Nové heslo</label>
      <input type="password" name="password_1" class="form-control" required="" id="exampleInputEmail_2" placeholder="">
    </div>
    <div class="form-group">
      <label class="control-label mb-10" for="exampleInputEmail_2">Overiť heslo</label>
      <input type="password" name="password_2" class="form-control" required="" id="exampleInputEmail_2" placeholder="">
    </div>

    <div class="form-group">
      <a class="txt-primary block mb-10 pull-right font-12" href="?">prihlásiť sa</a>
      <div class="clearfix"></div>
    </div>

    <div class="form-group text-center">
      <button type="submit" class="btn btn-primary">Zadať nové heslo</button>
    </div>

    <input type="hidden" name="form_gen_2" value="1">
  </form>
</div>

<?php
  }
} else { //forgot
?>

<div class="mb-30">
  <h3 class="text-center txt-dark mb-10">Prihlásiť sa</h3>
  <h6 class="text-center nonecase-font txt-grey">admin | <?php echo $GLOBALS["pageURL2"]; ?></h6>
</div>
<div class="form-wrap">
  <form action="?" method="post">
    <div class="form-group">
      <label class="control-label mb-10" for="exampleInputEmail_2">Login</label>
      <input type="text" name="login" class="form-control" required="" id="exampleInputEmail_2" placeholder="">
    </div>
    <div class="form-group">
      <label class="pull-left control-label mb-10" for="exampleInputpwd_2">Heslo</label>
      <div class="clearfix"></div>
      <input type="password" name="heslo" class="form-control" required="" id="exampleInputpwd_2" placeholder="">
      <a class="txt-primary block mb-10 pull-right font-12 mt-10" href="?p=forgot">zabudnuté heslo</a>
      <div class="clearfix"></div>
    </div>

    <div class="form-group text-center">
      <button type="submit" class="btn btn-primary">Prihlásiť sa</button>
    </div>
                            <input type="hidden" name="form" value="1">
  </form>
</div>
<?php
} //forgot
?>



									</div>
								</div>
							</div>
						</div>
					</div>
					<!-- /Row -->
				</div>

			</div>
			<!-- /Main Content -->

		</div>
		<!-- /#wrapper -->


	</body>
</html>
