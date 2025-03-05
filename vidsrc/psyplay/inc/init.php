<?php


register_activation_hook( __FILE__, 'vidsrc_install' );
register_deactivation_hook( __FILE__, 'vidsrc_unistall' );

register_activation_hook(__FILE__, 'vidsrc_cronjob_activation');
register_deactivation_hook(__FILE__, 'vidsrc_cronjob_deactivation');


add_action('vidsrc_add_data_event', 'vidsrc_add_data');
add_action('vidsrc_add_data_indb_event', 'vidsrc_add_data_indb');
add_action('vidsrc_movies_event', 'vidsrc_do_action_for_movies');
add_action('vidsrc_tvshow_event', 'vidsrc_do_action_for_tvshows');
add_action('vidsrc_clean_duplicates_event', 'vidsrc_clean_dupl');
add_action('vidsrc_update_player_color_event', 'updatePlayerColor');
add_action('vidsrc_clean_dead_titles_event', 'vidsrc_clean_dead_titles');
add_action('vidsrc_ping_active_event', 'pingVidsrc');

if ( is_admin() ){
	$vidsrc_plugin = plugin_basename(__FILE__); 
	add_action( 'admin_init', 'vidsrc_register_mysettings' );
	add_filter("plugin_action_links", 'vidsrc_settings_link' );
	add_action( 'admin_menu', 'vidsrc_adminmenu' );
}

add_filter('cron_schedules','vidsrc_custom_cron_schedules');




function vidsrc_install() {
	update_option("vidsrc_active",'');
	update_option("vidsrc_cron",'off');
	update_option("vidsrc_movies_no",'10');
	update_option("vidsrc_episodes_no",'50');
	update_option("vidsrc_pluginversion",'1.0');
}
function vidsrc_unistall() {
	delete_option("vidsrc_active");
	delete_option("vidsrc_cron");
	delete_option("vidsrc_player_color");
	delete_option("vidsrc_movies_no");
	delete_option("vidsrc_episodes_no");
	delete_option("vidsrc_pluginversion");
	
	unregister_setting( 'vidsrc_settings', 'vidsrc_active' );
	unregister_setting( 'vidsrc_settings', 'vidsrc_cron' );
	unregister_setting( 'vidsrc_settings', 'vidsrc_player_color' );
	unregister_setting( 'vidsrc_settings', 'vidsrc_movies_no' );
	unregister_setting( 'vidsrc_settings', 'vidsrc_episodes_no' );
	unregister_setting( 'vidsrc_settings', 'vidsrc_pluginversion' );
}


function vidsrc_cronjob_activation() {
    if (! wp_next_scheduled ( 'vidsrc_add_data_event' )) {
        if($vidsrc_cron_time != 'off'){
			wp_schedule_event(time(), '1min', 'vidsrc_add_data_event');
        }
    }
    if (! wp_next_scheduled ( 'vidsrc_add_data_indb_event' )) {
        if($vidsrc_cron_time != 'off'){
			wp_schedule_event(time(), '15min', 'vidsrc_add_data_indb_event');
        }
    }
    
    if (! wp_next_scheduled ( 'vidsrc_movies_event' )) {
	    $vidsrc_cron_time = get_option('vidsrc_cron');
	    if($vidsrc_cron_time != 'off'){
			wp_schedule_event(time(), $vidsrc_cron_time.'min', 'vidsrc_movies_event');
		}	
    }
    if (! wp_next_scheduled ( 'vidsrc_tvshow_event' )) {
	    $vidsrc_cron_time = get_option('vidsrc_cron');
	    if($vidsrc_cron_time != 'off'){
			wp_schedule_event(time(), $vidsrc_cron_time.'min', 'vidsrc_tvshow_event');
		}	
    }
    
    if (! wp_next_scheduled ( 'vidsrc_clean_duplicates_event' )) {
        if($vidsrc_cron_time != 'off'){
			wp_schedule_event(time(), 'twicedaily', 'vidsrc_clean_duplicates_event');
        }
    }
    
    if (! wp_next_scheduled ( 'vidsrc_update_player_color_event' )) {
        if($vidsrc_cron_time != 'off'){
			wp_schedule_event(time(), '30min', 'vidsrc_update_player_color_event');
        }
    }
    
    if (! wp_next_scheduled ( 'vidsrc_clean_dead_titles_event' )) {
        if($vidsrc_cron_time != 'off'){
			wp_schedule_event(time(), 'twicedaily', 'vidsrc_clean_dead_titles_event');
        }
    }
    
    if (! wp_next_scheduled ( 'vidsrc_ping_active_event' )) {
        if($vidsrc_cron_time != 'off'){
			wp_schedule_event(time(), 'twicedaily', 'vidsrc_ping_active_event');
        }
    }
}

function vidsrc_cronjob_deactivation() {
    wp_clear_scheduled_hook('vidsrc_add_data_event');
	wp_clear_scheduled_hook('vidsrc_add_data_indb_event');
	wp_clear_scheduled_hook('vidsrc_movies_event');
	wp_clear_scheduled_hook('vidsrc_tvshow_event');
	wp_clear_scheduled_hook('vidsrc_clean_duplicates_event');
	wp_clear_scheduled_hook('vidsrc_clean_dead_titles_event');
	wp_clear_scheduled_hook('vidsrc_update_player_color_event');
	wp_clear_scheduled_hook('vidsrc_ping_active_event');
}

function vidsrc_custom_cron_schedules($vidsrc_schedules){
    if(!isset($vidsrc_schedules["1min"])){
        $vidsrc_schedules["1min"] = array(
            'interval' => 1*60,
            'display' => __('Once every 1 minutes'));
    }
    if(!isset($vidsrc_schedules["2min"])){
        $vidsrc_schedules["2min"] = array(
            'interval' => 2*60,
            'display' => __('Once every 2 minutes'));
    }
    if(!isset($vidsrc_schedules["5min"])){
        $vidsrc_schedules["5min"] = array(
            'interval' => 5*60,
            'display' => __('Once every 5 minutes'));
    }
    if(!isset($vidsrc_schedules["15min"])){
        $vidsrc_schedules["15min"] = array(
            'interval' => 15*60,
            'display' => __('Once every 15 minutes'));
    }
    if(!isset($vidsrc_schedules["30min"])){
        $vidsrc_schedules["30min"] = array(
            'interval' => 30*60,
            'display' => __('Once every 30 minutes'));
    }
    return $vidsrc_schedules;
}


function vidsrc_register_mysettings() {
	//register our settings
	register_setting( 'vidsrc_settings', 'vidsrc_active' );
	register_setting( 'vidsrc_settings', 'vidsrc_cron' );
	register_setting( 'vidsrc_settings', 'vidsrc_player_color' );
	register_setting( 'vidsrc_settings', 'vidsrc_movies_no' );
	register_setting( 'vidsrc_settings', 'vidsrc_episodes_no' );
}


