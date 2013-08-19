<?php
//Class
class User {
	/*
	 * ======================================
	 * GET method
	 * ======================================
	 */
	function getInfo() {
		//Connect
		$sql = new DataBase;
		$sql->connect();
		

		//Query
		$sql->query("
			SELECT user.*
			FROM profiles user
			WHERE id = '".CurrentUser::getId()."'
		");
		
		//Objects
		$json = array();
		
		//Data
		while($data = mysql_fetch_array($sql->result)) {
			$firstName = explode(" ",$data["name"]);
			$firstName = $firstName[0];
			$lastName = array_reverse(explode(" ",$data["name"]));
			$lastName = $lastName[0];
			
			//Its new user?
			$accounts = new Accounts;
			$accounts = $accounts->get(1);
			$haveAccounts = count($accounts);
			
			//Infos
			$array = array(
				"id"			=>	$data["id"],
				"name"			=>	$data["name"],
				"firstName"		=>	$firstName,
				"lastName"		=>	$lastName,
				"birthday"		=>	$data["birthday"],
				"gender"		=>	$data["gender"] == "m" ? "male" : "female",
				"email"			=>	$data["email"],
				"facebookId"	=>	$data["facebook_id"],
				"photoUrl"		=>	$data["photo_url"],
				"invitesCount"	=>	$data["invites_count"],
				"lastLogin"		=>	$data["last_login"],
				"thisSession"	=>	$data["this_session"],
				"regiserDate"	=>	$data["sign_up_date"],
				"newUser"		=>	($haveAccounts ==  1) ? false : true
			);
			array_push($json, $array);
		}
		
		//Close Connection
		$sql->close();
		return $json;
	}

	/*
	 * ======================================
	 * POST method
	 * ======================================
	 */
	function updateUser($data){
		//Verify basic inputs
		if($data['name'] == '' || count($data['name']) > 45):
			RestUtils::sendResponse('406',array('data' => 'name', 'message' => 'Por favor, verifique o nome.'));
		elseif($data['gender'] == ''):
			RestUtils::sendResponse('406',array('data' => 'gender', 'message' => 'Por favor, verifique o sexo.'));
		elseif($data['email'] == '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)):
			RestUtils::sendResponse('406',array('data' => 'email', 'message' => 'Por favor, verifique o email.'));
		endif;

		//Connect
		$sql = new DataBase;
		$sql->connect();

		//Query
		$sql->query("
			SELECT DISTINCT user.*
			FROM profiles user
			WHERE id = '".CurrentUser::getId()."' LIMIT 1
		");

		while($userProfile = mysql_fetch_array($sql->result)):
			//Verify password
			if($userProfile['password'] != $data['password']):
				RestUtils::sendResponse('406',array('data' => 'password', 'message' => 'Por favor, verifique a senha atual.'));
			endif;
		endwhile;

		//Update profile
		$sql->query("
		 	UPDATE profiles
			SET name='".$data['name']."',
				birthday='".$data['birthday']."',
				gender='".$data['gender']."',
				email='".$data['email']."'
			WHERE profiles.id = '".CurrentUser::getId()."'
		");

		//Update password, if have
		if($data['newPassword'] != "" & $data['newPassword'] != null) {
			$sql->query("UPDATE profiles SET password='".$data['newPassword']."' WHERE profiles.id = '".CurrentUser::getId()."'");
		}

		//Close Connection
		$sql->close();
	}
	
};

?>