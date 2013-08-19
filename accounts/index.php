<?php
//Get classes
require_once('../api.php');

//Request
$request = RestUtils::processRequest();
$accounts = new Accounts;
$_DATA = $request->getData();


//Verify Request Method
switch($request->getMethod()) {
	/*
	 * ======================================
	 * GET method
	 * ======================================
	 */
	case 'get':
		//ID
		if(!empty($_DATA['id'])):
			//GET types
			if($_DATA['id'] == 'types'):
				echo json_encode($accounts->getTypes());
				exit();
			
			//Get Balance
			elseif($_DATA['id'] == 'balance'):
				isset($_DATA['account']) ? $account = $_DATA['account'] : $account = "";
				isset($_DATA['year']) ? $year = $_DATA['year'] : $year = "";
				isset($_DATA['month']) ? $month = $_DATA['month'] : $month = "";
				isset($_DATA['orderBy']) ? $orderBy = $_DATA['orderBy'] : $orderBy = "year";
				isset($_DATA['order']) ? $order = $_DATA['order'] : $order = "DESC";
				echo json_encode($accounts->getBalance($account, $month, $year, $orderBy, $order));
				exit();
			
			//Get info of one account	
			else:
				echo json_encode($accounts->get(1,$_DATA['id']));
				exit();
			endif;
			
		//Get ALL
		else:
			isset($_DATA['count']) ? $count = intval($_DATA['count']) : $count = 50;
			echo json_encode($accounts->get($count));
			exit();
		endif;
		
	break;
	
	/*
	 * ======================================
	 * PUT method
	 * ======================================
	 */
	case 'put':
		$_DATA = $request->getRequestVars();
		
		//Set variables
		$data = array(
			'name'				=>	trim(convertToUnicode($_DATA['accountName'])),
			'initialBalance'	=>	number_format(str_replace(',','.',$_DATA['initialBalance']),2,'.',''),
			'accountType'		=>	intval($_DATA['accountType'])
		);
		
		//Verify basic inputs
		if($data['name'] == '' || count($data['name']) > 28): //Name
			RestUtils::sendResponse('406',array('data' => 'name', 'message' => 'Por favor, verifique o nome.'));
			exit;
		elseif($data['initialBalance'] == ''): //Initial Balance
			RestUtils::sendResponse('406',array('data' => 'initialBalance', 'message' => 'Por favor, verifique o valor inicial.'));
			exit;
		elseif($data['accountType'] == ''): //Account type
			RestUtils::sendResponse('406',array('data' => 'accountType', 'message' => 'Por favor, verifique a conta escolhida.'));
			exit;
		endif;
		
		//Verify type
		$haserror = true;
		foreach($accounts->getTypes() as $type):
			if($data['accountType'] == $type['id']):
				$haserror = false;
				break;
			endif;
		endforeach;
		if($haserror):
			RestUtils::sendResponse('406',array('data' => 'accountType', 'message' => 'O tipo de conta n&atilde;o existe.'));
			exit;
		endif;
		
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Generate ID
		$transactionId = generateId();
		
		 //Insert account
		$sql->query("
			INSERT INTO accounts(id,name,profile_id,initial_balance,balance,account_type_id)
			VALUES('".$transactionId."','".$data['name']."','".CurrentUser::getId()."','".$data['initialBalance']."','".$data['initialBalance']."','".$data['accountType']."')
		");	

		
		//Close Connection
		$sql->close();
		RestUtils::sendResponse('201',$transactionId);
		exit;

	break;
	
	/*
	 * ======================================
	 * POST method
	 * ======================================
	 */
	case 'post':
	
		//Set variables
		$data = array(
			'name'				=>	trim(convertToUnicode($_DATA['accountName'])),
			'initialBalance'	=>	number_format(str_replace(',','.', str_replace('.','',$_DATA['initialBalance']) ),2,'.',''),
			'accountType'		=>	intval($_DATA['accountType']),
			'accountId'			=>	trim($_DATA['accountId'])
		);
		
		//Verify basic inputs
		if($data['name'] == '' || count($data['name']) > 28): //Name
			RestUtils::sendResponse('406',array('data' => 'name', 'message' => 'Por favor, verifique o nome.'));
			exit;
		elseif($data['initialBalance'] == ''): //Initial Balance
			RestUtils::sendResponse('406',array('data' => 'initialBalance', 'message' => 'Por favor, verifique o valor inicial.'));
			exit;
		elseif($data['accountType'] == ''): //Account type
			RestUtils::sendResponse('406',array('data' => 'accountType', 'message' => 'Por favor, verifique a conta escolhida.'));
			exit;
		endif;
		
		//Verify type
		$haserror = true;
		foreach($accounts->getTypes() as $type):
			if($data['accountType'] == $type['id']):
				$haserror = false;
				break;
			endif;
		endforeach;
		if($haserror):
			RestUtils::sendResponse('406',array('data' => 'accountType', 'message' => 'O tipo de conta n&atilde;o existe.'));
			exit;
		endif;
		
		//Verify if account exists
		$haserror = true;
		foreach($accounts->get() as $acc):
			if($data['accountId'] == $acc['id']):
				$haserror = false;
				$forProfileId = $acc['profile_id'];
				$balance = $acc['balance'];
				$oldIniBalance = $acc['initial_balance'];
				break;
			endif;
		endforeach;
		
		if($haserror):
			RestUtils::sendResponse('406',array('data' => 'accountId', 'message' => 'A conta escolhida n&atilde;o existe.'));
			exit;
		endif;
		
		if($forProfileId != CurrentUser::getId()):
			RestUtils::sendResponse('406',array('data' => 'accountId', 'message' => 'A conta escolhida n&atilde;o pertence ao usu&aacute;rio.'));
			exit;
		endif;
		
		//Adjust balance
		$balance = $balance - ($oldIniBalance - $data['initialBalance']);
		
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Update account
		$sql->query("
			UPDATE accounts
			SET name='".$data['name']."',
				balance = '".$balance."',
				initial_balance='".$data['initialBalance']."',
				account_type_id='".$data['accountType']."'
			WHERE id = '".$data['accountId']."'
		");
		
		//Update account balances
		$initialBalance = $data['initialBalance'];
		$sql->query("UPDATE accounts_month_balance SET balance = balance - ($oldIniBalance - $initialBalance) WHERE account_id = '" . $data['accountId'] . "'");

		//Close Connection
		$sql->close();
		RestUtils::sendResponse('200');
	break;
	
	/*
	 * ======================================
	 * DELETE method
	 * ======================================
	 */
	case 'delete':
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Verify if account exists
		$haserror = true;
		foreach($accounts->get() as $acc):
			if($_DATA['id'] == $acc['id']):
				$haserror = false;
				$forProfileId = $acc['profile_id'];
				break;
			endif;
		endforeach;

		if($haserror):
			RestUtils::sendResponse('406',array('data' => 'accountId', 'message' => 'A conta escolhida n&atilde;o existe.'));
			exit;
		endif;
		if($forProfileId != CurrentUser::getId()):
			RestUtils::sendResponse('406',array('data' => 'accountId', 'message' => 'A conta escolhida n&atilde;o pertence ao usu&aacute;rio.'));
			exit;
		endif;

		//Disable STATUS
		 $sql->query("UPDATE accounts SET status = 0 WHERE id = '".$_DATA['id']."'");
			
		//Close Connection
		$sql->close();
		RestUtils::sendResponse('200');
		exit;
	break;
	
	/////////////////////////////////////DEFAULT
	default:
		RestUtils::sendResponse('405', array('message' => 'O m&eacute;todo escolhido n&atilde;o &eacute; suportado.'));
		exit;
	break;
}

?>