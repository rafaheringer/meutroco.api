<?php
//Get classes
require_once('../api.php');

//Request
$request = RestUtils::processRequest();
$transactions = new Transactions;
$_DATA = $request->getData();

switch($request->getMethod()):
	/*
	 * ======================================
	 * GET method
	 * ======================================
	 */
	case 'get':
		//Get by Id
		if(!empty($_DATA['id'])):
			if($_DATA['id'] == 'types'):
				echo json_encode($transactions->getTypes());
			endif;
			exit;
		
		//Get by Tag
		elseif(!empty($_DATA['tag'])):
			isset($_DATA['count']) ? $count = intval($_DATA['count']) : $count = 10;
			isset($_DATA['from']) ? $from = $_DATA['from'] : $from = "";
			isset($_DATA['to']) ? $to = $_DATA['to'] : $to = "";
			isset($_DATA['account']) ? $account = $_DATA['account'] : $account = "";
			isset($_DATA['tag']) ? $tag = $_DATA['tag'] : $tag = "";
			isset($_DATA['orderby']) ? $orderby = $_DATA['orderby'] : $orderby = 'id';
			isset($_DATA['order']) ? $order = $_DATA['order'] : $order = 'asc';
			echo json_encode($transactions->getByTag($count, $from, $to, $account, $tag, $orderby, $order));
			exit;
		
		//Get ALL
		else:
			isset($_DATA['count']) ? $count = intval($_DATA['count']) : $count = 10;
			isset($_DATA['from']) ? $from = $_DATA['from'] : $from = "";
			isset($_DATA['to']) ? $to = $_DATA['to'] : $to = "";
			isset($_DATA['account']) ? $account = $_DATA['account'] : $account = "";
			isset($_DATA['orderby']) ? $orderby = $_DATA['orderby'] : $orderby = 'id';
			isset($_DATA['order']) ? $order = $_DATA['order'] : $order = 'asc';
			echo json_encode($transactions->get($count, $from, $to, $account, "", $orderby, $order));
			exit;
		endif;
		
	break;
	
	/*
	 * ======================================
	 * PUT method
	 * ======================================
	 */
	case 'put':
		//Request
		$_DATA = $request->getRequestVars();
		
		//Instances
		$accounts = new Accounts;
		$tags = new Tags;
	
		//Set variables
		$var['dateExploded'] = explode('-',str_replace('/','-',$_DATA['date']));
		(count($var['dateExploded']) == 3) ? $var['date'] = $var['dateExploded'][2].'-'.$var['dateExploded'][1].'-'.$var['dateExploded'][0] : $var['date'] = '';
		$data = array(
			'description'		=>	trim(convertToUnicode($_DATA['description'])),
			'amount'			=>	number_format(str_replace(',','.',str_replace('.','',$_DATA['amount'])),2,'.',''),
			'transType'			=>	intval($_DATA['transType']),
			'accountFrom'		=>	trim($_DATA['accountFrom']),
			'accountTo'			=>	trim($_DATA['accountTo']),
			'date'				=>	$var['date'],
			'tags'				=>	trim($_DATA['tags'])
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
				$sql->query("INSERT INTO transactions_has_tags(transaction_id,tag_id) VALUES ('".$transactionId."','".$tag['id']."')");
			endif;
		endforeach;
		
		foreach($transactionTags as $tag):
			if(!in_array(prepareToCompare($tag), $allTagsCompare)): //IF DONT have in BD
				$sql->query("INSERT INTO tags(name, profile_id) VALUES ('".ucfirst(trim(convertToUnicode($tag)))."','".CurrentUser::getId()."')");
				$tagId = $sql->insertId;
				$sql->query("INSERT INTO transactions_has_tags(transaction_id,tag_id) VALUES ('".$transactionId."','".$tagId."')");
			endif;
		endforeach;
		
		//Add in Ammount
		$balance = $accounts->get(1,$data['accountFrom'],'balance');
		$balance += $data['amount'];
		$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data['accountFrom']."'");
		$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance + " . $data['amount'] . " WHERE amb.account_id = '" . $data['accountFrom'] . "' AND amb.year >= " . date('Y', strtotime($data['date'])) . " AND amb.month >= " . date('n', strtotime($data['date'])) . "");
		if($data['accountTo'] != ''):
			$balance = $accounts->get(1,$data['accountTo'],'balance');
			$balance -= $data['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data['accountTo']."'");
			$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance - " . $data['amount'] . " WHERE amb.account_id = '" . $data['accountTo'] . "' AND amb.year >= " . date('Y', strtotime($data['date'])) . " AND amb.month >= " . date('n', strtotime($data['date'])) . "");
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
		
		//Set variables
		$var['dateExploded'] = explode('-',str_replace('/','-',$_DATA['date']));
		if(count($var['dateExploded']) == 3)
			$var['date'] = $var['dateExploded'][2].'-'.$var['dateExploded'][1].'-'.$var['dateExploded'][0];
		else
			$var['date'] = '';
			
		$data = array(
			'description'		=>	trim(convertToUnicode($_DATA['description'])),
			'amount'			=>	number_format(str_replace(',','.',str_replace('.','',$_DATA['amount'])),2,'.',''),
			'transType'			=>	intval($_DATA['transType']),
			'accountFrom'		=>	trim($_DATA['accountFrom']),
			'accountTo'			=>	trim($_DATA['accountTo']),
			'date'				=>	$var['date'],
			'tags'				=>	trim($_DATA['tags']),
			'transactionId'		=>	trim($_DATA['transactionId'])
		);
		
		$oldData = $transactions->get('all','','','',$data['transactionId']);
		
		if($data['transType'] != 3) {
			$data['accountTo'] = '';
		} else {
			$data['amount'] =  number_format($data['amount']*-1,2,'.','');
		}
		if($data['transType'] == 1)
			$data['amount'] =  number_format($data['amount']*-1,2,'.','');
			
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
		
		//Remove in Ammount
		if($oldData[0]['account_to'] != ''):
			$balance = $accounts->get(1,$oldData[0]['account_from'],'balance');
			$balance += $oldData[0]['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$oldData[0]['account_from']."'");
			$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance + " . $oldData[0]['amount'] . " WHERE amb.account_id = '" . $oldData[0]['account_from'] . "' AND amb.year >= " . date('Y', strtotime($oldData[0]['date'])) . " AND amb.month >= " . date('n', strtotime($oldData[0]['date'])) . "");
			
			$balance = $accounts->get(1,$oldData[0]['account_to'],'balance');
			$balance -= $oldData[0]['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$oldData[0]['account_to']."'");
			$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance - " . $oldData[0]['amount'] . " WHERE amb.account_id = '" . $oldData[0]['account_to'] . "' AND amb.year >= " . date('Y', strtotime($oldData[0]['date'])) . " AND amb.month >= " . date('n', strtotime($oldData[0]['date'])) . "");
		else:
			$balance = $accounts->get(1,$oldData[0]['account_from'],'balance');
			$balance -= $oldData[0]['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$oldData[0]['account_from']."'");
			$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance - " . $oldData[0]['amount'] . " WHERE amb.account_id = '" . $oldData[0]['account_from'] . "' AND amb.year >= " . date('Y', strtotime($oldData[0]['date'])) . " AND amb.month >= " . date('n', strtotime($oldData[0]['date'])) . "");
		endif;
		
		//Add in Ammount
		if($data['accountTo'] != ''):
			$balance = $accounts->get(1,$data['accountFrom'],'balance');
			$balance += $data['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data['accountFrom']."'");
			$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance + " . $data['amount'] . " WHERE amb.account_id = '" . $data['accountFrom'] . "' AND amb.year >= " . date('Y', strtotime($data['date'])) . " AND amb.month >= " . date('n', strtotime($data['date'])) . "");
			
			$balance = $accounts->get(1,$data['accountTo'],'balance');
			$balance -= $data['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data['accountTo']."'");
			$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance - " . $data['amount'] . " WHERE amb.account_id = '" . $data['accountTo'] . "' AND amb.year >= " . date('Y', strtotime($data['date'])) . " AND amb.month >= " . date('n', strtotime($data['date'])) . "");
		else:
			$balance = $accounts->get(1,$data['accountFrom'],'balance');
			$balance += $data['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data['accountFrom']."'");
			$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance + " . $data['amount'] . " WHERE amb.account_id = '" . $data['accountFrom'] . "' AND amb.year >= " . date('Y', strtotime($data['date'])) . " AND amb.month >= " . date('n', strtotime($data['date'])) . "");
		endif;

		//Close Connection
		$sql->close();
		RestUtils::sendResponse('200');
	break;
	
	
	/////////////////////////////////////DELETE
	case 'delete':
		//Instances and Variables
		$ID = $_DATA['id'];
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
		
		//Remove in Ammount
		if($data[0]['account_to'] != ''):
			$balance = $accounts->get(1,$data[0]['account_from'],'balance');
			$balance += $data[0]['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data[0]['account_from']."'");
			$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance + " . $data[0]['amount'] . " WHERE amb.account_id = '" . $data[0]['account_from'] . "' AND amb.year >= " . date('Y', strtotime($data[0]['date'])) . " AND amb.month >= " . date('n', strtotime($data[0]['date'])) . "");
			
			$balance = $accounts->get(1,$data[0]['account_to'],'balance');
			$balance -= $data[0]['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data[0]['account_to']."'");
			$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance - " . $data[0]['amount'] . " WHERE amb.account_id = '" . $data[0]['account_to'] . "' AND amb.year >= " . date('Y', strtotime($data[0]['date'])) . " AND amb.month >= " . date('n', strtotime($data[0]['date'])) . "");
		else:
			$balance = $accounts->get(1,$data[0]['account_from'],'balance');
			$balance -= $data[0]['amount'];
			$sql->query("UPDATE accounts SET balance='".$balance."' WHERE id = '".$data[0]['account_from']."'");
			$sql->query("UPDATE accounts_month_balance AS amb SET amb.balance = amb.balance - " . $data[0]['amount'] . " WHERE amb.account_id = '" . $data[0]['account_from'] . "' AND amb.year >= " . date('Y', strtotime($data[0]['date'])) . " AND amb.month >= " . date('n', strtotime($data[0]['date'])) . "");
		endif;

		//Remove
		$sql->query("DELETE FROM transactions_has_tags WHERE transaction_id = '".$ID."'");
		$sql->query("DELETE FROM transactions WHERE id = '".$ID."'");
		
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