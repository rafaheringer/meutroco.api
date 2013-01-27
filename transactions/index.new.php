<?php
//Get classes
require_once('../api.php');

//Request
$request = RestUtils::processRequest();
$transactions = new Transactions;

switch($request->getMethod()):
	/*
	 * ======================================
	 * GET method
	 * ======================================
	 */
	case 'get':
		//Get by Id
		if(!empty($_GET['id'])):
			if($_GET['id'] == 'types'):
				echo json_encode($transactions->getTypes());
			endif;
			
		//Get ALL
		else:
			isset($_GET['count']) ? $count = intval($_GET['count']) : $count = 10;
			isset($_GET['from']) ? $from = $_GET['from'] : $from = "";
			isset($_GET['to']) ? $to = $_GET['to'] : $to = "";
			isset($_GET['account']) ? $account = $_GET['account'] : $account = "";
			echo json_encode($transactions->get($count, $from, $to, $account));
		endif;
		
	break;
	
	/*
	 * ======================================
	 * PUT method
	 * ======================================
	 */
	case 'put':
		//Request
		$_PUT = $request->getRequestVars();
		
		//Instances
		$accounts = new Accounts;
		$tags = new Tags;
	
		//Set variables
		$var['dateExploded'] = explode('-',str_replace('/','-',$_PUT['date']));
		(count($var['dateExploded']) == 3) ? $var['date'] = $var['dateExploded'][2].'-'.$var['dateExploded'][1].'-'.$var['dateExploded'][0] : $var['date'] = '';
		$data = array(
			'description'		=>	trim(convertToUnicode($_PUT['description'])),
			'amount'			=>	number_format(str_replace(',','.',str_replace('.','',$_PUT['amount'])),2,'.',''),
			'transType'			=>	intval($_PUT['transType']),
			'accountFrom'		=>	trim($_PUT['accountFrom']),
			'accountTo'			=>	trim($_PUT['accountTo']),
			'date'				=>	$var['date'],
			'tags'				=>	trim($_PUT['tags'])
		);
		($data['transType'] != 3) ? $data['accountTo'] = '' : $data['amount'] = number_format($data['amount']*-1,2,'.','');
		($data['transType'] == 1) ? $data['amount'] = number_format($data['amount']*-1,2,'.','') : $data['amount'];
		
		//Verify basic inputs
		if($data['description'] == '' || count($data['description']) > 28): //Description
			RestUtils::sendResponse('406',array('data' => 'description', 'message' => 'Por favor, verifique a descri&ccedil;&atilde;o.'));
		elseif($data['amount'] == '' || $data['amount'] == '0.00'): //Amount
			RestUtils::sendResponse('406',array('data' => 'amount', 'message' => 'Por favor, verifique o valor.'));
		elseif($data['date'] == '' || !checkdate(intval($var['dateExploded'][1]),intval($var['dateExploded'][0]),intval($var['dateExploded'][2]))): //Date
			RestUtils::sendResponse('406',array('data' => 'amount', 'message' => 'Por favor, verifique a data.'));
		elseif($data['transType'] == ''): //Transactiom type
			RestUtils::sendResponse('406',array('data' => 'transType', 'message' => 'Por favor, verifique o tipo de transa&ccedil;&atilde;o.'));
		elseif($data['accountFrom'] == ''): //Account from
			RestUtils::sendResponse('406',array('data' => 'accountFrom', 'message' => 'Por favor, verifique a conta escolhida.'));
		endif;
		
		//Verify accounts
		$haserror = true;
		foreach($accounts->get() as $acc):
			if($data['accountFrom'] == $acc['id']):
				$haserror = false;
				break;
			endif;
		endforeach;
		if($haserror)
			RestUtils::sendResponse('406',array('data' => 'accountFrom', 'message' => 'A conta escolhida n&atilde;o existe.'));
		
		if($data['accountTo'] != ''):
			$haserror = true;
			foreach($accounts->get() as $acc):
				if($data['accountTo'] == $acc['id']):
					$haserror = false;
					break;
				endif;
			endforeach;
			if($haserror)
				RestUtils::sendResponse('406',array('data' => 'accountTo', 'message' => 'A conta escolhida n&atilde;o existe.'));
		endif;
		
		//Verify type
		$haserror = true;
		foreach($accounts->getTypes() as $type):
			if($data['transType'] == $type['id']):
				$haserror = false;
				break;
			endif;
		endforeach;
		if($haserror)
			RestUtils::sendResponse('406',array('data' => 'transType', 'message' => 'O tipo de conta n&atilde;o existe.'));
			
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Generate ID
		$transactionId = generateId();
		
		//Get all tags to compare
		$allTagsCompare = array();
		foreach($tags->get() as $tag):
			$tag = strtolower(clearUTF($tag['name']));
			array_push($allTagsCompare, $tag);
		endforeach;
		
		//Get all tags of transaction
		$transactionTags = explode(',',$data['tags']);
		$i = 0;
		foreach($transactionTags as $tag):
			if($tag == "" || $tag == " " || $tag == "," || $tag == ", ")
				unset($transactionTags[$i]);
			$i++;
		endforeach;
		
		//Get all tags of USER of transaction compare
		 $transactionTagsCompare = array();
		 foreach($transactionTags as $tag):
		 	$tag = prepareToCompare($tag);
			array_push($transactionTagsCompare, $tag);
		 endforeach;
		 
		//Insert transaction
		$sql->query("
			INSERT INTO transactions(id,description,amount,date,account_to_id,account_from_id,transaction_type_id,profile_id)
			VALUES('".$transactionId."','".$data['description']."','".$data['amount']."','".$data['date']."','".$data['accountTo']."','".$data['accountFrom']."','".$data['transType']."','".CurrentUser::getId()."')
		");
		
		//Insert tags
		foreach($tags->get() as $tag):
			if(in_array(prepareToCompare($tag['name']), $transactionTagsCompare)): //IF have in BD
				$sql->query("
					INSERT INTO transactions_has_tags(transaction_id,tag_id)
					VALUES('".$transactionId."','".$tag['id']."')
				");
			endif;
		endforeach;
		
		foreach($transactionTags as $tag):
			if(!in_array(prepareToCompare($tag), $allTagsCompare)): //IF DONT have in BD
				$sql->query("
					INSERT INTO tags(name, profile_id)
					VALUES ('".ucfirst(trim(convertToUnicode($tag)))."','".CurrentUser::getId()."')
				");
				$tagId = $sql->insertId;
				$sql->query("
					INSERT INTO transactions_has_tags(transaction_id,tag_id)
					VALUES('".$transactionId."','".$tagId."')
				");
			endif;
		endforeach;
		
		//Add in Ammount
		$balance = $accounts->get(1,$data['accountFrom'],'balance');
		$balance += $data['amount'];
		$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data['accountFrom']."'");
		if($data['accountTo'] != ''):
			$balance = $accounts->get(1,$data['accountTo'],'balance');
			$balance -= $data['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data['accountTo']."'");
		endif;
		
		//Close Connection
		$sql->close();
		RestUtils::sendResponse('201',$transactionId);
	break;

	/*
	 * ======================================
	 * POST method
	 * ======================================
	 */
	case 'post':
		//Instances
		$accounts = new Accounts;
		$tags = new Tags;
		
		//Set date
		$var['dateExploded'] = explode('-',str_replace('/','-',$_POST['date']));
		$date = count($var['dateExploded']) == 3 ?  $var['dateExploded'][2].'-'.$var['dateExploded'][1].'-'.$var['dateExploded'][0] : '';
		
		//Others
		$description 	= trim(convertToUnicode($_POST['description']));
		$amount			= number_format(str_replace(',','.',str_replace('.','',$_POST['amount'])),2,'.','');
		$transType		= intval($_POST['transType']);
		$accountFrom	= trim($_POST['accountFrom']);
		$accountTo		= trim($_POST['accountTo']);
		$tags			= trim($_POST['tags']);
		$transactionId	= trim($_POST['transactionId']);
		
		//Transaction Type
		$transType != 3 ? $accountTo = '' : $amount = number_format($amount*-1,2,'.','');
		if($transType == 1) $amount = number_format($amount*-1,2,'.','');
		
		//Verify basic inputs
		if($description == ''): //Description
			RestUtils::sendResponse('406',array('data' => 'description', 'message' => 'Por favor, verifique a descri&ccedil;&atilde;o.'));
		elseif($amount == '' || $amount == '0.00'): //Amount
			RestUtils::sendResponse('406',array('data' => 'amount', 'message' => 'Por favor, verifique o valor.'));
		elseif($date == '' || !checkdate(intval($var['dateExploded'][1]),intval($var['dateExploded'][0]),intval($var['dateExploded'][2]))): //Date
			RestUtils::sendResponse('406',array('data' => 'amount', 'message' => 'Por favor, verifique a data.'));
		elseif($transType == ''): //Transactiom type
			RestUtils::sendResponse('406',array('data' => 'transType', 'message' => 'Por favor, verifique o tipo de transa&ccedil;&atilde;o.'));
		elseif($accountFrom == ''): //Account from
			RestUtils::sendResponse('406',array('data' => 'accountFrom', 'message' => 'Por favor, verifique a conta escolhida.'));
		endif;
		
		
		//Run	
		$transactions->post($transactionId, $description, $amount, $transType, $accountFrom, $accountTo, $date, $tags);
		
				exit();
		
		
		
			
		
		
		//Verify accounts
		$haserror = true;
		foreach($accounts->get() as $acc):
			if($data['accountFrom'] == $acc['id']):
				$haserror = false;
				break;
			endif;
		endforeach;
		if($haserror)
			RestUtils::sendResponse('406',array('data' => 'accountFrom', 'message' => 'A conta escolhida n&atilde;o existe.'));
		
		if($data['accountTo'] != ''):
			$haserror = true;
			foreach($accounts->get() as $acc):
				if($data['accountTo'] == $acc['id']):
					$haserror = false;
					break;
				endif;
			endforeach;
			if($haserror)
				RestUtils::sendResponse('406',array('data' => 'accountTo', 'message' => 'A conta escolhida n&atilde;o existe.'));
		endif;
		
		//Verify type
		$haserror = true;
		foreach($accounts->getTypes() as $type):
			if($data['transType'] == $type['id']):
				$haserror = false;
				break;
			endif;
		endforeach;
		if($haserror)
			RestUtils::sendResponse('406',array('data' => 'transType', 'message' => 'O tipo de conta n&atilde;o existe.'));
			
		//Verify if transaction is for the user
		$tr = $transactions->get('1','','','',$data['transactionId']);
		if(count($tr) == 0)
			RestUtils::sendResponse('406',array('data' => 'transactionId', 'message' => 'Essa transa&ccedil;&atilde;o n&atilde;o existe.'));
		if($tr[0]['profile_id'] != CurrentUser::getId())
			RestUtils::sendResponse('406',array('data' => 'transactionId', 'message' => 'Essa transa&ccedil;&atilde;o n&atilde;o pertence ao perfil.'));
		
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Get all tags to compare
		if($data['tags'] != "" || $data['tags'] != NULL):
			$allTagsCompare = array();
			foreach($tags->get() as $tag):
				$tag = strtolower(clearUTF($tag['name']));
				array_push($allTagsCompare, $tag);
			endforeach;
			
			//Get all tags of transaction
			$transactionTags = explode(',',$data['tags']);
			$i = 0;
			foreach($transactionTags as $tag):
				if($tag == "" || $tag == " ")
					unset($transactionTags[$i]);
				$i++;
			endforeach;
			
			//Get all tags of transaction compare
			 $transactionTagsCompare = array();
			 foreach($transactionTags as $tag):
				$tag = trim(strtolower(clearUTF($tag)));
				array_push($transactionTagsCompare, $tag);
			 endforeach;
		endif;
		 
		 //Update transaction
		 $sql->query("
		 	UPDATE transactions
			SET description='".$data['description']."',
				amount='".$data['amount']."',
				date='".$data['date']."',
				account_to_id='".$data['accountTo']."',
				account_from_id='".$data['accountFrom']."',
				transaction_type_id='".$data['transType']."'
			WHERE transactions.id = '".$data['transactionId']."'
		 ");
		 
		 //Remove tags relationship
		$sql->query("
		 	DELETE FROM transactions_has_tags
			WHERE transaction_id='".$data['transactionId']."'
		 ");
		 
		//Insert tags
		if($data['tags'] != "" || $data['tags'] != NULL):
			foreach($tags->get() as $tag):
				if(in_array(trim(strtolower(clearUTF($tag['name']))), $transactionTagsCompare)): //IF have in BD
					$sql->query("
						INSERT INTO transactions_has_tags(transaction_id,tag_id)
						VALUES('".$data['transactionId']."','".$tag['id']."')
					");
				endif;
			endforeach;
			
			foreach($transactionTags as $tag):
				if(!in_array(strtolower(clearUTF(trim($tag))), $allTagsCompare)): //IF DONT have in BD
					$sql->query("
						INSERT INTO tags(name, profile_id)
						VALUES ('".trim(convertToUnicode($tag))."','".CurrentUser::getId()."')
					");
					$tagId = $sql->insertId;
					$sql->query("
						INSERT INTO transactions_has_tags(transaction_id,tag_id)
						VALUES('".$data['transactionId']."','".$tagId."')
					");
				endif;
			endforeach;
		endif;
		
		//Add in Ammount
		if($data['accountTo'] != ''):
			$balance = $accounts->get(1,$data['accountFrom'],'balance');
			$balance += $oldData[0]['amount'];
			$balance += $data['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data['accountFrom']."'");
			
			$balance = $accounts->get(1,$data['accountTo'],'balance');
			$balance -= $oldData[0]['amount'];
			$balance -= $data['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data['accountTo']."'");
		else:
			$balance = $accounts->get(1,$data['accountFrom'],'balance');
			$balance -= $oldData[0]['amount'];
			$balance += $data['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data['accountFrom']."'");
		endif;

		//Close Connection
		$sql->close();
		RestUtils::sendResponse('200');
	break;
	
	
	/////////////////////////////////////DELETE
	case 'delete':
		//Instances and Variables
		$ID = $_GET['id'];
		//$transactions = new Transactions;
		$accounts = new Accounts;
	
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Verify if exists
		$tr = $transactions->get('all','','','',$ID);
		$data = $tr;
		
		if(count($tr) == 0)
			RestUtils::sendResponse('406',array('data' => 'transactionId', 'message' => 'Essa transa&ccedil;&atilde;o n&atilde;o existe.'));
		if($tr[0]['profile_id'] != CurrentUser::getId())
			RestUtils::sendResponse('406',array('data' => 'transactionId', 'message' => 'Essa transa&ccedil;&atilde;o n&atilde;o pertence ao perfil.'));
			
		//Remove
		$sql->query("DELETE FROM transactions_has_tags WHERE transaction_id = '".$ID."'");
		$sql->query("DELETE FROM transactions WHERE id = '".$ID."'");
		
		//Remove in Ammount
		if($data[0]['account_to'] != ''):
			$balance = $accounts->get(1,$data[0]['account_from'],'balance');
			$balance += $data[0]['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data[0]['account_from']."'");
			
			$balance = $accounts->get(1,$data[0]['account_to'],'balance');
			$balance -= $data[0]['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data[0]['account_to']."'");
		else:
			$balance = $accounts->get(1,$data[0]['account_from'],'balance');
			$balance -= $data[0]['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data[0]['account_from']."'");
		endif;

		
		//Close Connection
		$sql->close();
		RestUtils::sendResponse('200');
	break;
		
	/*
	 * ======================================
	 * Default
	 * ======================================
	 */
	default:
		RestUtils::sendResponse('405'); //Method not allowed
	break;

endswitch;

?>