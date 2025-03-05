<?php


function vidsrc_settings_page() {

    if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true ) {
    $vidsrc_cron_time = get_option('vidsrc_cron');
        if(get_option('vidsrc_active')){
            wp_clear_scheduled_hook('vidsrc_add_data_event');
            wp_clear_scheduled_hook('vidsrc_add_data_indb_event');
            wp_schedule_event(time(), '1min', 'vidsrc_add_data_event');
            wp_schedule_event(time(), '15min', 'vidsrc_add_data_indb_event');
        }else{
            wp_clear_scheduled_hook('vidsrc_add_data_event');
            wp_clear_scheduled_hook('vidsrc_add_data_indb_event');
        }
    
        if($vidsrc_cron_time != 'off'){
                wp_clear_scheduled_hook('vidsrc_movies_event');
                wp_clear_scheduled_hook('vidsrc_tvshow_event');
                wp_clear_scheduled_hook('vidsrc_clean_duplicates_event');
                wp_clear_scheduled_hook('vidsrc_update_player_color_event');
                wp_clear_scheduled_hook('vidsrc_clean_dead_titles_event');
                wp_clear_scheduled_hook('vidsrc_ping_active_event');
                
                wp_schedule_event(time(), $vidsrc_cron_time.'min', 'vidsrc_movies_event');
                wp_schedule_event(time(), $vidsrc_cron_time.'min', 'vidsrc_tvshow_event');
                wp_schedule_event(time(), 'twicedaily', 'vidsrc_clean_duplicates_event');
                wp_schedule_event(time(), '30min', 'vidsrc_update_player_color_event');
                wp_schedule_event(time(), 'twicedaily', 'vidsrc_clean_dead_titles_event');
                wp_schedule_event(time(), 'twicedaily', 'vidsrc_ping_active_event');
        }else{
            wp_clear_scheduled_hook('vidsrc_movies_event');
            wp_clear_scheduled_hook('vidsrc_tvshow_event');
            wp_clear_scheduled_hook('vidsrc_clean_duplicates_event');
            wp_clear_scheduled_hook('vidsrc_update_player_color_event');
            wp_clear_scheduled_hook('vidsrc_clean_dead_titles_event');
            wp_clear_scheduled_hook('vidsrc_ping_active_event');
        }
    }
?>
<div class="wrap">
	<h2>VidSrc Dooplay</h2>

	<form method="post" action="options.php">
    <?php settings_fields( 'vidsrc_settings' ); ?>
    <?php do_settings_sections( 'vidsrc_settings' ); ?>
    <table class="form-table">
		<tr style="width:420px" valign="top">
			<th scope="row"><?php _e('Active','VidSrc');?> VidSrc</th>
			<td><input type="checkbox" name="vidsrc_active" <?php echo get_option('vidsrc_active')?'checked="checked"':''; ?>/></td>
        </tr>
		<tr style="width:420px" valign="top">
			<th scope="row"><?php _e('Cron time','VidSrc');?></th>
			<td>
			<select style="width:120px" name="vidsrc_cron">
				<option value="off" <?php echo(get_option('vidsrc_cron')=="off"?'selected="selected"':'')?>><?php _e('off','VidSrc');?></option>
				<option value="5" <?php echo(get_option('vidsrc_cron')=="5"?'selected="selected"':'')?>><?php _e('5 min','VidSrc');?></option>
				<option value="15" <?php echo(get_option('vidsrc_cron')=="15"?'selected="selected"':'')?>><?php _e('15 min','VidSrc');?></option>
				<option value="30" <?php echo(get_option('vidsrc_cron')=="30"?'selected="selected"':'')?>><?php _e('30 min','VidSrc');?></option>
				

			</select>
			</td>
			<td>How often do you want to have the server run to fetch movies and episodes. Remember the faster it is set the more pressure it has on the site. For Shared hosting every 15 minutes is recommended. For dedicated, Every 5 mins should be fine. 
			</td>
        </tr>
        <tr style="width:420px" valign="top">
			<th scope="row">Movies Add </th>
			<td><input type="number" min="1" max="50" name="vidsrc_movies_no" value="<?php echo get_option('vidsrc_movies_no'); ?>" /></td>
			<td>How many movies do you want to add on every cron run.  Default 10. Max 50.</td>
        </tr>
        <tr style="width:420px" valign="top">
			<th scope="row">Episodes Add </th>
			<td><input type="number" min="1" max="150" name="vidsrc_episodes_no" value="<?php echo get_option('vidsrc_episodes_no'); ?>" /></td>
			<td>How many episodes do you want to add on every cron run.  Default 50. Max 150.</td>
        </tr>
        
        <tr style="width:420px" valign="top">
            <?php
            $player_color = get_option('vidsrc_player_color');
            if(!$player_color)
                $player_color = "#e600e6";
            ?>
			<th scope="row">Player color: </th>
			<td><input type="color" name="vidsrc_player_color" value="<?php echo $player_color; ?>" /></td>
			<td>Select a color for the player. Changing this value may take few hours to affect all movies and episodes.</td>
        </tr>
        
        <tr style="width:420px" valign="top">
			<th scope="row">Get player stats:</th>
			<td id="get_stats_parent"><button id="get_stats" onclick="getStats()">Get stats</button></td>
        </tr>
        
        <tr id="list_results"> </tr>
        
        <script>
	        function getStats(){
                this.disabled = true;
                document.getElementById("get_stats_parent").innerHTML = "Loading...";
                document.getElementById("list_results").innerHTML = " ";
                
                var xhttp = new XMLHttpRequest();
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        document.getElementById("list_results").innerHTML = this.responseText;
                        document.getElementById("get_stats_parent").innerHTML = '<button id="get_stats" onclick="getStats()">Get stats</button>';
                    }
                };
                xhttp.open("GET", "<?php echo get_site_url(); ?>/index.php?vidsrc_stats=1&t=" + new Date().getTime(), true);
                xhttp.send();
            };
        </script>
		
    </table>
    <?php submit_button(); ?>
	</form>
	
