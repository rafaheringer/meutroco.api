<?php
//Class
class Tags {
	/*
	 * ======================================
	 * GET method, all tags
	 * ======================================
	 */
	function get($count = 500, $orderby = null, $order = null, $from = null, $to = null) {
		//Query
		$sql = new DataBase;
		$sql->connect();
		$query = "
			SELECT DISTINCT tag.*
			FROM tags tag
			WHERE tag.profile_id = ".CurrentUser::getId();
		if($orderby == 'name') $query .= " ORDER BY tag.name";
		if($order == 'ASC') $query .= " ASC";
		if($order == 'DESC') $query .= " DESC";
		if($count && $orderby == 'name') $query .= " LIMIT ".$count;
		
		$sql->query($query);
		
		//Objects
		$json = array();
		
		//Data
		while($data = mysql_fetch_array($sql->result)):
			$sql2 = new DataBase;
			$sql2->connect();
			$query = "
				SELECT DISTINCT transaction.id, transaction.amount
				FROM transactions_has_tags tht, transactions transaction
				WHERE tht.tag_id = ".$data["id"]." AND transaction.id = tht.transaction_id AND transaction.profile_id = ".CurrentUser::getId();
			if($from) $query .= " AND transaction.date >= '".$from."'";
			if($to) $query .= " AND transaction.date <= '".$to."'";

				
			$sql2->query($query);
			$json2 = array();
			$totalSpend = 0;
			
			while($data2 = mysql_fetch_array($sql2->result)):
				array_push($json2, $data2["id"]);
				$totalSpend += $data2['amount'];
			endwhile;
			
			$array = array(
				"id"			=>	$data["id"],
				"name"			=>	$data["name"],
				"transactions"	=> $json2,
				"total_spend"	=> $totalSpend
			);
			
			array_push($json, $array);
		endwhile;
		
		//Close connection
		$sql->close();
		
		//Order By
		switch($orderby):
			case "most_expensive":
				($order == "asc" || $order == "ASC") ? $json = array_sort($json, 'total_spend') : $json = array_sort($json, 'total_spend', SORT_DESC);
			break;
			case "most_valuable":
				($order == "asc" || $order == "ASC") ? $json = array_sort($json, 'total_spend') : $json = array_sort($json, 'total_spend', SORT_DESC);
			break;
			
		endswitch;
		
		//Finally
		$t = array();
		foreach ($json as $a): array_push($t, $a); endforeach;
		$t = array_slice($t,0,$count);
		
		//Return
		return $t;
	}
	
	/*
	 * ======================================
	 * GET method, unique tag
	 * ======================================
	 */
	function getUnique($id) {
		$sql = new DataBase;
		$sql->connect();
		$sql->query("
		SELECT DISTINCT tag.*
		FROM tags tag
		WHERE tag.id = ".$id."
		");
		
		//Objects
		$json = array();
		
		//Data
		while($data = mysql_fetch_array($sql->result)):
			$array = array(
				"name"			=>	$data["name"],
				"profile_id"	=>	$data["profile_id"]
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
	 * GET method, tags from transaction
	 * ======================================
	 */
	function getTransactionTags($count, $id) {
		$sql = new DataBase;
		$sql->connect();
		$sql->query("
		SELECT tag.*
		FROM tags tag, transactions_has_tags tht
		WHERE tht.transaction_id = '".$id."' AND tag.profile_id = ".CurrentUser::getId()."
		AND tag.id = tht.tag_id
		LIMIT ".$count."
		");
		
		//Objects
		$json = array();
		
		//Data
		while($data = mysql_fetch_array($sql->result)):
			$array = array(
				"id"		=>	$data["id"],
				"name"		=>	$data["name"]
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
	}
	
	/*
	 * ======================================
	 * POST method
	 * ======================================
	 */
	function post(){
		//Connect
		$sql = new DataBase;
		$sql->connect();
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
	}	
}
?>