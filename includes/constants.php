<?php
//////////
// different PBAS constants are gathered here
// do not modify this file, unless you know what you are doing
//
// $Id: constants.php 891128 2013-06-27 11:04:52Z dkolvakh $
//////////


## Current API Version
define('CURRENT_API_VERSION', '1.0');

## Minimal billing period, 30 days
define('BILL_PERIOD', 2592000);

## Hosting plan types
define('HP_TYPE_VPS', 1);						// Virtuozzo VPS
define('HP_TYPE_DEDICATED_SERVER', 3);			// Dedicated Server
define('HP_TYPE_VIRTUOZZO_DEDICATED_NODE', 4);	// Virtuozzo Dedicated Node
define('HP_TYPE_DOMAIN_REGISTRATION', 6);		// Domain Registration
define('HP_TYPE_MISC', 7);						// Miscellaneous 
define('HP_TYPE_PLESK_DEDICATED_NODE', 8);		// Plesk Dedicated Node
define('HP_TYPE_PLESK_DOMAIN', 9);				// Plesk Domain
define('HP_TYPE_PLESK_CLIENT', 10);				// Plesk Client
define('HP_TYPE_PLESK_VIRTUAL_NODE', 11);		// Plesk Virtual Node
define('HP_TYPE_SSL_SINGLE', 12);				// SSL Certificate
define('HP_TYPE_ONETIME_FEE_ITEM', 13);			// One time fee item
define('HP_TYPE_PSVM', 14);						// Virtual Machines
define('HP_TYPE_POA', 16);						// Operations Automation

## Promotion types
define('PROMOTION_DEFAULT', 1);
define('PROMOTION_COUPON_CODE', 2);
define('PROMOTION_AGREEMENT', 3);

## Account types
define('ACCOUNT_TYPE_CUSTOMER', '3');
define('ACCOUNT_TYPE_RESELLER', '2');

## Payment constants
## Payment transaction status
define('PP_PROC_DECLINED',	1);
define('PP_PROC_APPROVED',	2);
define('PP_PROC_POSTPONED', 3);
define('PP_PROC_UNKNOWN',	4);
define('PP_PROC_3DSECURE',	5);

## QOS names
define('VE_HOST_LIMIT', 'numwebsites');			## Number of websites for VE 
define('PC_HOST_LIMIT', 'pc_numdomains');		## Number of domains for Plesk Client Unix / Windows

## Applications
define('APP_WACP', 3);							## Workgroup Administrator Control Panel

## On-screen Message Classes
define('MC_ERROR', 'error');
define('MC_WARN', 'warning');
define('MC_SUCCESS', 'success');
define('MC_HINT', 'hint');
define('MC_INTERR', 'internal_error');
## Divider, used for on-screen messages concatenation
define('MC_DIVIDER', '<br />');

## Store Blocks Classes
define('HP_BLOCK_GEN_INFO', 'gen_info');
define('HP_BLOCK_DOMAINS', 'domains');
define('HP_BLOCK_CONFIG', 'config');
define('HP_BLOCK_ATTR', 'attr');
define('HP_BLOCK_AUTH', 'auth');
define('HP_BLOCK_APP', 'app');
define('HP_BLOCK_LIC', 'lic');
define('HP_BLOCK_QOS', 'qos');
