<?php
//////////
// Localization methods 
//
// $Id: Translate.php 888348 2013-06-19 15:00:06Z dkolvakh $
//////////

namespace Entity;

use Symfony\Component\Config\ConfigCache;

class Translate {

	private $strings;
	private $locale;
	private $path;
	private $cache_path;

	function __construct($locale = "en") {
		$this->locale     = strtoupper($locale);
		$this->path       = $GLOBALS['StoreConf']['LOCALES_DIR'];
		$this->cache_path = $GLOBALS['StoreConf']['CACHE_DIR'];
		$this->load_lang();
	}

	public function get($str, $params) {
		return strtr ($this->strings[$str], $params);
	}

	private function get_locale_files() {
		$lang = $this->locale;
		$dir = $this->path.$lang;
		$files = array();

		if(!is_dir($dir)) {
			sw_log_error("Directory $dir not exist, can't get locale files.");
			return $files;
		}

		foreach(scandir("$dir") as $ent) {
			if(preg_match("/\.xml$/", $ent)) {
				array_push($files, $dir.'/'.$ent);
			}
		}

		$cust_dir = $GLOBALS['StoreConf']['CUSTOM_LOC_DIR'].$lang;
		if(is_dir($cust_dir)) {
			foreach(scandir($cust_dir) as $ent) {
				if(preg_match( "/\.xml$/", $ent)) {
					array_push($files, $cust_dir.'/'.$ent);
				}
			}
		}
		
		return $files;
	}

	private function calculate_md5_files($files) {
		foreach($files as $file) { 
			$md5 .= md5_file($file);
		}
		return md5($md5);
	}

	private function md5check_needed() {
		return 1;
	}

	private function reparse_xml($files) {
		$lang = $this->locale;
		$this->strings = array();

		foreach($files as $file) {
			$xml = simplexml_load_file($file);
			foreach($xml as $key0 => $value) {
				$this->strings["$value->id"] = "$value->val";
			}
		}

		if(count($this->strings) == 0) {
			return null;
		}

		$md5 = $this->calculate_md5_files($files);

		$str = "<?php\n";
		$str .= '$md5 = ' . var_export($md5,1) . ";\n";
		$str .= '$strings = ';
		$str .= var_export($this->strings,1);
		$str .= "?>\n";
		return $str;
	}

	private function load_lang() {
		$lang = $this->locale;

		$cache = new ConfigCache($this->cache_path.$lang.'.php', false);
		
		if(!$cache->isFresh()) {
			$cache_data = $this->reparse_xml($this->get_locale_files());

			if($cache_data && is_writable($this->cache_path)) {
				$cache->write($cache_data);
			}
		} else {
			require_once $cache;
			$this->strings = $strings;

			if($this->md5check_needed()) {
				$files = $this->get_locale_files();
				$my_md5 = $this->calculate_md5_files($files);
				if($md5 != $my_md5) {
					$cache_data = $this->reparse_xml($files);
					if($cache_data && is_writable($this->cache_path)) {
						$cache->write($cache_data);
					}
				}
			}
		}
		## define special constant, to use as shortcut in templates
		## in FR locale, f.e., should be written with extra space as 'something : anything'
		define('COLON', isset($this->strings['COLON']) ? $this->strings['COLON'] : ':');
	}
}

?>