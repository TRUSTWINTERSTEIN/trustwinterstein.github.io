<?php
/**
 * @package VidSrc DooPlay
 * @version 1.0
 */
/*
Plugin Name: VidSrc DooPlay
Plugin URI: https://vidsrc.me
Description: Automating DooPlay to add and update latest streams From VidSrc.me.
Author: VidSrc
Version: 1.0
Author URI: https://vidsrc.me
*/

// GLOBALS START

$vidsrc_mov_file = dirname(__FILE__)."/data/vidsrc_mov.txt";
$vidsrc_eps_file = dirname(__FILE__)."/data/vidsrc_eps.txt";

$indb_mov_file = dirname(__FILE__)."/data/indb_mov.txt";
$indb_eps_file = dirname(__FILE__)."/data/indb_eps.txt";

$movies_add_limit;
$movies_not_added;
$movies_add_count;


$eps_not_added;

$ep_add_limit;
$ep_add_count;

// GLOBALS END

// INC START

include_once(dirname(__FILE__)."/inc/init.php");
include_once(dirname(__FILE__)."/inc/page_settings.php");
include_once(dirname(__FILE__)."/inc/cron_mov.php");
include_once(dirname(__FILE__)."/inc/cron_tv.php");
include_once(dirname(__FILE__)."/inc/cron_clean.php");
include_once(dirname(__FILE__)."/inc/cron_data.php");
include_once(dirname(__FILE__)."/inc/func.php");

// INC END



