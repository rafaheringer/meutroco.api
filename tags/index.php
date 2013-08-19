<?php
//Get classes
require_once('../api.php');

//Request
$request = RestUtils::processRequest();
$tags = new Tags;
$_DATA = $request->getData();


//Verify Request Method
switch($request->getMethod()):
	/*
	 * ======================================
	 * GET method
	 * ======================================
	 */
	case 'get':
		//Get by id
		if(!empty($_DATA['id'])):
			echo json_encode($tags->getUnique($_DATA['id']));
			exit;
			
		//Get by transaction
		elseif(!empty($_DATA['transaction'])):
			isset($_DATA['count']) ? $count = intval($_DATA['count']) : $count = 500;
			isset($_DATA['orderby']) ? $orderby = $_DATA['orderby'] : $orderby = 'id';
			isset($_DATA['order']) ? $order = $_DATA['order'] : $order = 'asc';
			echo json_encode($tags->getTransactionTags($count, $_DATA['transaction'], $orderby, $order));
			exit;
			
		//Get all tags
		else:
			isset($_DATA['count']) ? $count = intval($_DATA['count']) : $count = 500;
			isset($_DATA['orderby']) ? $orderby = $_DATA['orderby'] : $orderby = 'name';
			isset($_DATA['order']) ? $order = $_DATA['order'] : $order = 'asc';
			isset($_DATA['from']) ? $from = $_DATA['from'] : $from = "";
			isset($_DATA['to']) ? $to = $_DATA['to'] : $to = "";
			echo json_encode($tags->get($count, $orderby, $order, $from, $to));
			exit;
		endif;
		
	break;
	
	/*
	 * ======================================
	 * PUT method
	 * ======================================
	 */
	case 'put':
		//Requisições
		$_DATA = $request->getRequestVars();

		//Informações cadastradas
		$data = array(
			'tagName'				=>	trim($_DATA['tagName'])
		);

		//Instâncias
		$tags = new Tags;

		//Verificar inputs básicos
		if($data['tagName'] == '' || count($data['tagName']) > 28): //Nome da tag
			RestUtils::sendResponse('406',array('data' => 'tagName', 'message' => 'Por favor, verifique o nome da tag.'));
		endif;

		//Verifica se a tag já não existe
		$allTagsCompare = array();
		foreach($tags->get(500) as $tag):
			$tag = strtolower(clearUTF($tag['name']));
			array_push($allTagsCompare, $tag);
		endforeach;

		if(in_array(prepareToCompare($data['tagName']), $allTagsCompare)): //Se a tag já existe
			RestUtils::sendResponse('406',array('data' => 'tagName', 'message' => 'A tag a ser cadastrada já existe.'));
		endif;

		//Conexão
		$sql = new DataBase;
		$sql->connect();

		//Adiciona a tag no banco
		$sql->query("INSERT INTO tags(name, profile_id) VALUES ('".ucfirst(trim(convertToUnicode($data['tagName'])))."','".CurrentUser::getId()."')");

		//Termina execução com sucesso
		$sql->close();
		RestUtils::sendResponse('201');

	break;

	/*
	 * ======================================
	 * POST method
	 * ======================================
	 */
	case 'post':
		//Set variables
		$data = array(
			'name'				=>	trim(convertToUnicode($_DATA['tagName'])),
			'id'				=>	trim($_DATA['id'])
		);
		
		//Verify if exists
		$tr = $tags->getUnique($data['id']);
		if(count($tr) == 0)
			RestUtils::sendResponse('406',array('data' => 'tagId', 'message' => 'Essa tag n&atilde;o existe.'));
		if($tr[0]['profile_id'] != CurrentUser::getId())
			RestUtils::sendResponse('406',array('data' => 'tagId', 'message' => 'Essa tag n&atilde;o pertence ao perfil.'));
		
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Update 
		$sql->query("
			UPDATE tags
			SET name='".$data['name']."'
			WHERE id = '".$data['id']."'
		");
		
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
		$ID = $_DATA['id'];
		
		//Connect
		$sql = new DataBase;
		$sql->connect();
		
		//Verify if exists
		$tr = $tags->getUnique($ID);
		if(count($tr) == 0)
			RestUtils::sendResponse('406',array('data' => 'tagId', 'message' => 'Essa tag n&atilde;o existe.'));
		if($tr[0]['profile_id'] != CurrentUser::getId())
			RestUtils::sendResponse('406',array('data' => 'tagId', 'message' => 'Essa tag n&atilde;o pertence ao perfil.'));
		
		//Remove
		$sql->query("DELETE FROM transactions_has_tags WHERE tag_id = '".$ID."'");
		$sql->query("DELETE FROM tags WHERE id = '".$ID."'");
		
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