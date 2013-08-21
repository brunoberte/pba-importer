<?php
//////////
// not related to HSPc common functions
//
// $Id: general_functions.php 888348 2013-06-19 15:00:06Z dkolvakh $
//////////

## get param from GET or POST arrays
## TODO remove, mostly obsoleted
function param($key = null) {
	if(array_key_exists($key, $_GET)) {
		return strip_tags( urldecode($_GET[$key]) );
	} else if(array_key_exists($key, $_POST)) {
		$var = $_POST[$key];
		if(is_array($var)) {
			foreach($var as &$value) {
				$value = strip_tags($value);
			}
		} else {
			$var = strip_tags($var);
		}
		return $var;
	}
	return null;
}


## get param from preselected cache or from GET or POST
## TODO remove, mostly obsoleted
function ps_param($key = null, $step = null) {
	if(array_key_exists('ps', $_SESSION) && is_array($_SESSION['ps'])) {
		if($step) {
			if(is_array($_SESSION['ps'][$step]) && array_key_exists($key, $_SESSION['ps'][$step]) && isset($_SESSION['ps'][$step][$key])) {
				return ($_SESSION['ps'][$step][$key]);
			}
		} else {
			if(array_key_exists($key, $_SESSION['ps']) && isset($_SESSION['ps'][$key])) {
				return ($_SESSION['ps'][$key]);
			}
		}
	}
	return param($key);
}


////
// Stop from parsing any further PHP code
function _exit() {
	session_write_close();
	exit();
}


////
// Redirect to another page
function redirect($url) {
	error_log('Redirecting to: '.$url);
	header('Location: ' . $url);
	_exit();
}

////
// Decode to utf8 if necessary (by default, localization files are shipped in UTF-8 format, so no decoding needed)
function string($str) {
	if(ENCODING != 'utf8') {
		return iconv(ENCODING, 'utf8', $str);
	} else {	
		return $str;
	}
}


////
// Format price to show according to provider's configs
function format_price($price, $currency_minor = null) {
	$sign = '';
	if(!$currency_minor) {
		$currency_minor = $_SESSION['provider_config']['currency']['currency_minor'];
	}
	
	if ($price < 0) {
		$sign = '-';
		$price = $price * -1;
	}

	$currency = explode(';', $_SESSION['provider_config']['currency']['currency_sign_code']);

	$currency_sign = '';

	foreach ($currency AS $ord) {
		$currency_sign .= chr($ord);
	}

	$formated_price = str_replace('.',
		$_SESSION['provider_config']['currency']['currency_radix'],
		sprintf("%.".$currency_minor."f", round($price, $currency_minor))
	);

	if($currency_minor > $_SESSION['provider_config']['currency']['currency_minor']) {
		$parts = explode($_SESSION['provider_config']['currency']['currency_radix'], $formated_price);
		if(count($parts) > 1) {
			$odd_minor = $currency_minor - $_SESSION['provider_config']['currency']['currency_minor'];
			$parts[1] = preg_replace('/0{1,'.$odd_minor.'}$/', '', "".$parts[1]."");
			$formated_price = implode($_SESSION['provider_config']['currency']['currency_radix'], $parts);
		} 
	}
	if ($_SESSION['provider_config']['currency']['currency_alignment'] == 1) {
		$formated_price = $sign.$currency_sign.$formated_price;
	} else {
		$formated_price = $sign.$formated_price.$currency_sign;
	}
	return $formated_price;
}

////
// Format percent discount to show according to provider's configs
function format_discount($percents) {
	return str_replace(
		'.', 
		$_SESSION['provider_config']['currency']['currency_radix'], 
		sprintf ("%6.2f", round($percents, 2)).' %'
	);
}

////
// Format time period to show in human-friendly format
function format_period($time_items, $translator) {
	$time_items = abs($time_items);
	if ($time_items < 60) {
		if($time_items == 1) {
			return $time_items . ' ' . $translator->trans('YEAR');
		} else {
			return $time_items . ' ' . $translator->trans('YEAR_S');
		}
	} elseif ($time_items >= 60 && $time_items < 3600) {
		return round($time_items/60) . ' ' . $translator->trans('MINUTE_S');
	} elseif ($time_items >= 3600 && $time_items < 86400) {
		return round($time_items/3600) . ' ' . $translator->trans('HOUR_S');
	} elseif ($time_items >= 86400 && $time_items < 2592000) {
		return round($time_items/86400) . ' ' . $translator->trans('DAY_S');
	} elseif ($time_items >= 2592000 && $time_items < 31104000) {
		return
			round($time_items/2592000, 2) . ' ' . (
			$time_items/2592000 > 1
			? $translator->trans('MONTHS')
			: $translator->trans('MONTH')
		);
	} else {
		return
			round($time_items/31104000, 2) . ' ' . (
			$time_items/31104000 > 1
			? $translator->trans('YEAR_S')
			: $translator->trans('YEAR')
		);
	}
}

