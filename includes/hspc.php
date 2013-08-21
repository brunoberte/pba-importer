<?php
//////////
// main application startup file
// - initiates and keeps connection with the HSPc server
// - takes care about the sessions
// - defines constants, settings, language
// included in each and every store page
//
// $Id: hspc.php 888348 2013-06-19 15:00:06Z dkolvakh $
//////////

////
// Re-define some default php parameters to avoid faults
ini_set('memory_limit', '128M');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if(!ini_get('date.timezone')) {
	## set default TZ to avoid PHP warnings
	ini_set('date.timezone', 'Europe/London');
}
// Define error reporting level
error_reporting(E_ALL ^ E_NOTICE);

require_once dirname(__FILE__).	"/vendor/autoload.php";

require_once dirname(__FILE__).	"/Entity/Base.php";
require_once dirname(__FILE__).	"/Entity/Account.php";
require_once dirname(__FILE__).	"/Entity/Domains.php";
require_once dirname(__FILE__).	"/Entity/Main.php";
require_once dirname(__FILE__).	"/Entity/Controller.php";
require_once dirname(__FILE__).	"/Entity/Translate.php";
require_once dirname(__FILE__).	"/Entity/Error.php";
require_once dirname(__FILE__).	"/Entity/Transport.php";
require_once dirname(__FILE__).	"/Entity/StoreRequest.php";

####
## Special global variables
$pbas_transport = null;
$error_handler = null;
$StoreConf = array();

####
## Define configuration parameters
{
	## Fill store configuration with default parameters
	$settings = parse_ini_file(dirname(__FILE__).'/../settings.ini');
	foreach($settings as $key => $value) {
		$StoreConf[$key] = $value;
	}
	## Use defaults for important parameters, if not set
	$default_settings = array(
		'HSPCOMPLETE_SERVER' => 'http://127.0.0.1:8080',
		'SERVER_NAME' => $_SERVER['SERVER_NAME'],
		'STORE_VERSION' => 'DS1',
		'LOOKUP_TLDS_PER_ROW' => 6,
		'DOMAIN_CONTACT_DISPLAY_LENGHT' => 50,
		'DOMAIN_NAME_REDUCE_LENGHT' => 35,
		'DOMAIN_NAME_REDUCE_LENGHT_CONF' => 40,
		'VAT_NUMBER_REQUIRED' => null,
		'CHECK_STATIC_BY_STORE' => 1,
		'COLLAPSED_BLOCKS' => null,
		'CUSTOMIZATION_DIR' => dirname(__FILE__).'/../customization/vendor/%s/',
		'CUSTOM_TMPL_DIR' => dirname(__FILE__).'/../customization/vendor/%s/templates/',
		'CUSTOM_STAT_DIR' => dirname(__FILE__).'/../customization/vendor/%s/static/',
		'CUSTOM_LOC_DIR' => dirname(__FILE__).'/../customization/localization/',
		'TEMPLATE_DIR' => dirname(__FILE__).'/../templates/',
		'LOCALES_DIR' => dirname(__FILE__).'/../i18n/',
		'ROUTES_FILE' => dirname(__FILE__).'/routing.yml',
		'CACHE_DIR' => dirname(__FILE__).'/../cache/',
		'LOG_LOCATION' => '/var/log/hspc/store.log',
	);
	foreach($default_settings as $key => $value) {
		if(!isset($StoreConf[$key])) {
			$StoreConf[$key] = $value;
		}
	}
	## clear temporary variable
	unset($default_settings);
}

####
## Re-define session handler
if(isset($StoreConf['SESSION_SERIALIZE_HANDLER'])) {
	ini_set('session.serialize_handler', $StoreConf['SESSION_SERIALIZE_HANDLER']);
}

## Re-define PHP log settings, to output information to custom file
ini_set('error_log', $StoreConf['LOG_LOCATION']);

define('TECH_DATA', base64_encode($StoreConf['STORE_VERSION'].'||'.$_SERVER['SERVER_NAME'].'||'.$_SERVER['SERVER_ADDR'].'||'.$_SERVER['SCRIPT_FILENAME']));

