<?php


function is_script_time_limit(){
    $script_start = microtime(true);
    $script_limit = 50;
    if(microtime(true)-$script_start > $script_limit)
        return true;
    else    
        return false;
}

function checkLoad(){
    if(function_exists("sys_getloadavg")){
        $load = sys_getloadavg();    
        if($load[0] > 7){
            return 0;
        }else{
            return 1;
        }
    }else{
        return 1;
    }
}

function cron_add_limit($vidsrc_k){
	if($vidsrc_k == 'm'){
		$vidsrc_m = get_option('vidsrc_movies_no');
		$vidsrc_nGl = (5*20)+1;
		if($vidsrc_m >=$vidsrc_nGl){
			return $vidsrc_nGl-1;
		}else{
		 return $vidsrc_m;
		}
	}
	if($vidsrc_k == 'e'){
		$vidsrc_e = get_option('vidsrc_episodes_no');
		$vidsrc_nGl = (5*40)+1;
		if($vidsrc_e >=$vidsrc_nGl){
			return $vidsrc_nGl-1;
		}else{
		 	return $vidsrc_e;
		}
	}
}

function count_deleted_dupl($dupl){
    if(is_array($dupl)){
        $sum = 0;
        foreach($dupl as $num){
            $sum += $num;
        }
        
        return $sum;
    }else{
        return 0;
    }
}

function vidsrc_random_strings($vidsrc_length = 4){
    $vidsrc_characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $vidsrc_charactersLength = strlen($vidsrc_characters); 
    $vidsrc_randomString = ''; 
    for ($vidsrc_i = 0; $vidsrc_i < $vidsrc_length; $vidsrc_i++) {
        $vidsrc_randomString .= $vidsrc_characters[rand(0, $vidsrc_charactersLength - 1)]; 
    } 
    return $vidsrc_randomString;
}