////
// Generate short hostname
// used to generate subdomain from account name
function generate_hostname($source) {
	$hostname = preg_replace('/[^\w]/', '', $source);
	$hostname = strtolower($hostname);
	return $hostname;
}

////
// Escape domain name to be used in js vars
function escape_dm($domain_name) {
	return str_replace(array('.','-'), '____',$domain_name);
}


////
// Escape license product ID to be used in js vars
function escape_license_product($name = '') {
	return str_replace('-', '__', $name);
}

####
## Detect XSS attempt
function xss_safe($string) {
	if(preg_match('/[\/\\\<\>\"\'\%\;\)\(\&\+\s\,]+/', $string)) {
		return 0;
	} else {
		return 1;
	}
}

////
// Dump Variable
function dumper($mixed = null) {
	ob_start();
	print_r($mixed);
	$content = ob_get_contents();
	ob_end_clean();
	return clear_sensitive_data($content);
}

####
## wipe out sensitive data
function clear_sensitive_data($str) {
	$str = preg_replace('/(\[.*password\]\s+=>\s+)[^\s]+/', '$1XXXXXX,', $str);
	$str = preg_replace('/(\[login\]\s+=>\s+Array\s+\(\s+\[0\]\s+=>\s+)[^\s]+/', '$1XXXXXX,', $str);
	$str = preg_replace('/(\'.*password\,)[^\s]+/', '$1XXXXXX\'', $str);
	$str = preg_replace('/(\[sid\]\s+=>\s+)[^\s]+/', '$1XXXXXX,', $str);
	return $str;
}

####
## Determine caller, used in Entity\Error and in sw_log_* 
function get_backtrace($level) {
	$level++;	## itself
	$bt = debug_backtrace();
	$method = $bt[$level]['function'] ? '::'.$bt[$level]['function'] : '';
	$class = '';
	if(is_array($bt[$level]) && array_key_exists('class', $bt[$level])) {
		$class = $bt[$level]['class'];
		$class = str_replace('\\', '::', $class);
		if(isset($bt[$level-1]['line'])) {
			$method .= ', line '.$bt[$level-1]['line'];
		}
	} else {
		$class = $bt[$level-1]['file'];
		$class = preg_replace('/^.*\/([^\/]+)$/', '\1', $class);
		if(preg_match('/Controller\.php.*eval/', $class) && isset($bt[$level+1]) && isset($bt[$level+1]['args'])) {
			$method = '';
			$class = $bt[$level+1]['args'][0];
			$class = preg_replace('/^.*\:([^\:]+)$/', '\1', $class);
		}
		if(isset($bt[$level-1]['line'])) {
			$method .= ', line '.$bt[$level-1]['line'];
		}
	}
	$method = $class.$method;
	return $method;	
}

####
## Evoke built-in error_log with caller name trace, should not be called directly, use wrappers instead
function __sw_log($msg = null, $severity = null) {
	error_log('['.$severity.'] ['.$_SERVER['REMOTE_ADDR'].'] ['.get_backtrace(2).'] '.clear_sensitive_data($msg));	## str_repeat('+', $level).'> '.   ## ['.$level.']
}

####
## Different debug level wrappers
function sw_log_debug($msg = null) {
	if($GLOBALS['StoreConf']['DEBUG_MODE']) {
		__sw_log($msg, 'DEBUG');
	}
}
function sw_log($msg = null) {
	__sw_log($msg, 'INFO');
}
function sw_log_warn($msg = null) {
	__sw_log($msg, 'WARN');
}
function sw_log_error($msg = null) {
	__sw_log($msg, 'ERROR');
}

####
## Trim repeating spaces, trim to specified length
function compact_string($str = '', $len = 7900) {
	$str = preg_replace('/\s+/is', ' ', $str);
	if(strlen($str) <= $len) return $str;
	if(strlen($str) > $len) return substr($str, 0, $len);
}

####
## Reduce string to specified length
function reduce_string($str = '', $length = null) {
	if(!isset($length) || !$length) {
		$length = $GLOBALS['StoreConf']['DOMAIN_NAME_REDUCE_LENGHT'];
	}
	if($length > 5 && strlen($str) > $length) {
		$str = substr($str, 0, (int)($length/2-2)).'...'.substr($str, -(int)($length/2-1));
	}
	return $str;
}

####
## Show error page with given message
function error($message = null) {
	header("Content-type: text/html; charset=UTF-8");
	## Temporary solution. It's better to use Symfony exception handler.
	include('error.html.php');
	_exit();
}

####
## encoding-safe lowercase given string 
function lc($string = null) {
	if(ini_get('mbstring.language')) {
		return(mb_strtolower($string, 'UTF-8'));
	} else {
		return(strtolower($string));
	}
}
