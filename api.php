<?php
/*
 * ======================================
 * Configurations
 * ======================================
 */
//Charset
header("Content-Type: text/html; charset=UTF-8", true); 

//Test Server?
define('TEST_SERVER',false);

//Local Database
if(TEST_SERVER):
	define('MYSQL_URL', '127.0.0.1');
	define('MYSQL_USERNAME', 'root');
	define('MYSQL_PASSWORD', '');
	define('MYSQL_DBNAME', 'meutroco');

//Web Database
else:
	define('MYSQL_URL', 'mysql.meutroco.com.br');
	define('MYSQL_USERNAME', 'letsrider');
	define('MYSQL_PASSWORD', '132435');
	define('MYSQL_DBNAME', 'meutroco');
endif;

//Locale
setlocale(LC_MONETARY, 'pt_BR');
date_default_timezone_set('America/Sao_Paulo');

/*
 * ======================================
 * Required Files
 * ======================================
 */
require_once('api.utils.php');
require_once('transactions/class.php');
require_once('users/class.php');
require_once('tags/class.php');
require_once('accounts/class.php');
require_once('token/class.php');

/*
 * ======================================
 * Rest Request
 * ======================================
 */
class RestRequest {
	private $request_vars;
	private $data;
	private $http_accept;
	private $method;

	/* Construct */
	public function __construct() {
		$this->request_vars		= array();
		$this->data				= '';
		$this->http_accept		= 'json'/*(strpos($_SERVER['HTTP_ACCEPT'], 'json')) ? 'json' : 'xml'*/;
		$this->method			= 'get';
	}

	/* Set Data */
	public function setData($data) {
		$this->data = $data;
	}
	
	/* Get Data */
	public function getData() {
		return $this->data;
	}

	/* Set Method */
	public function setMethod($method) {
		$this->method = $method;
	}
	
	/* Get Method */
	public function getMethod() {
		return $this->method;
	}	

	/* Set Request Variables */
	public function setRequestVars($request_vars) {
		$this->request_vars = $request_vars;
	}
	
	/* Get Request Variables */
	public function getRequestVars() {
		return $this->request_vars;
	}
	
	/* Get HTTP Accept */
	public function getHttpAccept() {
		return $this->http_accept;
	}
}

/*
 * ======================================
 * Rest Utils
 * ======================================
 */
class RestUtils {
	/* */
	public static function processRequest() {
		//Get our request method and data storage var
		$request_method = strtolower($_SERVER['REQUEST_METHOD']);
		$return_obj     = new RestRequest();
		$data           = array();
		
		//Verify request method
		switch ($request_method) {
			//GET
			case 'get':
				$data = $_GET;
			break;
			
			//POST
			case 'post':
				$data = $_POST;
			break;
			
			//PUT
			case 'put':
				parse_str(file_get_contents('php://input'), $put_vars);
				$data = $put_vars;
			break;
			
			//DELETE
			case 'delete':
				$data = $_GET;
			break;
			
			//OTHERS
			default:
				$data = $_GET;
			break;
			
		}
		
		//Store the method
		if(isset($_SERVER['HTTP_METHOD']))
			$request_method = strtolower($_SERVER['HTTP_METHOD']);
		elseif(isset($data['method']))
			$request_method = strtolower($data['method']);
		$return_obj->setMethod($request_method);
		
		//Store the data
		$return_obj->setRequestVars($data);
		
		//Store the JSON object
		if(isset($data['data'])) {
			$return_obj->setData(json_decode($data['data'])); 
		} else {
			$return_obj->setData($data); 
		}
		
		//Return 
		return $return_obj;
		
	}
	
	/* Send Response */
	public static function sendResponse($status = 200, $body = '', $content_type = 'text/html') {
		//Set status and content type
		$status_header = 'HTTP/1.1 ' . $status . ' ' . RestUtils::getStatusCodeMessage($status);
		header($status_header);
		header('Content-type: ' . $content_type);
		
		//Body
		if($body != '') {
			if(is_array($body))
				echo json_encode($body);
			else
				echo $body;
			exit();
		} else {
			//Server Signature
			$signature = ($_SERVER['SERVER_SIGNATURE'] == '') ? $_SERVER['SERVER_SOFTWARE'] . ' Server at ' . $_SERVER['SERVER_NAME'] . ' Port ' . $_SERVER['SERVER_PORT'] : $_SERVER['SERVER_SIGNATURE'];
			
			//Body
			$body = '{"message": "'.RestUtils::getStatusCodeMessage($status).'", "code":"'.$status.'", "signature":"'.$signature.'"}';
			/*
			<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">  
			<html>  
			<head>  
			<meta http-equiv="Content-Type" content="text/html; charset=iso-UTF-8">  
			<title>' . $status . ' ' . RestUtils::getStatusCodeMessage($status) . '</title>  
			</head>  
			<body>  
			<h1>' . RestUtils::getStatusCodeMessage($status) . '</h1>  
			<p>' . $message . '</p>  
			<hr />  
			<address>' . $signature . '</address>  
			</body>  
			</html>';  */
			
			echo $body;  
			exit; 
		}
	}

	/* Get Status Code Message */
	public static function getStatusCodeMessage($status) {
		$codes = parse_ini_file("statusCode.ini");
		return (isset($codes[$status])) ? $codes[$status] : '';
	}
}


/*
 * ======================================
 * MySQL Connection
 * ======================================
 */
class DataBase {	
	//Variables
	private $conn = NULL;
	public $result = NULL;
	public $insertId = NULL;
	
	//Connect
	public function connect() {
		$this->conn = mysql_connect(MYSQL_URL,MYSQL_USERNAME,MYSQL_PASSWORD) or die(RestUtils::sendResponse('500',array('data' => 'db_connect', 'message' => 'Ops! Erro ao conectar ao BD.', 'error' => mysql_error())));
		return mysql_select_db(MYSQL_DBNAME, $this->conn);
	}
	
	//Protect Query
	private function protect($q){
		return $q;
	}
	
	//Close
	public function close() {
		mysql_close($this->conn);
	}
	
	//Get Query
	public function query($query){
		$this->connect();
		$sql = $query;
		$query=mysql_query($this->protect($sql))or die(RestUtils::sendResponse('500',array('data' => 'db_query', 'message' => 'Ops! Erro ao consultar BD.', 'error' => mysql_error())));
		$this->result = $query;
		$this->insertId = mysql_insert_id();
		return $query;
	}
}

/*
 * ======================================
 * User Profile
 * ======================================
 */
class CurrentUser {
	public static function getId() {
		//Get Token
		if(isset($_GET['token'])):
			//Verify token
			$token = new Token;
			if(!$token->verify($_GET['token'])):
			
				//Connect
				$sql = new DataBase;
				$sql->connect();
				$sql->query("
					SELECT DISTINCT *
					FROM token
					WHERE token = '".$_GET['token']."'
				");
				
				//Data
				while($data = mysql_fetch_array($sql->result)):
					return $data['profile_id'];
					break;
				endwhile;
				
			else:
				RestUtils::sendResponse('400',array('data' => 'token', 'message' => 'A verificação do token falhou.'));
				exit;
			endif;
		else:
			RestUtils::sendResponse('412',array('data' => 'token', 'message' => 'O token não foi passado pela URL.'));
			exit;
		endif;
	}
}

?>