</div>
<?php
}

function vidsrc_settings_link($vidsrc_links) { 
  $vidsrc_settings_link = '<a href="admin.php?page=vidsrc_dooplay">Settings</a>';
  array_unshift($vidsrc_links, $vidsrc_settings_link); 
  return $vidsrc_links; 
}

function vidsrc_adminmenu() {
	add_menu_page( 'VidSrc DooPlay', 'VidSrc DooPlay', 'manage_options', 'vidsrc_dooplay', 'vidsrc_settings_page', '', 2);
}




if(@$_GET['vidsrc_stats']){
    list_settings();
    exit();
}

function list_settings(){
    $vidsrc_movies_list_updated = vidsrc_get_list_updated();
    $vidsrc_movies_list_total = vidsrc_get_list_total();
    ?>
        	<div>
        	    <b>Total Movies:</b> <?php echo $vidsrc_movies_list_total['movies']; ?><br />
            	<b>Total Episodes:</b> <?php echo $vidsrc_movies_list_total['episodes']; ?><br />
            	<b>Total VidSrc Movies:</b> <?php echo $vidsrc_movies_list_updated['movies']; ?><br />
            	<b>Total VidSrc Episodes:</b> <?php echo $vidsrc_movies_list_updated['episodes']; ?><br />
        	</div>
    <?php
}

function vidsrc_get_list_updated(){
    $vidsrc_a = array();
    global $wpdb;
    
    $vidsrc_total_episodes = $wpdb->get_row("
    SELECT 
        COUNT(ID) as count 
    FROM $wpdb->posts as p,$wpdb->postmeta as ps 
    WHERE 
        ps.meta_key = 'repeatable_fields' AND 
        ps.meta_value like '%vidsrc.me%' and 
        p.ID = ps.post_id and 
        p.post_type = 'episodes' and 
        p.post_status = 'publish'
    ");	
    $vidsrc_a['episodes'] = $vidsrc_total_episodes->count;
    
    $vidsrc_total_posts = $wpdb->get_row("
    SELECT 
        COUNT(ID) as count 
    FROM $wpdb->posts as p,$wpdb->postmeta as ps 
    WHERE 
        ps.meta_key = 'repeatable_fields' and 
        ps.meta_value like '%vidsrc.me%' and 
        p.ID = ps.post_id and p.post_type = 'movies' and 
        p.post_status = 'publish'
    ");	
    $vidsrc_a['movies'] = $vidsrc_total_posts->count;
    
    return $vidsrc_a;
}

function vidsrc_get_list_total(){
    $vidsrc_a = array();
    global $wpdb;
    
    $vidsrc_total_episodes = $wpdb->get_row("
    SELECT 
        COUNT(ID) as count 
    FROM $wpdb->posts
    WHERE 
        post_type = 'episodes' and 
        post_status = 'publish'
    ");	
    $vidsrc_a['episodes'] = $vidsrc_total_episodes->count;
    
    $vidsrc_total_movies = $wpdb->get_row("
    SELECT COUNT(ID) as count 
    FROM $wpdb->posts 
    WHERE 
        post_type = 'movies' and
        post_status = 'publish'
    ");	
    $vidsrc_a['movies'] = $vidsrc_total_movies->count;
    return $vidsrc_a;
}