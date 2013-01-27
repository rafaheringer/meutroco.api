<?php
class Transactions {
	/*
	 * ======================================
	 * GET method
	 * ======================================
	 */
	function get($count = 10, $from = "", $to = "", $account = "", $id = "") {
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Query
		$query = "
			SELECT DISTINCT transaction.*
			FROM transactions transaction
			WHERE transaction.profile_id = ".CurrentUser::getId()." ";
			if(!empty($from)) $query .= "AND transaction.date >= '".$from."' ";
			if(!empty($to)) $query .= "AND transaction.date <= '".$to."' ";
			if(!empty($account)) $query .= "AND (transaction.account_from_id = '".$account."' || transaction.account_to_id = '".$account."') ";
			if(!empty($id)) $query .= "AND transaction.id = '".$id."' ";
			$query .= "ORDER BY transaction.date DESC ";
			if($count != "all") $query .= "LIMIT ".$count;
			
		$sql->query($query);
		
		//Objects
		$json = array();
		
		//Instances
		$tags = new Tags;
		$accounts = new Accounts;
		
		//Data
		while($data = mysql_fetch_array($sql->result)):
			$array = array(
				"id"			=>	$data["id"],
				"description"	=>	$data["description"],
				"amount"		=>	($data['transaction_type_id'] == 3 && $data["account_from_id"] != $account) ? $data["amount"]*-1 : $data["amount"],
				"type"			=>	$data["transaction_type_id"],
				"date"			=>	$data["date"],
				"account_from"	=>	$data["account_from_id"],
				"account_to"	=>	$data["account_to_id"],
				"account_type"	=>	$accounts->get(1,$data["account_from_id"],"account_type_id"),
				"profile_id"	=>	$data["profile_id"],
				'tags'			=>	$tags->getTransactionTags(1000, $data["id"])
			);
			array_push($json, $array);
		endwhile;
		
		//Close connection
		//$sql->close();
		
		//Return
		return $json;
	}
	
	/*
	 * ======================================
	 * GET method, by Tag
	 * ======================================
	 */
	 function getByTag($count = 10, $from = "", $to = "", $account = "", $tag = "", $orderby = "", $order = "") {
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Query
		$query = "
			SELECT tht.transaction_id, tht.tag_id , transaction.*
			FROM transactions_has_tags tht, transactions transaction
			WHERE tht.tag_id = '".$tag."' AND transaction.id = tht.transaction_id ";
			if(!empty($from)) $query .= "AND transaction.date >= '".$from."' ";
			if(!empty($to)) $query .= "AND transaction.date <= '".$to."' ";
			if(!empty($account)) $query .= "AND (transaction.account_from_id = '".$account."' || transaction.account_to_id = '".$account."') ";
			if(!empty($id)) $query .= "AND transaction.id = '".$id."' ";
			if($orderby == 'date') $query .= " ORDER BY transaction.date ";
			if($order == "ASC") $query .= "ASC ";
			if($order == "DESC") $query .= "DESC ";
			

		$sql->query($query);
		
		//Objects
		$json = array();
		
		//Instances
		$tags = new Tags;
		$accounts = new Accounts;
		
		//Data
		while($data = mysql_fetch_array($sql->result)):
			$array = array(
				"id"			=>	$data["id"],
				"description"	=>	$data["description"],
				"amount"		=>	($data['transaction_type_id'] == 3 && $data["account_from_id"] != $account) ? $data["amount"]*-1 : $data["amount"],
				"type"			=>	$data["transaction_type_id"],
				"date"			=>	$data["date"],
				"account_from"	=>	$data["account_from_id"],
				"account_to"	=>	$data["account_to_id"],
				"account_type"	=>	$accounts->get(1,$data["account_from_id"],"account_type_id"),
				"profile_id"	=>	$data["profile_id"],
				'tags'			=>	$tags->getTransactionTags(1000, $data["id"])
			);
			array_push($json, $array);
		endwhile;
		
		//Close connection
		//$sql->close();
		
		//Return
		return $json;
	 }
	
	/*
	 * ======================================
	 * GET method, get transactions types
	 * ======================================
	 */
	function getTypes() {
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Query
		$sql->query("
		SELECT DISTINCT type.*
		FROM transactions_type type
		ORDER BY type.id
		");
		
		//Objects
		$json = array();
		
		//Data
		while($data = mysql_fetch_array($sql->result)):
			$array = array(
				"id"			=>	$data["id"],
				"name"			=>	$data["name"]
			);
			array_push($json, $array);
		endwhile;
		
		//Close connection
		$sql->close();
		
		//Return
		return $json;
		
	}
	
	/*
	 * ======================================
	 * PUT method
	 * ======================================
	 */
	function put(){
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		
		
		//Close connection
		$sql->close();
	}
	
	/*
	 * ======================================
	 * POST method
	 * ======================================
	 */
	function post($transactionId, $description, $amount, $transType, $accountFrom, $accountTo, $date, $tags){
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Get old data
		$oldData = $this->get('all','','','',$transactionId);
		
		
		
		
		
		RestUtils::sendResponse('406',array('data' => 'description', 'message' => 'Por favor, verifique a descri&ccedil;&atilde;o.'));
		
		//Close connection
		$sql->close();
	}
	
	/*
	 * ======================================
	 * DELETE method
	 * ======================================
	 */
	function delete(){
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Close connection
		$sql->close();
	}
}
?>