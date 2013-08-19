<?php
	/*
		UserPie Version: 1.0
		http://userpie.com
	*/

	require_once('../api.php');
	require_once("db/mysql.php");
	
	//Construct a db instance
	$db = new $sql_db();
	if(is_array($db->sql_connect(
							MYSQL_URL, 
							MYSQL_USERNAME,
							MYSQL_PASSWORD,
							MYSQL_DBNAME, 
							MYSQL_PORT,
							false, 
							false
	))) {
		die("Unable to connect to the database");
	}

	require_once("lang/".LANGUAGE.".php");
	require_once("class.user.php");
	require_once("class.mail.php");
	require_once("funcs.user.php");
	require_once("funcs.general.php");
	require_once("class.newuser.php");

	//session_start();
	
//Global User Object Var
//loggedInUser can be used globally if constructed
if(isset($_SESSION["userPieUser"]) && is_object($_SESSION["userPieUser"]))
	$loggedInUser = $_SESSION["userPieUser"];
else if(isset($_COOKIE["userPieUser"])) {
	$db->sql_query("SELECT session_data FROM ".$db_table_prefix."sessions WHERE session_id = '".$_COOKIE['userPieUser']."'");
	$dbRes = $db->sql_fetchrowset();
	if(empty($dbRes)) {
		$loggedInUser = NULL;
		setcookie("userPieUser", "", -parseLength($remember_me_length));
	}
	else {
		$obj = $dbRes[0];
		$loggedInUser = unserialize($obj["session_data"]);
	}
}
else {
	$db->sql_query("DELETE FROM ".$db_table_prefix."sessions WHERE ".time()." >= (session_start+".parseLength($remember_me_length).")");
	$loggedInUser = NULL;
}

?>