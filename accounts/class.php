<?php
//Class
class Accounts {
	/*
	 * ======================================
	 * GET method, all accounts
	 * ======================================
	 */
	function get($count = 50, $id = "", $info = "all") {
		//Connect
		$sql = new DataBase;
		$sql->connect();
		$query = "
		SELECT DISTINCT acc.*
		FROM accounts acc
		WHERE acc.profile_id = '".CurrentUser::getId()."'";
		if(!empty($id))
			$query .= " AND acc.id = '".$id."'";
		$query .= " ORDER BY acc.account_type_id, acc.name LIMIT ".$count;
		
		//Execute
		$sql->query($query);
		
		//Objects
		$json = array();
		
		//Data
		while($data = mysql_fetch_array($sql->result)):
			
			if($info == "all"):
				$array = array(
					"id"				=>	$data["id"],
					"name"				=>	$data["name"],
					"profile_id"		=>	$data["profile_id"],
					"initial_balance"	=>	$data["initial_balance"],
					"account_type_id"	=>	$data["account_type_id"],
					"balance"			=> 	$data["balance"],
					"status"			=>	$data["status"]
				);
				array_push($json, $array);
			else:
				$json = $data[$info];
			endif;
		endwhile;
		
		//Return
		return $json;
		$sql->close();
	}
	