function vidsrc_add_term(){
    $term_row = $wpdb->get_row("
        SELECT terms.* FROM $wpdb->terms as terms
        LEFT JOIN $wpdb->term_taxonomy as term_tax
        ON	term_tax.term_id = terms.term_id
        WHERE
        	term_tax.taxonomy = 'dtquality' AND
            terms.name = '".$quality."'");	

    

    if($term_row){
    	$quality_cat_id[] = $term_row->term_id;
    }else{
        $quality_cat_term = wp_insert_term($quality, 'dtquality');
        if($quality_cat_term){
        	$quality_cat_id[] = $quality_cat_term['term_id'];
        }
        
    }
}

function setTerms($post_id , $terms , $taxonomy){
    if(!is_array($terms)){
        if(strlen($terms)){
            $terms = [$terms];
        }else{
            exit();
        }
    }
    
    $term_ids = [];
    
    foreach($terms as $term){
    	$term_row = get_term_by( 'name', $term, $taxonomy);
    
        if($term_row){
        	$term_ids[] = $term_row->term_id;
        }else{
            $new_term = wp_insert_term($term , $taxonomy);
            
            if($new_term){
            	$term_ids[] = $new_term['term_id'];
            }
        }
    }
    
	if(wp_set_post_terms($post_id,$term_ids,$taxonomy,false)){
	    return 1;
	}else{
	    return 0;
	}
}


function getPlayerColor(){
    
    $player_color_db = get_option("vidsrc_player_color");
    
    if(strlen($player_color_db) == 7){
        return "color-".str_replace("#" , "" , $player_color_db);
    }else{
        return "";
    }
    
}

function updatePlayerColor(){
    
    
    if(!checkLoad())
        return 0;
        
        
    global $wpdb;
    
    $player_color = getPlayerColor();
    
    if(@strlen($player_color)){
    
        $wpdb->get_row("
            UPDATE $wpdb->postmeta 
            SET 
                meta_value = REGEXP_REPLACE(meta_value,'color-([a-z0-9]{0,6})','".$player_color."')
            WHERE
            	meta_value LIKE '%vidsrc.me/embed%' AND
                meta_value LIKE '%/color-%' AND
                meta_value NOT LIKE '%/".$player_color."%'
        ");
    }
}

function clear_dbmv_cache($tmdb){
    
    $files = scandir(DBMOVIES_CACHE_DIR);
    
    $matches = [];
    foreach($files as $file){
        preg_match("/".$tmdb."/i" , $file , $match);
        if($match){
            unlink(DBMOVIES_CACHE_DIR . $file);
        }
    }
}


function curl_get_data($vidsrc_url,$vidsrc_post_data='') {
	
	$vidsrc_ch = curl_init();
	$vidsrc_timeout = 15;
	curl_setopt($vidsrc_ch,CURLOPT_URL,$vidsrc_url);
	curl_setopt($vidsrc_ch,CURLOPT_RETURNTRANSFER, true);
	curl_setopt($vidsrc_ch,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
	curl_setopt($vidsrc_ch,CURLOPT_REFERER,'http://www.imdb.com');
	curl_setopt($vidsrc_ch,CURLOPT_CONNECTTIMEOUT,$vidsrc_timeout);
	curl_setopt($vidsrc_ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($vidsrc_ch,CURLOPT_COOKIEJAR,'cookies.txt');
	curl_setopt($vidsrc_ch,CURLOPT_COOKIEFILE,'cookies.txt');
	curl_setopt($vidsrc_ch, CURLOPT_SSL_VERIFYPEER, false);
	if(!empty($vidsrc_post_data))
	{
		 curl_setopt($vidsrc_ch,CURLOPT_POST, 1);
		 curl_setopt($vidsrc_ch, CURLOPT_POSTFIELDS, $vidsrc_post_data);
	}
	$vidsrc_data = curl_exec($vidsrc_ch);
	
	//echo curl_error($vidsrc_ch);
	curl_close($vidsrc_ch);
	return $vidsrc_data;
}


function pingVidsrc(){
    $url = "https://v2.vidsrc.me/ping.php";
    $postdata = [
        "dom"   => get_site_url()
        ];
    curl_get_data($url , http_build_query($postdata));
}


function get_id_from_imdb($imdb_id){
    global $wpdb;
    $mov = $wpdb->get_row("
    SELECT 
        post_id 
    FROM 
        `".$wpdb->postmeta."` 
    WHERE 
        meta_key='".esc_sql("ids")."' AND 
        meta_value='".esc_sql($imdb_id)."'
    ");
    
    if (is_object($mov)) {
		return $mov->post_id;
	}else{
		return false;
	}
}

function get_id_from_tmdb_se($tmdb_se){
    global $wpdb;
    preg_match("/^(\d+)_(\d+)x(\d+)$/" , $tmdb_se , $match);
    if($match){
        $tmdb = $match[1];
        $season = $match[2];
        $episode = $match[3];
        $ep = $wpdb->get_row("
        SELECT 
            posts.ID
        FROM 
            $wpdb->posts as posts
        LEFT JOIN $wpdb->postmeta as postmeta_tmdb 
        ON  postmeta_tmdb.meta_key = 'ids' AND 
            postmeta_tmdb.meta_value = '".esc_sql($tmdb)."' AND
            postmeta_tmdb.post_id = posts.ID
        LEFT JOIN $wpdb->postmeta as postmeta_season 
        ON  postmeta_season.meta_key = 'temporada' AND 
            postmeta_season.meta_value = '".esc_sql($season)."' AND
            postmeta_season.post_id = posts.ID
        LEFT JOIN $wpdb->postmeta as postmeta_episode 
        ON  postmeta_episode.meta_key = 'episodio' AND 
            postmeta_episode.meta_value = '".esc_sql($episode)."' AND
            postmeta_episode.post_id = posts.ID
        WHERE 
            postmeta_tmdb.post_id IS NOT NULL AND 
            postmeta_season.post_id IS NOT NULL AND 
            postmeta_episode.post_id IS NOT NULL
        LIMIT 1
        ");
        
        if (is_object($ep)) {
    		return $ep->ID;
    	}else{
    		return false;
    	}
    }
}