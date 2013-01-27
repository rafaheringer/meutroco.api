<?php
/*
 * ======================================
 * TOKEN
 * ======================================
 */
class Token {
	public $type;
	public $token;
	public $val;

	/* Gera um novo token Token */
	public function generate($length = 8) {
		$first_half_length = floor($length / 2);
		$second_half_length = ($length - $first_half_length);
		$key = rand(1, 999);
		$result = '';
		for ($i = 0; $i < $first_half_length; $i++) {
			$result .= $key;
		}
		for ( $i = 0; $i < $second_half_length; $i++) {
			$result .= ($key + 1);
		}
		
		return $result;
	}
	
	/* Verifica autenticidade do token Token */
	public function verify($str) {
		$length = strlen($str);
		if ((!is_int($str)) || ($length < 2))
			return false;
		
		$full_string = str_split($str);
		$first_half_length = floor($length / 2);
		$first_half = array_slice($full_string, 0, $first_half_length);
		$second_half = array_slice($full_string, $first_half_length);
		
		$first_char = $first_half[0];
		foreach ($first_half as $k => $char) {
			if ($char != $first_char) {
				return false;
			}
		}
		
		$first_char++;
		foreach ($second_half as $k => $char) {
			if ($char != $first_char) {
					return false;
			}
		}
		
		return true;
	}
}
?>