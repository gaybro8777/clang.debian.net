<?php
date_default_timezone_set("Europe/Paris");
$pathClangToLog="logs/2012-01-12/";
$pathGCCToLog="rebuild.lsid64-amd64.2011-09-11";
$suffix="lsid64b";
$ext="buildlog";

$currentVersion="5.0";

$clangVersions=Array(
	"2.9" => 16398,
	"3.0" => 15658,
	"3.1" => 17710,
	"3.2" => 18264,
	"3.3" => 18854,
	"3.4" => 21204,
	"3.4.2" => 21383,
	"3.5.0" => 22202,
	"3.6.0" => 22319,
    "3.8.1" => 24585,
    "3.9.1" => 27828,
    "4.0.1" => 27828,
    "5.0" => 28203
);

$locally_configured = false;
if (file_exists("local-config.inc.php")) {
	include("local-config.inc.php");
}

if (!$locally_configured) {
switch ($_SERVER['HTTP_HOST']){
 case 'clang.debian.net':
 case 'manchot.ecranbleu.org':
        include("clang.debian.net.php");
	define("BASEPATH","/www/clang.debian.net/");
        $pathSite="/www/clang.debian.net/";
        $pathToBaseDocumentations="{$pathSite}docs/";
        $base="/";
        define("_DEBUG_MAIL_","sylvestre@ledru.info");
        $nom_emetteur="Site Sylvestre";
        $adr_emetteur="sylvestre.ledru@scilab.org";
        $type="MYSQL";
        $ATOMSdbAvailable=true;

        break;

 case 'localhost':
	define("_DB_","clang");
	define("_DB_HOST_","localhost");
	define("_DB_USER_","clang");
	define("_DB_PASS_","aze");
	$pathSite="/var/www/clang/";
	$pathToBaseDocumentations="{$pathSite}docs/";
	$base="/";
	define("_DEBUG_MAIL_","sly@hitomi.ecranbleu.org");
	$nom_emetteur="Site Sylvestre";
	$adr_emetteur="sylvestre.ledru@scilab.org";
	$type="MYSQL";

	break;

	default:
	define("_DB_","Unknow");
	define("_DB_HOST_","Unknow");
	define("_DB_USER_","Unknow");
	define("_DB_PASS_","Unknow");
	print "Le fichier config.inc.php n'est pas configure ";
	print "({$_SERVER['HTTP_HOST']})";
	exit;
}
}

$secureMode = false;
if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == "X.Y.Z.W") {
   $secureMode = true;
}

$host_db = _DB_HOST_;
$user_db = _DB_USER_;
$pass_db = _DB_PASS_;
//$port_db = _DB_PORT_;
$db_db = _DB_;



$use_mysqli = false;
if (defined("PHP_MAJOR_VERSION") && PHP_MAJOR_VERSION >= 7) {
	$use_mysqli = true;

function mysql_connect($host_db,$user_db,$pass_db) {
	$mysqli = new mysqli($host_db,$user_db,$pass_db);
	if ($mysqli->connect_error) {
		die("Connect Error (".$mysqli->connect_errno.") "
			.$mysqli->connect_error);
	}
	return $mysqli;
}

function mysql_error() {
	global $conn_db;
	return $conn_db->error;
}

function mysql_fetch_object($result) {
	return $result->fetch_object();
}

function mysql_query($req) {
	global $conn_db;
	return $conn_db->query($req);
}

function mysql_select_db($db_db,$conn_db) {
	return $conn_db->select_db($db_db);
}
}

switch ($type) {
	case "POSTGRESQL" :
	$chaine_connexion="host=$host_db port=$port_db dbname=$db_db user=$user_db password=$pass_db";
	$conn = pg_pconnect($chaine_connexion);
	echo pg_errormessage($conn);
	break;
	case "MYSQL" :
	$conn_db = @mysql_connect("$host_db","$user_db","$pass_db");
	if( !@mysql_select_db("$db_db",$conn_db) ){
		//		echo ("Erreur dans la selection de la base de donn�es $db_db -> $user_db@$host_db.\n");
		echo mysql_error();
	}
	break;
}

function db_query ($req)
{
	// Fonction "surcouche" e mysql_query () qui ajoute la gestion d'erreur
	global $type;
	global $conn;
	switch ($type) {
		case "POSTGRESQL" :
		if ( $sth=pg_exec($conn,$req) ) {
			return $sth;
		} else {
		  echo pg_errormessage($conn);
		  error_req($req);
		}
		break;
		case "MYSQL" :
		if ( $sth=mysql_query("$req") )
		{
			// la requete s'est bien pass�
			// affichage de la requete si le verbose est activ�
			return $sth;
		}
		else
		{
			// Erreur dans la requete, donc g�n�ration du mail.
		  echo mysql_error();
		  error_req($req);
		}
	}
}

function error_req ($req) {
	global $PHP_SELF;
	global $REQUEST_URI;
	global $REMOTE_ADDR;
	global $type;
	global $HTTP_REFERER;
	global $HTTP_USER_AGENT;
	$to=_DEBUG_MAIL_;
	switch ($type) {
		case "POSTGRESQL" :
		global $conn;
		$sgbd=pg_errormessage($conn);
		break;
		case "MYSQL" :
		$sgbd=mysql_error();
		break;
	}
	$subject="SQL query - Error";
	$body="Query : $req \nPage : $PHP_SELF \nError : ".$sgbd;
	$body.="\nRequested Url : $REQUEST_URI";
	$body.="\nGet :\n".parray($_GET)."\nPost :\n".parray($_POST)."\nSession :\n".parray($_SESSION);
	$body.="\nReferent : $HTTP_REFERER\nUser Agent : $HTTP_USER_AGENT\nAdresse distante : $REMOTE_ADDR ( ".gethostbyaddr($REMOTE_ADDR)." )\n";
	mail($to,$subject,$body);
}

/*
 * Retour une repr�sentation des �l�ments d'un tableau 
 * $array : le tableau � afficher
 * $prep : le s�parateur � utiliser
 * return : le tableau ainsi g�n�r�
 */
if (!function_exists("parray")){
function parray($array , $prep=''){
	$ret="";
        $prep = "$prep|";
		if (is_array($array) || is_object($array)) {
			if (is_object($array)) 
				$array=get_object_vars($array);
					
			while(list($key, $val) = each($array)) {
                $type = gettype($val);
                if(is_array($val)) {
					$line = "-+ $key ($type)\n";
					$line .= parray($val, "$prep ");
                } else {
					if (is_object($val)) { // C'est un object
                        $line = "-+ $key ($type)\n";
						$line .= parray(get_object_vars($val),"$prep ");
					}else {
                        $line = "=> $key = \"$val\" ($type)\n";
					}
                }
                $ret .= $prep.$line;
			}
		}
        return $ret;
}
}


function SendMail($adr_destinataire,$sujet,$corps) {

	// l'�metteur
  global $nom_emetteur;
  global $adr_emetteur;
	$tete = "From: ".$nom_emetteur." <".$adr_emetteur.">\n";
	$tete .= "Reply-To: ".$adr_emetteur."\n";
	// false si erreur d'�mission
	return mail($adr_destinataire,$sujet,$corps,$tete);
}

function makeSummary($text, $nbchar) {
        if (strlen($text)>$nbchar) {
                return substr($text,0,$nbchar)." (...)";
        }else{
                return $text;
        }
}

?>
