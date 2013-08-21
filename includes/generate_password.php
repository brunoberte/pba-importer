<?php
//////////
// random password generation class
//
// $Id: generate_password.php 850324 2013-03-01 15:20:19Z dkolvakh $
//////////

/**
 * generate random password
 *
 */
class GeneratePassword
{

/**
 *
 * @param integer $number_of_chars
 * @param integer $include_number,
 * 1 - include numbers , 0 - do not include numbers
 * @param integer $include_lower_letter,
 * 1 - include lower letter, 0 - do not include lower letter
 * @param integer $include_upper_letter,
 * 1 - include upper letter, 0 - do not include upper letter
 * @return string
 */
function Generate(
$number_of_chars = 12, $include_number = 1,
$include_lower_letter=1, $include_upper_letter=1 )
{

	//init password
	$pswd = "";

	$last_character = '';

	//while password length < number of characters
	while( strlen( $pswd ) < $number_of_chars )
	{
		//seed the random number generator
		srand( $this->makeSeed() );

		$ch_type = (rand() % 2);

		$character = '';

		//if ch_type == 0 generate number
		if( $ch_type == 0 && $include_number == 1  )
		{
			$character = $this->generateNumber();
		}
		//generate letter
		else
		{
			mt_srand( $this->makeSeed() );
			$letter_type = mt_rand( 0,9);
			$letter_type = $letter_type % 2;

			//generate upper case letter
			if(
				( $letter_type == 0 &&
				$include_upper_letter == 1 )
				||
				( $include_lower_letter == 0 &&
				$include_upper_letter == 1 )
			)
			{
				$character = $this->generateLetter( 0 );
			}

			//generate lower case letter
			if( ( $letter_type == 1
			&& $include_lower_letter == 1 )||
			( $include_lower_letter == 1
			&& $include_upper_letter == 0 ) )
			{
				$character = $this->generateLetter( 1 );
			}

			//include number = 0,
			//include upper = 0, include lower = 0
			if( $character == ''
			&& $include_number == 0 )
			{
			  $character = $this->generateLetter( $letter_type );
			}
		}

		if( $character != '' )
		{
			$pswd .= $character;
		}

	}

	return $pswd;

}
//end generatePassword method

/**
 * generate number
 *
 * @return character 0-9
 */
function generateNumber()
{
	mt_srand( $this->makeSeed() );
	$character = mt_rand( 0,9);

	return $character;
}
//end generateNumber method


/**
 * generate lower or upper case letter
 *
 * @param integer_type $letter_type,
 * 0 - upper case, 1 - lower case, 2 - random
 * @return character a-zA-Z
 */
function generateLetter( $letter_type = '2')
{
	//make seed
	mt_srand( $this->makeSeed() );

	//if letter_type == '2', either lower case or upper case
	if( $letter_type == '2' )
	{
		$letter_type = mt_rand( 0,9);
		$letter_type = $letter_type % 2;
	}

	//generate lower case letter
	if( $letter_type == 1 )
	{
		mt_srand( $this->makeSeed());
		$character = mt_rand( 97,122);
	}

	//generate upper case letter
	if( $letter_type == 0 )
	{
		mt_srand( $this->makeSeed());
		$character = mt_rand( 65,90);
	}

	$character = chr( $character );

	return $character;
}
//end generateLetter method

/**
 * makeSeed
 *
 * @return float
 */
function makeSeed()
{
	list($usec, $sec) = explode(' ', microtime());
	return (float) $sec + ((float) $usec * 100000);
}
//end makeSeed method

}

?>