<?php
/*
Plugin Name: PBA Importer
Plugin URI: https://github.com/brunoberte/pba-importer
Description: Plugin para importação de planos de hospedagem via Xml APi do Parallels Business Automation Standard (PBA-S)
Version: 0.4.1
Author: Bruno S. Berté
Author URI: http://about.me/brunoberte
License: GPL2
*/

$GLOBALS['StoreConf']['DEBUG_MODE'] = false;

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if (isset($PbaImporter)) return false;

require_once(dirname(__FILE__) . '/pba-importer.class.php');
require_once(dirname(__FILE__) . '/hspc_api.class.php');

$PbaImporter = new PbaImporter();




require_once(dirname(__FILE__) . '/includes/__updater.php');

if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin
    $config = array(
        'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
        'proper_folder_name' => 'pba-importer', // this is the name of the folder your plugin lives in
        'api_url' => 'https://api.github.com/repos/brunoberte/pba-importer', // the github API url of your github repo
        'raw_url' => 'https://raw.github.com/brunoberte/pba-importer/master', // the github raw url of your github repo
        'github_url' => 'https://github.com/brunoberte/pba-importer', // the github url of your github repo
        'zip_url' => 'https://github.com/brunoberte/pba-importer/zipball/master', // the zip url of the github repo
        'sslverify' => true, // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
        'requires' => '3.0', // which version of WordPress does your plugin require?
        'tested' => '3.5', // which version of WordPress is your plugin tested up to?
        'readme' => 'README.md', // which file to use as the readme for the version number
        'access_token' => '', // Access private repositories by authorizing under Appearance > Github Updates when this example plugin is installed
    );
    new WP_GitHub_Updater($config);
}