####
## Initialization
{
	// Get all constants
	include dirname(__FILE__).'/constants.php';

	// Load general functions
	include dirname(__FILE__).'/general_functions.php';

	// Load HSPc replated functions
	include dirname(__FILE__).'/hspc_functions.php';

	// Start or resume session
	session_name('dssid');
	session_set_cookie_params(0, "/", $GLOBALS['StoreConf']['SERVER_NAME'], ($_SERVER['HTTPS'] ? true : false));
	session_start();

	if($_SESSION['SERVER_NAME'] != $GLOBALS['StoreConf']['SERVER_NAME']) {
		session_destroy();
		session_name('dssid');
		session_set_cookie_params(0, "/", $GLOBALS['StoreConf']['SERVER_NAME'], ($_SERVER['HTTPS'] ? true : false));
		session_start();
	}

	get_error_handler();
	get_api_transport();

	## When API session opened, try to apply parameters customized per-vendor
	if(isset($_SESSION['vendor_id']) && file_exists(sprintf($GLOBALS['StoreConf']['CUSTOMIZATION_DIR'], $_SESSION['vendor_id']).'custom.ini')) {
		$settings = parse_ini_file(sprintf($GLOBALS['StoreConf']['CUSTOMIZATION_DIR'], $_SESSION['vendor_id']).'custom.ini');
		foreach($settings as $key => $value) {
			$StoreConf[$key] = $value;
		}
		## Re-define log location per vendor
		ini_set('error_log', $StoreConf['LOG_LOCATION']);
	}

	// Define server protocol and redirect if necessary
	define('SELF_URL',
		($_SERVER['HTTPS'] ? 'https://' : 'http://').
		$_SERVER['SERVER_NAME'].
		$_SERVER['REQUEST_URI']);

	if(strpos(SELF_URL, 'https') === 0) {
		define('SERVER_PROTOCOL', 'https');
		if(!$_SESSION['provider_config']['is_use_ssl']) {
			redirect(preg_replace('/^https/', 'http', SELF_URL));
		}
	} elseif(strpos(SELF_URL, 'http') === 0) {
		define('SERVER_PROTOCOL', 'http');
		if($_SESSION['provider_config']['is_use_ssl']) {
			redirect(preg_replace('/^http/', 'https', SELF_URL));
		}
	}

	// Get campaign info if HSPC_MM_[ACCOUNT_NO] cookie or uri param encountered
	if(($_GET['HSPC_MM'] || $_COOKIE['HSPC_MM']) && (!$_SESSION['digest'])) {
		$_SESSION['digest'] = ($_GET['HSPC_MM']) ? $_GET['HSPC_MM'] : $_COOKIE['HSPC_MM'];
		$_SESSION['campaign'] = call('get_campaign', array('digest' => $_SESSION['digest']), 'HSPC/API/Campaign');
		if($_SESSION['campaign']['promo_id']) {
			$_SESSION['campaign']['promotion'] = call('get_promotion',
				array('promo_id' => $_SESSION['campaign']['promo_id']),
				'HSPC/API/HP'
			);
		}
	}

	// Login person automatically if he came here from PCC/RCC/CP
	if($_GET['sid']) {
		## Remember parameters from URL, for further navigation
		$_SESSION['CP_GET'] = $_GET;
		unset($_SESSION['account'], $_SESSION['person'], $_SESSION['is_authorized']);
		$result = call('auth_person',
			array('email'		=> null,
				'password'		=> null,
				'ip'			=> $_SERVER['REMOTE_ADDR'],
				'sid'			=> $_GET['sid'],
				'login_to_cp'	=> 0
			),
			'HSPC/API/Person'
		);
		if(isset($result['person'])) {
			$_SESSION['person'] = $result['person'];
			foreach($_SESSION['person']['account_list'] AS $key => $value) {
				if(preg_match('/'.$value['type'].'/', '2,3')) {
					$_SESSION['account'] = $value;
					break;
				}
			}
		}
		if(isset($_SESSION['account'])) {
			$_SESSION['is_authorized'] = true;
		}
		if(isset($_SESSION['is_authorized'])) {
			$_SESSION['sid'] = $_GET['sid'];
			if(!array_key_exists('credentials', $_SESSION) || !is_array($_SESSION['credentials'])) {
				$_SESSION['credentials'] = array();
				$_SESSION['credentials']['password_source'] = 'enter_new';
			}
			load_domain_contacts();
			get_domain_list();
			// Redirect to the same URL without sid to make the url nice, unless there's any post params
			if (!$_POST) {
				$url = preg_replace('/sid='.$_GET['sid'].'&?/', '', SELF_URL);
				$url = preg_replace('/[\?|\&]$/', '', $url);
				## Remove unneeded part of URL
				$url = preg_replace('/index\.php/', '', $url);
				redirect($url);
			}
		}
	}

	if(!array_key_exists('vendor', $_SESSION) || !is_array($_SESSION['vendor'])) {
		$_SESSION['vendor'] = get_account_info();
		get_domain_list();
	}

	$collapsed_blocks = array();
	if(isset($GLOBALS['StoreConf']['COLLAPSED_BLOCKS']) && $GLOBALS['StoreConf']['COLLAPSED_BLOCKS']) {
		$per_plan = explode(';', $GLOBALS['StoreConf']['COLLAPSED_BLOCKS']);
		if(is_array($per_plan) && count($per_plan)) {
			foreach($per_plan as $item) {
				$item = trim($item);
				$items = explode(',', $item);
				if(
					is_array($items) && count($items) &&
					in_array($items[0],
						array(
							HP_TYPE_VPS, HP_TYPE_DEDICATED_SERVER, HP_TYPE_VIRTUOZZO_DEDICATED_NODE, HP_TYPE_DOMAIN_REGISTRATION,
							HP_TYPE_MISC, HP_TYPE_PLESK_DEDICATED_NODE, HP_TYPE_PLESK_DOMAIN, HP_TYPE_PLESK_CLIENT, HP_TYPE_PLESK_VIRTUAL_NODE,
							HP_TYPE_SSL_SINGLE, HP_TYPE_ONETIME_FEE_ITEM, HP_TYPE_PSVM, HP_TYPE_POA
						)
					)
				) {
					$hp_type = array_shift($items);
					$collapsed_blocks[$hp_type] = array_values($items);
				}
			}
		}
	}
}