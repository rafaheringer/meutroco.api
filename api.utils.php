<?php

/* ***** Replace chars ***** */
function replaceSpecialChars($t){	
	$array1 = array("&aacute;", "&agrave;", "&acirc;", "&atilde;", "&auml;", "&eacute;", "&egrave;", "&ecirc;", "&euml;", "í", "ì", "î", "ï", "ó", "ò", "ô", "õ", "ö", "ú", "ù", "û", "ü", "&ccedil;", "Á", "À", "Â", "Ã", "Ä", "É", "È", "Ê", "Ë", "Í", "Ì", "Î", "Ï", "Ó", "Ò", "Ô", "Õ", "Ö", "Ú", "Ù", "Û", "Ü", "&Ccedil;", "&ccedil;");
	$array2 = array("a", "a", "a", "a", "a", "e", "e", "e", "e", "i", "i", "i", "i", "o", "o", "o", "o", "o", "u", "u", "u", "u", "c", "A", "A", "A", "A", "A", "E", "E", "E", "E", "I", "I", "I", "I", "O", "O", "O", "O", "O", "U", "U", "U", "U", "C", "c");
	return str_replace($array1, $array2, $t);
}

/* ***** Convert to Unicode ***** */
function convertToUnicode($t){	
	$array1 = array("á","à","â","ã","ä","é","è","ê","ë","í","ì","î","ï","ó","ò","ô","õ","ö","ú","ù","û","ü","ç","Á","À","Â","Ã","Ä","É","È","Ê","Ë","Í","Î","Î","Ï","Ó","Ò","Ô","Õ","Ö","Ú","Ù","Û","Ü","Ç");
	$array2 = array("&aacute;","&agrave;","&acirc;","&atilde;","&auml;","&eacute;","&egrave;","&ecirc;","&euml;","&iacute;","&igrave;","&icirc;","&iuml;","&oacute;","&ograve;","&ocirc;","&otilde;","&ouml;","&uacute;","&ugrave;","&ucirc;","&uuml;","&ccedil;","&Aacute;","&Agrave;","&Acirc;","&Atilde;","&Auml;","&Eacute;","&Egrave;","&Ecirc;","&Euml;","&Iacute;","&Igrave;","&Icirc;","&Ograve;","&Ocirc;","&Otilde;","&Ouml;","&Uacute;","&Ugrave;","&Ucirc;","&Uuml;","&Ccedil;");
	return str_replace($array1, $array2, $t);
}

/* ***** Clear UTF ***** */
function clearUTF($t) {
	return replaceSpecialChars(convertToUnicode($t));
}

/* ***** toLower ***** */
function toLower($t) {
	return strtr(strtolower($t),"ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÜÚÞß","àáâãäåæçèéêëìíîïðñòóôõö÷øùüúþÿ");
}

/* ***** Generate ID ***** */
function generateId(){
	return uniqid();
}

/* ***** Sort Array ***** */
function array_sort($array, $on, $order=SORT_ASC) {
	$new_array = array();
	$sortable_array = array();

	if (count($array) > 0):
		 foreach ($array as $k => $v)
			 if (is_array($v))
				 foreach ($v as $k2 => $v2)
					 if ($k2 == $on) $sortable_array[$k] = $v2;
			 else $sortable_array[$k] = $v;
		switch ($order):
			 case SORT_ASC:
				 asort($sortable_array);
			 break;
			 case SORT_DESC:
				 arsort($sortable_array);
			 break;
		 endswitch;
		foreach ($sortable_array as $k => $v)
			 $new_array[$k] = $array[$k];
	 endif;
	
	return $new_array;
};

/* ***** Prepare to compare ***** */
function prepareToCompare($term) {
	return strtolower(clearUTF(trim($term)));
}
  
?>