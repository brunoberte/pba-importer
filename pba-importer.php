<?php
/*
Plugin Name: PBA Importer
aaPlugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Plugin para importação de planos de hospedagem via Xml APi do Parallels Business Automation Standard (PBA-S)
Version: 0.4
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