	/*
	 * ======================================
	 * GET method, types of accounts
	 * ======================================
	 */
	function getTypes($id = NULL){
		//Connect
		$sql = new DataBase;
		$sql->connect();
		if(isset($id)):
			$sql->query("
			SELECT DISTINCT type.*
			FROM account_types type
			WHERE type.id = ".$id."
			ORDER BY type.id
			");
		else:
			$sql->query("
			SELECT DISTINCT type.*
			FROM account_types type
			ORDER BY type.id
			");
		endif;

		//Objects
		$json = array();

		//Data
		while($data = mysql_fetch_assoc($sql->result)):
			$balance = 0;


			$array = array(
				"id"				=>	$data["id"],
				"name"				=>	$data["name"],
				"balance:"			=>	$balance
			);
			array_push($json, $array);
		endwhile;
		return $json;
		$sql->close();
	}
	
	/*
	 * ======================================
	 * GET method, balance of accounts
	 * ======================================
	 */
	function getBalance($account = "", $month = "", $year = "", $orderBy = "", $order = ""){

		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Objects
		$json = array();
		$accounts = $this->get(50,$account);

		//Oder array by YEAR or ACCOUNT
		if($account == "" && ($orderBy == "year" || $orderBy == "month" || $orderBy == "account")):
			
			function fnQuery($account, $month, $year, $orderBy, $order) {
				//Query
				$query = "SELECT amb.year, amb.month, amb.account_id, amb.balance FROM accounts_month_balance amb";
				$query .= " WHERE amb.account_id = '" . $account . "'"; 
				if($month != "") $query .= " AND amb.month = '" . $month . "'";
				if($year != "") $query .= " AND amb.year = '" . $year . "'";
				if($orderBy == "year") $query .= " ORDER BY amb.year " . $order . ", amb.month " . $order . "";
				if($orderBy == "month") $query .= " ORDER BY amb.month " . $order . ", amb.year " . $order . "";
				return $query;
			};

			//Test if have almost 1 line in DB
			function testYearMonth($account, $month, $year, $sql) {
				if($month == "") $month = date("m");
				if($year == "") $year = date("Y");

				$Account = new Accounts;
				$calcBalance = $Account->getForceBalance($account, date('2011-01-01'), date($year . '-' . $month . '-31'));
				$calcBalance = $calcBalance[0]["balance"];
	
				$query2 = "INSERT INTO accounts_month_balance(year,month,account_id,balance)";
				$query2 .= " VALUES('" . $year . "','" . $month . "','" . $account . "','" . $calcBalance . "')";
				return $sql->query($query2);
			};

			//Mount year array
			function yearArrayMount($result, $month, $year){
				//Data
				$arr = array();
				while($data = mysql_fetch_array($result)):

					//Year array
					if(!isset($arr[$data["year"]]))
						$arr[$data["year"]] = array();

					//Year total
					if(!isset($arr[$data["year"]][0]["total"]))
						array_push($arr[$data["year"]], array("total" => $data["balance"]));
					else
						$arr[$data["year"]][0]["total"] += $data["balance"];

					//Month array
					if(!isset($arr[$data["year"]][0][$data["month"]]))
						$arr[$data["year"]][0][$data["month"]] = $data["balance"];
					else
						$arr[$data["year"]][0][$data["month"]] += $data["balance"];
				endwhile;

				return $arr;
			};

			//Order by ACCOUNT
			if($orderBy == "account"):
				foreach($accounts as $acc):
					//Account array
					if(!isset($json[$acc["id"]]))
						$json[$acc["id"]] = array();

					//Years ////////////////////////////////////////////////////////////////////////////////// PEGAR O registerDATE!!!!!!!!!!!!!!!!!!!!!
					if($year == ""):
						$user = new User;
						$userYearInit = (int)"2011";
						$yearDiff = date('Y') - $userYearInit;
						$years = array();
						$months = array(1,2,3,4,5,6,7,8,9,10,11,12);
						for($i = 0; $i <= $yearDiff; $i++):
							array_push($years, date('Y') - $i);
						endfor;
					endif;

					if(isset($years)):
						foreach ($years as $y):
							//Months
							foreach ($months as $m):
								//Execute
								$sql->query(fnQuery($acc["id"], $m, $y, "year", $order));

								//Test
								if(mysql_num_rows($sql->result) == 0):
									testYearMonth($acc["id"], $m, $y, $sql);
								endif;
							endforeach;
						endforeach;
						
					endif;

					//Execute
					$sql->query(fnQuery($acc["id"], $month, $year, "year", $order));

					//Test
					if(mysql_num_rows($sql->result) == 0 && !isset($years)):
						testYearMonth($acc["id"], $month, $year, $sql);
						$sql->query(fnQuery($acc["id"], $month, $year, "year", $order));
					endif;

					$json[$acc["id"]] = yearArrayMount($sql->result,$month, $year);
				endforeach;
	
			//Order by YEAR
			else:
				//Execute
				$sql->query(fnQuery("", $month, $year, $orderBy, $order));

				//Test
				if(mysql_num_rows($sql->result) == 0):
					foreach($accounts as $acc):
						testYearMonth($acc["id"], $month, $year, $sql);
					endforeach;
					$sql->query(fnQuery("", $month, $year, $orderBy, $order));
				endif;

				array_push($json, yearArrayMount($sql->result,$month, $year));
			endif;
		endif;

		//TODO: Select one account

		return $json;
		$sql->close();
	}
	
	/*
	 * ======================================
	 * GET method, FORCE balance of accounts
	 * ======================================
	 */
	function getForceBalance($account = "", $from = "", $to = ""){
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Objects
		$json = array();
		$accounts = $this->get(50,$account);
		
		//Data		
		foreach($accounts as $acc):
			//Query
			$balance = 0;
			$query = "SELECT transaction.amount, transaction.account_to_id FROM transactions transaction";
			$query .= " WHERE (transaction.account_from_id = '".$acc['id']."' OR transaction.account_to_id = '".$acc['id']."')";
			if(!empty($from)) $query .= " AND transaction.date >= '".$from."' ";
			if(!empty($to)) $query .= " AND transaction.date <= '".$to."' ";
			
			//execute
			$sql->query($query);
			
			//Data
			while($data = mysql_fetch_array($sql->result)):
				if($data["account_to_id"] == $acc["id"])
					$balance -= $data["amount"];
				else
				$balance += $data["amount"];
			endwhile;
			
			$balance += $acc["initial_balance"];
			
			//Array
			$acc['balance'] = round($balance, 2);	
			$array = $acc;
			
			array_push($json, $array);
		endforeach;
		
		return $json;
		$sql->close();
	}	
}
?>