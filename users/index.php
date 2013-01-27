<?php
//Get classes
require_once('../api.php');

//Request
$request = RestUtils::processRequest();
$userInfo = new User;
$_DATA = $request->getData();

//Verify Request Method
switch($request->getMethod()) {
	/*
	 * ======================================
	 * GET method
	 * ======================================
	 */
	case 'get':
		echo json_encode($userInfo->getInfo());
	break;

	/*
	 * ======================================
	 * POST method
	 * ======================================
	 */
	case 'post':
		//Set DATA
		$var['dateExploded'] = explode('-',str_replace('/','-',$_DATA['birthday']));
		if(count($var['dateExploded']) == 3)
			$var['date'] = $var['dateExploded'][2].'-'.$var['dateExploded'][1].'-'.$var['dateExploded'][0];
		else
			$var['date'] = '';

		$data = array(
			'name'				=>	trim(convertToUnicode($_DATA['name'])),
			'birthday'			=>	$var['date'],
			'gender'			=>	trim(convertToUnicode($_DATA['gender'])),
			'email'				=>	trim(convertToUnicode($_DATA['email'])),
			'password'			=>	trim(convertToUnicode($_DATA['password'])),
			'newPassword'		=>	trim(convertToUnicode($_DATA['newPassword']))
		);

		//Return
		if($userInfo->updateUser($data))
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
};

?>