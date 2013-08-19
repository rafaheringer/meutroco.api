<?php
//Get classes
require_once('../api.php');
require_once("../pie/config.php");

//Request
$request = RestUtils::processRequest();
$_DATA = $request->getData();


//Verify Request Method
switch($request->getMethod()) {
	/*
	 * ======================================
	 * GET method
	 * ======================================
	 */
	case 'get':
		//Verificação básica
		if(!isset($_DATA['api_key'])):
			RestUtils::sendResponse('412',array('data' => 'api_key', 'message' => 'Por favor defina uma API_KEY.'));
		endif;

		//Conexão
		$sql = new DataBase;
		$sql->connect();
		$sql->query("
			SELECT app.* 
			FROM applications app
			WHERE app.api_key = '".$_DATA['api_key']."'
		");
		
		//Verifica se a API KEY é válida
		if(mysql_num_rows($sql->result) != 1):
		echo $_DATA['api_key'];
			RestUtils::sendResponse('412',array('data' => 'api_key', 'message' => 'A API KEY utilizada não existe.'));
		endif;
	
		while($data = mysql_fetch_array($sql->result)):
			//Verifica a permissão da API KEY
			if($data['permissions_id'] == 0):
				RestUtils::sendResponse('401',array('data' => 'api_key', 'message' => 'Esta API KEY está bloqueada.'));
			endif;
			
			//Buscas as informações do usuário
			$sql2 = new DataBase;
			$sql2->connect();
			$sql2->query("
				SELECT user.id, user.permission_id, user.this_session
				FROM profiles user
				WHERE user.id = '".$_DATA['who']."'
			");
			
			//Verifica se o usuário existe
			if(mysql_num_rows($sql2->result) != 1):
				RestUtils::sendResponse('412',array('data' => 'login', 'message' => 'O usuário selecionado não existe.'));
			endif;

			//Remove o TOKEN utilizado pelo usuário
			$sql3 = new DataBase;
			$sql3->connect();
			$sql3->query("DELETE from token WHERE token = '".$_DATA['token']."'");

			//Retorno
			if($data['return_url'] != '' && !isset($_DATA['redirect'])):
				header("Location: ".$data['logout_url']);
			else:
				RestUtils::sendResponse('200');
			endif;

		endwhile;

	break;
	
	/*
	 * ======================================
	 * POST method
	 * ======================================
	 */
	case 'post':
		//Verificação básica
		if(!isset($_DATA['api_key'])):
			RestUtils::sendResponse('412',array('data' => 'api_key', 'message' => 'Por favor defina uma API_KEY.'));
		elseif(!isset($_DATA['username']) || trim($_DATA['username']) == ''):
			RestUtils::sendResponse('412',array('data' => 'username', 'message' => 'Por favor defina um nome de usuário.'));
		elseif(!isset($_DATA['password']) || trim($_DATA['password']) == ''):
			RestUtils::sendResponse('412',array('data' => 'password', 'message' => 'Por favor defina uma senha.'));
		endif;

		//Relembrar login?
		if(isset($_DATA["remember_me"])): 
			$remember_choice = trim($_DATA["remember_me"]);
		else:
			$remember_choice = false;
		endif;
	
		//Conexão
		$sql = new DataBase;
		$sql->connect();
		$sql->query("
			SELECT app.* 
			FROM applications app
			WHERE app.api_key = '".$_DATA['api_key']."'
		");
		
		//Verifica se a API KEY é válida
		if(mysql_num_rows($sql->result) != 1):
			RestUtils::sendResponse('412',array('data' => 'api_key', 'message' => 'A API KEY utilizada não existe.'));
		endif;
		
		//Loop tabela "applications"
		while($data = mysql_fetch_array($sql->result)):
			
			//Verifica a permissão da API KEY
			if($data['permissions_id'] == 0):
				RestUtils::sendResponse('401',array('data' => 'api_key', 'message' => 'Esta API KEY está bloqueada.'));
			endif;
				
			//Buscas as informações do usuário
			$sql2 = new DataBase;
			$sql2->connect();
			$sql2->query("
				SELECT user.id, user.permission_id, user.this_session, user.password
				FROM profiles user
				WHERE user.username = '".$_DATA['username']."'
			");
			
			//Verifica se o usuário existe
			if(mysql_num_rows($sql2->result) != 1):
				RestUtils::sendResponse('412',array('data' => 'login', 'message' => 'O usuário selecionado não existe.'));
			endif;

			//Loop tabela "profiles"
			while($userdetails = mysql_fetch_array($sql2->result)):
				
				//Verifica senha
				$entered_pass = generateHash($_DATA['password'],$userdetails["password"]);
				if($entered_pass != $userdetails["password"]):
					RestUtils::sendResponse('412',array('data' => 'login', 'message' => 'Senha incorreta.'));
				endif;
				
				//Verifica permissão do usuário
				if($userdetails['permission_id'] == 0):
					echo "O usuário não tem permissão";
					RestUtils::sendResponse('401',array('data' => 'login', 'message' => 'O usuário selecionado está bloqueado.'));
				endif;
					
				//Verifica se existem token não-expirados
				// $timeToBuildStructure = 1800;
				// $now = time();
				// $finishedBuilding = $now - $timeToBuildStructure;
				
				$sql3 = new DataBase;
				$sql3->connect();
				$sql3->query("
					SELECT token.*
					FROM token token
					WHERE token.profile_id = '".$userdetails['id']."'
				");

				$sql3->query("DELETE from token WHERE profile_id = '".$userdetails['id']."'");
				
				//Se existirem tokens para o usuário
				//if(mysql_num_rows($sql3->result) != 0):]
					//Loop tabela "token"
					// while($data3 = mysql_fetch_array($sql3->result)):
					// 	//Se existe um ainda a expirar
					// 	if(date("Y-m-d H:i:s",$finishedBuilding) <= $data3['expires']):
						
					// 		//Retorna o token gerado
					// 		$generateToken = $data3['token'];

					// 		//Atualiza tempo de expiração
					// 		$timeToBuildStructure = 1800;						//Segundos (30 * 60 = 30 minutos)
					// 		$now = time();										//Tempo atual (segundos desde 1/1/1970)
					// 		$finishedBuilding = $now + $timeToBuildStructure;	//Tempo a expirar
					// 		$sql4 = new DataBase;
					// 		$sql4->connect();
					// 		$sql4->query("UPDATE token SET expires='".date("Y-m-d H:i:s",$finishedBuilding)."' WHERE token = '".$generateToken."'");

					// 	//Se o token já expirou
					// 	else:
					// 		$sql4 = new DataBase;
					// 		$sql4->connect();
					// 		$sql4->query("DELETE from token WHERE token = '".$data3['token']."'");
					// 	endif;
					// endwhile;
				//endif;

				//Caso seja um novo token
				if(!isset($generateToken)):
					//Gera token
					require_once("../token/class.php");
					$token = new Token;
					$generateToken = $token->generate();

					//Atribui token ao usuário
					$timeToBuildStructure = 1800;						//Segundos (30 * 60 = 30 minutos)
					$now = time();										//Tempo atual (segundos desde 1/1/1970)
					$finishedBuilding = $now + $timeToBuildStructure;	//Tempo a expirar
	
					$sql4 = new DataBase;
					$sql4->connect();
					$sql4->query("INSERT INTO token(token,profile_id,expires,application_id) VALUES ('".$generateToken."','".$userdetails['id']."','".date("Y-m-d H:i:s",$finishedBuilding)."','".$data['id']."')");
				endif;
				
				//Atualiza tempo de último login
				$sql4 = new DataBase;
				$sql4->connect();
				$sql4->query("UPDATE profiles SET last_login='".$userdetails['this_session']."', this_session='".date("Y-m-d H:i:s")."' WHERE profiles.id = '".$userdetails['id']."'");

				//Construct a new logged in user object
				//Transfer some db data to the session object
				// $loggedInUser = new loggedInUser();
				// $loggedInUser->email = $userdetails["email"];
				// $loggedInUser->id = $userdetails["id"];
				// $loggedInUser->hash_pw = $userdetails["password"];
				// $loggedInUser->display_username = $userdetails["username"];
				// $loggedInUser->clean_username = $userdetails["username_clean"];
				// $loggedInUser->remember_me = $remember_choice;
				// $loggedInUser->api_key = $_DATA['api_key'];
				// $loggedInUser->login = $userdetails["email"];
				// $loggedInUser->redirect = 'false';
				// $loggedInUser->remember_me_sessid = generateHash(uniqid(rand(), true));
				
				// //Update last sign in
				// $loggedInUser->updatelast_sign_in();

				// if($loggedInUser->remember_me == 0)
				// 	$_SESSION["userPieUser"] = $loggedInUser;
				// 	else if($loggedInUser->remember_me == 1) {
				// 	$db->sql_query("INSERT INTO ".$db_table_prefix."sessions VALUES('".time()."', '".serialize($loggedInUser)."', '".$loggedInUser->remember_me_sessid."')");
				// 	setcookie("userPieUser", $loggedInUser->remember_me_sessid, time()+parseLength($remember_me_length));
				// }
				 
				//LOG
				$sql4->query("INSERT INTO log(type,message,application_id,profile_id) VALUES ('login','O usuario logou no sistema. Token: ".$generateToken."','".$data['id']."','".$userdetails['id']."')");

				//Retorno
				/*if($data['return_url'] != '' && !isset($_DATA['redirect'])):
					header("Location: ".$data['return_url']."?token=".$generateToken);
				else:*/
					echo $generateToken;
				/*endif;*/
				
				break;
			endwhile;

			$sql->close();
			break;

		endwhile;
	break;	
	
	/////////////////////////////////////DEFAULT
	default:
		RestUtils::sendResponse('405');
		exit;
	break;
};
?>