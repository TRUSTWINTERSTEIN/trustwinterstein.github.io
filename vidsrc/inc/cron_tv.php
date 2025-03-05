<?php


function vidsrc_do_action_for_tvshows(){
    
    
    if(!checkLoad())
        return 0;
    
    
    global $ep_add_limit;
    global $ep_add_count;
    global $eps_not_added;
    
    
    $ep_add_limit = cron_add_limit('e');
    $ep_add_count = 0;
    

	if(get_option('vidsrc_cron') != 'off' && get_option('vidsrc_active') || @$_GET['test']){
	    
    
	    
	    $eps_not_added = get_episodes_not_added();
	    
	    
	    $new_eps_not_added = [];
	    foreach($eps_not_added as $ep){
	        $ep_key = $ep->tmdb."_".$ep->season."_".$ep->episode;
	        $new_eps_not_added[$ep_key] = $ep;
	    }
	    
	    $eps_not_added = $new_eps_not_added;
	    unset($new_eps_not_added);
	    
	    
	    if(!empty($eps_not_added)){
            foreach($eps_not_added as $vidsrc_ep){
                vidsrc_post_episodes($vidsrc_ep);
            }
	    }
	    
	    $eps_not_updated = get_episodes_not_updated();
	    if(!empty($eps_not_updated)){
	        foreach($eps_not_updated as $vidsrc_ep){
	                if(vidsrc_update_episode($vidsrc_ep)){
	                    $ep_add_count++;
	                    clear_dbmv_cache($vidsrc_ep->tmdb);
	                    vidsrc_insert_indb_row($vidsrc_ep);
	                    
	                    if($ep_add_count >= $ep_add_limit)
	                        exit("limit reached");
	                }
	        }
	    }
	    
	}
    return 0;
}

function vidsrc_post_episodes($vidsrc_data){
    
    global $ep_add_limit;
    global $ep_add_count;
    global $eps_not_added;
    
    global $wpdb;
    
    if(!is_numeric($vidsrc_data->tmdb)){
        exit();
    }

    $vidsrc_tv_data_raw = curl_get_data('https://v2.vidsrc.me/api/t/'.$vidsrc_data->tmdb);
    
    if($vidsrc_tv_data_raw == "not in db"){
        $ep_key = $vidsrc_data->tmdb."_".$vidsrc_data->season."x".$vidsrc_data->episode;
        unset($eps_not_added[$ep_key]);
        return 0;
    }
    
    $vidsrc_tv_data = json_decode($vidsrc_tv_data_raw);
    
    
    $vidsrc_data->imdb = $vidsrc_tv_data->general->imdb;
    
    if(@!is_object($vidsrc_tv_data->tmdb))
        exit();
    
    $db_post_tv = $wpdb->get_row("
    SELECT  posts.* ,
        postmeta_tmdb.meta_value as tmdb_id
    FROM $wpdb->posts as posts
    LEFT JOIN $wpdb->postmeta as postmeta_tmdb
    ON  postmeta_tmdb.post_id = posts.ID AND
        postmeta_tmdb.meta_key = 'ids'
    WHERE
        posts.post_type = 'tvshows' AND
        posts.post_status = 'publish' AND
        postmeta_tmdb.meta_value = '".$vidsrc_data->tmdb."' ");
    
    
    if(empty($db_post_tv)){
        $db_post_tv = vidsrc_post_tv($vidsrc_tv_data);
        if(!$db_post_tv){
            file_put_contents("vidsrc_error.txt",$vidsrc_data->tmdb." can't add to db");
            exit();
        }
    }
    
    
    clear_dbmv_cache($vidsrc_data->tmdb);
    if(is_script_time_limit()){exit();}
    
    $vidsrc_eps = get_episodes($vidsrc_data->tmdb);

    
    $vidsrc_seasons = [];
    foreach($vidsrc_eps as $ep){
        if(!in_array($ep->season,$vidsrc_seasons)){
            $vidsrc_seasons[] = $ep->season;
        }
    }
    
    
    foreach($vidsrc_tv_data->tmdb->seasons as $key => $season){
        if(!in_array($season->season_number,$vidsrc_seasons)){
            foreach($vidsrc_seasons as $vidsrc_season){
                preg_match("/".$vidsrc_season."/i" , $season->name , $match);
                if($match){
                    $vidsrc_tv_data->tmdb->seasons[$key]->season_number = $vidsrc_season;
                    break;
                }
            }
        }
    }
    
    
    
    foreach($vidsrc_tv_data->tmdb->seasons as $season){
        if(in_array($season->season_number,$vidsrc_seasons)){
            
            $db_post_se = $wpdb->get_row("
                SELECT  posts.* ,
            		postmeta_tmdb.meta_value as tmdb_id , 
            		postmeta_season.meta_value as season
                FROM $wpdb->posts as posts
                LEFT JOIN $wpdb->postmeta as postmeta_tmdb
                ON  postmeta_tmdb.post_id = posts.ID AND
                    postmeta_tmdb.meta_key = 'ids'
                LEFT JOIN $wpdb->postmeta as postmeta_season
                ON  postmeta_season.post_id = posts.ID AND
                    postmeta_season.meta_key = 'temporada'
                WHERE
                    posts.post_type = 'seasons' AND
                    posts.post_status = 'publish' AND
                    postmeta_tmdb.meta_value = '".$vidsrc_data->tmdb."' AND
                    postmeta_season.meta_value = '".$season->season_number."'
                    ");
            
            
            if(empty($db_post_se)){
                if(!vidsrc_post_season($vidsrc_tv_data , $season)){
                    exit();
                }
            }
        }
    }
    
    clear_dbmv_cache($vidsrc_data->tmdb);
    if(is_script_time_limit()){exit();}
    
    $missing_episodes = [];
    $not_added_eps = get_episodes_not_added($vidsrc_data->tmdb);
    foreach($not_added_eps as $ep){
        if(!is_array($missing_episodes[$ep->season])){
            $missing_episodes[$ep->season] = [];
        }
        
        $missing_episodes[$ep->season][] = $ep->episode;
    }
    unset($not_added_eps);
    
    ksort($missing_episodes);
    foreach($missing_episodes as $season => $episode){
        sort($missing_episodes[$season]);
    }
    
    
    $tmdb_seasons_data = [];    
    
    
    foreach($missing_episodes as $mis_s => $mis_e){
        if(@!is_object($tmdb_seasons_data[$mis_s])){
            
            $tmdb_seasons_data[$mis_s] = json_decode(curl_get_data('https://v2.vidsrc.me/api/t/'.$vidsrc_data->tmdb."/".$mis_s));
            
            
            
            if(@!is_array($tmdb_seasons_data[$mis_s]->episodes))
                exit();
        }
        
        
        foreach($tmdb_seasons_data[$mis_s]->episodes as $vidsrc_ep_data){
            
            
            if(in_array($vidsrc_ep_data->episode_number,$missing_episodes[$vidsrc_ep_data->season_number])){
                $db_post_ep = $wpdb->get_row("
                SELECT  posts.* ,
            		postmeta_tmdb.meta_value as tmdb_id , 
            		postmeta_season.meta_value as season ,
            		postmeta_episode.meta_value as episode
                FROM $wpdb->posts as posts
                LEFT JOIN $wpdb->postmeta as postmeta_tmdb
                ON  postmeta_tmdb.post_id = posts.ID AND
                    postmeta_tmdb.meta_key = 'ids'
                LEFT JOIN $wpdb->postmeta as postmeta_season
                ON  postmeta_season.post_id = posts.ID AND
                    postmeta_season.meta_key = 'temporada'
                 LEFT JOIN $wpdb->postmeta as postmeta_episode
                ON  postmeta_episode.post_id = posts.ID AND
                    postmeta_episode.meta_key = 'episodio'
                WHERE
                    posts.post_type = 'episodes' AND
                    posts.post_status = 'publish' AND
                    postmeta_tmdb.meta_value = '".$vidsrc_tv_data->tmdb->id."' AND
                    postmeta_season.meta_value = '".$vidsrc_ep_data->season_number."' AND
                    postmeta_episode.meta_value = '".$vidsrc_ep_data->episode_number."'
                    ");
                
                
                $ep_key = $vidsrc_tv_data->tmdb->id."_".$vidsrc_ep_data->season_number."_".$vidsrc_ep_data->episode_number;
                
                $insert_indb_data = new stdClass();
                $insert_indb_data->tmdb = $vidsrc_tv_data->tmdb->id;
                $insert_indb_data->season = $vidsrc_ep_data->season_number;
                $insert_indb_data->episode = $vidsrc_ep_data->episode_number;
                
                if(empty($db_post_ep)){
                    if(vidsrc_post_episode($vidsrc_tv_data , $vidsrc_ep_data)){
                        clear_dbmv_cache($vidsrc_data->tmdb);
                    }else{
                        exit();
                    }
                }   
                vidsrc_insert_indb_row($insert_indb_data);
                
                $eps_not_added[$ep_key] = "";
                $ep_add_count++;
                if($ep_add_count >= $ep_add_limit)
                    exit();
                
                if(is_script_time_limit()){exit();}
            }
        }
    }
    
}

function vidsrc_post_tv($vidsrc_tv_data){
    
    
    $tv_data = new stdClass();
    
    $tv_data->title = $vidsrc_tv_data->general->title;
    
    
    
    
    if(!empty($vidsrc_tv_data->tmdb->overview)){
        $tv_data->plot = $vidsrc_tv_data->tmdb->overview;
    }else{
        $tv_data->plot = $vidsrc_tv_data->general->plot;
    }
    
    
    
    
    $tv_data->terms = [];
    
    
    // genres terms
    $tv_data->terms["genres"] = [];
    if(is_array($vidsrc_tv_data->tmdb->genres)){
        foreach($vidsrc_tv_data->tmdb->genres as $genre){
            $tv_data->terms["genres"][] = $genre->name;
        }
    }elseif(count($vidsrc_tv_data->general->genres)){
        $tv_data->terms["genres"] = $vidsrc_tv_data->general->genres;
    }
    
    
    // quality terms
    
    
    // year terms
    if(!empty($vidsrc_tv_data->general->year)){
        $tv_data->terms["dtyear"] = $vidsrc_tv_data->general->year;
    }
    
    
    // directors terms
    $tv_data->terms["dtcreator"] = [];
    if(is_array($vidsrc_tv_data->tmdb->created_by)){
        foreach($vidsrc_tv_data->tmdb->created_by as $person){
                $tv_data->terms["dtcreator"][] = $person->name;
        }
    }
    
    // networks terms
    $tv_data->terms["dtnetworks"] = [];
    if(is_array($vidsrc_tv_data->tmdb->networks)){
        foreach($vidsrc_tv_data->tmdb->networks as $network){
                $tv_data->terms["dtnetworks"][] = $network->name;
        }
    }
    
    
    
    // cast terms
    $tv_data->terms["dtcast"] = [];
    if(is_array($vidsrc_tv_data->tmdb->credits->cast)){
        $count = 0;
        foreach($vidsrc_tv_data->tmdb->credits->cast as $person){
            $tv_data->terms["dtcast"][] = $person->name;
            $count++;
            if($count > 9)
                break;
        }
    }
    if(is_array($vidsrc_tv_data->general->people->cast) &&
        !count($tv_data->terms["dtcast"])){
        $count = 0;
        foreach($vidsrc_tv_data->general->people->cast as $person){
            $tv_data->terms["dtcast"][] = $person;
            $count++;
            if($count > 9)
                break;
        }
    }
    
    
    
    $tv_data->meta = [];
    
    
    $tv_data->meta['clgnrt'] = "1";
    
    // imdb
    if(is_numeric($vidsrc_tv_data->general->imdb_rating))
        $tv_data->meta['imdbRating'] = $vidsrc_tv_data->general->imdb_rating;
    
    // tmdb
    $tv_data->meta['ids'] = $vidsrc_tv_data->tmdb->id;
    
    if(!empty($vidsrc_tv_data->tmdb->poster_path)){
        $tv_data->meta['dt_poster'] = $vidsrc_tv_data->tmdb->poster_path;
    }
    
    if(!empty($vidsrc_tv_data->tmdb->backdrop_path)){
        $tv_data->meta['dt_backdrop'] = $vidsrc_tv_data->tmdb->backdrop_path;
    }
    
    if(isset($vidsrc_tv_data->tmdb->original_name)){
        $tv_data->meta['original_name'] = $vidsrc_tv_data->tmdb->original_name;
    }
    
    if(!empty($vidsrc_tv_data->tmdb->first_air_date)){
        $tv_data->meta['first_air_date'] = $vidsrc_tv_data->tmdb->first_air_date;
    }

    if(!empty($vidsrc_tv_data->tmdb->last_air_date)){
        $tv_data->meta['last_air_date'] = $vidsrc_tv_data->tmdb->last_air_date;
    }
    
    if(!empty($vidsrc_tv_data->tmdb->vote_average)){
        $tv_data->meta['vote_average'] = $vidsrc_tv_data->tmdb->vote_average;
    }
    
    if(!empty($vidsrc_tv_data->tmdb->vote_count)){
        $tv_data->meta['vote_count'] = $vidsrc_tv_data->tmdb->vote_count;
    }
    
    
    if(!empty($vidsrc_tv_data->tmdb->episode_run_time[0])){
        $tv_data->meta['episode_run_time'] = $vidsrc_tv_data->tmdb->episode_run_time[0];
    }
    
    if(!empty($vidsrc_tv_data->tmdb->credits->cast)){
        $cast_meta_value = [];
        foreach($vidsrc_tv_data->tmdb->credits->cast as $casting){
            if(empty($casting->profile_path))
                $casting->profile_path = "null";
            $cast_meta_value[] = "[".$casting->profile_path.";".$casting->name.",".$casting->character."]";
        }
    }
    if(is_array($cast_meta_value) && count($cast_meta_value))
        $tv_data->meta['dt_cast'] = implode("",$cast_meta_value);
    else
        $tv_data->meta['dt_cast'] = $cast_meta_value;
    
    
    if(!empty($vidsrc_tv_data->tmdb->created_by)){
        $creator_meta_value = [];
        foreach($vidsrc_tv_data->tmdb->created_by as $person){
            if(empty($person->profile_path))
                $person->profile_path = "null";
            
            $creator_meta_value = "[".$person->profile_path.";".$person->name."]";
        }
    }
    if(is_array($creator_meta_value) && count($creator_meta_value))
        $tv_data->meta['dt_creator'] = implode("",$creator_meta_value);
    else
        $tv_data->meta['dt_creator'] = $creator_meta_value;
        
    
    
    
    $post_insert_data = array(
      'post_title'    => $tv_data->title,
      'post_content'  => $tv_data->plot,
      'post_status'   => 'publish',
      'post_type'   => 'tvshows',
      'post_author'   => "333",
  
	);
    
    $new_post_id = wp_insert_post( $post_insert_data );
    
    if(is_numeric($new_post_id)){
        foreach($tv_data->terms as $taxonomy => $terms){
            setTerms($new_post_id , $terms , $taxonomy);
        }
        foreach($tv_data->meta as $meta_key => $meta_value){
            add_post_meta($new_post_id,$meta_key,$meta_value, true);
        }
    }else{
        exit("failed post insert tv show");
    }
    
    return 1;
}

function vidsrc_post_season($vidsrc_tv_data , $season_data){
    
    $se_data = new stdClass();
    
    $se_data->title = $vidsrc_tv_data->general->title.": ".$season_data->name;
    $se_data->plot = $season_data->overview;
    
    
    // meta
    $se_data->meta = [];
    
    
    $se_data->meta['clgnrt'] = "1";
    
    
    // tmdb
    $se_data->meta['serie'] = $vidsrc_tv_data->general->title;
    
    $se_data->meta['ids'] = $vidsrc_tv_data->tmdb->id;
    
    if(!empty($season_data->poster_path)){
        $se_data->meta['dt_poster'] = $season_data->poster_path;
    }
    
    if(!empty($season_data->season_number)){
        $se_data->meta['temporada'] = $season_data->season_number;
    }
    if(!empty($season_data->air_date)){
        $se_data->meta['air_date'] = $season_data->air_date;
    }
    
    
    
    $post_insert_data = array(
      'post_title'    => $se_data->title,
      'post_content'  => $se_data->plot,
      'post_status'   => 'publish',
      'post_type'   => 'seasons',
      'post_author'   => "333",
	);
    
    $new_post_id = wp_insert_post( $post_insert_data );
    
    if(is_numeric($new_post_id)){
        foreach($se_data->meta as $meta_key => $meta_value){
            add_post_meta($new_post_id,$meta_key,$meta_value, true);
        }
    }else{
        exit("failed post insert season");
    }
    
    return 1;
    
}

function vidsrc_post_episode($vidsrc_tv_data , $vidsrc_ep_data){
    
    $ep_data = new stdClass();
    
    $ep_data->title = $vidsrc_tv_data->general->title." ".$vidsrc_ep_data->season_number."x".$vidsrc_ep_data->episode_number;
    
    
    $ep_data->plot = $vidsrc_ep_data->overview;
    
    
    $ep_data->meta = [];
    
    // tmdb
    $ep_data->meta['ids'] = $vidsrc_tv_data->tmdb->id;
    
    $ep_data->meta['temporada'] = $vidsrc_ep_data->season_number;
    
    $ep_data->meta['episodio'] = $vidsrc_ep_data->episode_number;
    
    $ep_data->meta['serie'] = $vidsrc_tv_data->general->title;
    
    
    if(!empty($vidsrc_ep_data->name)){
        $ep_data->meta['episode_name'] = $vidsrc_ep_data->name;
    }
    
    if(!empty($vidsrc_ep_data->air_date)){
        $ep_data->meta['air_date'] = $vidsrc_ep_data->air_date;
    }
    
    if(!empty($vidsrc_ep_data->still_path)){
        $ep_data->meta['dt_backdrop'] = $vidsrc_ep_data->still_path;
    }
    
    
    $vidsrc_iframe = array();
    $vidsrc_iframe[] = array('name' => 'VidSrc','select' => 'iframe','idioma' => '','url'=>'https://vidsrc.me/embed/'.$vidsrc_tv_data->general->imdb."/".$vidsrc_ep_data->season_number."-".$vidsrc_ep_data->episode_number."/".getPlayerColor());
    
    $ep_data->meta['repeatable_fields'] = $vidsrc_iframe;
    
    
    $post_insert_data = array(
      'post_title'    => $ep_data->title,
      'post_content'  => $ep_data->plot,
      'post_status'   => 'publish',
      'post_type'   => 'episodes',
      'post_author'   => "333",
  
	);
	
    $new_post_id = wp_insert_post( $post_insert_data );
    
    if(is_numeric($new_post_id)){
        foreach($ep_data->meta as $meta_key => $meta_value){
            add_post_meta($new_post_id,$meta_key,$meta_value, true);
        }
    }else{
        exit("failed post insert episode");
    }
    
    return 1;
    
}

function vidsrc_update_episode($vidsrc_data){
    
    $vidsrc_iframe = [
        ['name' => 'VidSrc','select' => 'iframe',
        'idioma' => '',
        'url'=>'https://vidsrc.me/embed/'.$vidsrc_data->imdb_id."/".$vidsrc_data->season."-".$vidsrc_data->episode."/".getPlayerColor()]
        ];
    
    
    global $wpdb;
    
    $db_post = $wpdb->get_row("
        SELECT  posts.* ,
    		postmeta_tmdb.meta_value as tmdb_id ,
    		postmeta_episode.meta_value as season,
            postmeta_season.meta_value as episode
        FROM $wpdb->posts as posts
        LEFT JOIN $wpdb->postmeta as postmeta_tmdb
        ON  postmeta_tmdb.post_id = posts.ID AND
            postmeta_tmdb.meta_key = 'ids'
        LEFT JOIN $wpdb->postmeta as postmeta_season
        ON  postmeta_season.post_id = posts.ID AND
            postmeta_season.meta_key = 'temporada'
        LEFT JOIN $wpdb->postmeta as postmeta_episode
        ON  postmeta_episode.post_id = posts.ID AND
            postmeta_episode.meta_key = 'episodio'
        WHERE
            posts.post_type = 'episodes' AND
            postmeta_tmdb.meta_value = '".$vidsrc_data->tmdb."' AND
            postmeta_season.meta_value = '".$vidsrc_data->season."' AND
            postmeta_episode.meta_value = '".$vidsrc_data->episode."'");
    
    
    if(!empty($db_post)){
        $post_iframes = get_post_meta($db_post->ID, 'repeatable_fields', true); 
        
        $fields_flag = 1;
        foreach($post_iframes as $post_iframe){
            preg_match('/vidsrc.me/i' , $post_iframe['url'] , $match_vidsrc);
            if($match_vidsrc){
                $fields_flag = 0;
                break;
            }
        }
        
        
        if($fields_flag){
        	if(!empty($post_iframes) and is_array($post_iframes)){
        	    $post_iframes_update = array_merge($vidsrc_iframe , $post_iframes);
        	}else{
        	    $post_iframes_update = $vidsrc_iframe;
        	}
        
        	if(!update_post_meta($db_post->ID, 'repeatable_fields', $post_iframes_update)){
        	    add_post_meta($db_post->ID, 'repeatable_fields', $post_iframes_update, true);
        	}
        }
    }
    
    return 1;
}

function get_episodes_not_added($tmdb = 0){
    ini_set("memory_limit","1024M");
    
    global $ep_add_limit;
    global $vidsrc_eps_file;
    global $indb_eps_file;
    
    $vidsrc_eps = file($vidsrc_eps_file ,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    $indb_eps = file($indb_eps_file ,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    
    $eps_diff = array_diff($vidsrc_eps , $indb_eps);
    
    $not_added_eps = [];
    $not_added_eps_br = 0;
    
    foreach($eps_diff as $ep){
        preg_match("/^([0-9]+)_([0-9]+)x([0-9]+)$/" , $ep , $match_info);
        $ep_tmp = new stdClass();
        $ep_tmp->tmdb = $match_info[1];
        $ep_tmp->season = $match_info[2];
        $ep_tmp->episode = $match_info[3];
        $ep_key = $ep_tmp->tmdb."_".$ep_tmp->season."x".$ep_tmp->episode;
        
        
        if($tmdb){
            if($ep_tmp->tmdb == $tmdb)
                $not_added_eps[$ep_key] = $ep_tmp;
        }else{
            $not_added_eps[$ep_key] = $ep_tmp;
        }
        $not_added_eps_br++;
        if($not_added_eps_br > $ep_add_limit){
            break;
        }
    }
    
    
    return $not_added_eps;
}

function get_episodes_not_updated(){
    global $wpdb;
    global $ep_add_limit;
    global $vidsrc_eps_file;
    
    if(!@is_numeric($ep_add_limit)){
        $ep_add_limit = cron_add_limit('e');
    }
    
    
    $sql = "
    SELECT
        CONCAT(postmeta_tmdb.meta_value,'_',postmeta_season.meta_value ,'x',postmeta_episode.meta_value) as ep
    FROM
        $wpdb->posts AS posts
    LEFT JOIN $wpdb->postmeta AS postmeta_tmdb
    ON	postmeta_tmdb.meta_key = 'ids' AND 
    	postmeta_tmdb.post_id = posts.ID
    LEFT JOIN $wpdb->postmeta AS postmeta_season
    ON	postmeta_season.meta_key = 'temporada' AND 
    	postmeta_season.post_id = posts.ID
    LEFT JOIN $wpdb->postmeta AS postmeta_episode
    ON	postmeta_episode.meta_key = 'episodio' AND 
    	postmeta_episode.post_id = posts.ID
    LEFT JOIN $wpdb->postmeta AS postsmeta_embed
    ON	postsmeta_embed.meta_key = 'repeatable_fields' AND 
    	postsmeta_embed.post_id = posts.ID AND 
        postsmeta_embed.meta_value LIKE '%vidsrc.me%'
    WHERE
        posts.post_type = 'episodes' AND 
        posts.post_status = 'publish' AND 
        postmeta_tmdb.post_id IS NOT NULL AND  
        postmeta_season.post_id IS NOT NULL AND 
        postmeta_episode.post_id IS NOT NULL AND 
        postsmeta_embed.post_id IS NULL
    LIMIT ".$ep_add_limit;
    
    
    $not_updated_eps = $wpdb->get_results($sql);	
    
    
    $not_updated_eps_tmp = [];
    foreach($not_updated_eps as $row){
        $not_updated_eps_tmp[] = $row->ep;
    }
    $not_updated_eps = $not_updated_eps_tmp;
    unset($not_updated_eps_tmp);
    
    $vidsrc_eps = file($vidsrc_eps_file ,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    
    $not_updated_eps = array_intersect($not_updated_eps , $vidsrc_eps);
    
    unset($vidsrc_eps);
    
    $not_updated_arr = [];
    foreach($not_updated_eps as $ep){
        preg_match("/^([0-9]+)_([0-9]+)x([0-9]+)$/" , $ep , $match_info);
        $ep_tmp = new stdClass();
        $ep_tmp->tmdb = $match_info[1];
        $ep_tmp->season = $match_info[2];
        $ep_tmp->episode = $match_info[3];
        
        $not_updated_arr[$ep] = $ep_tmp;
    }
    unset($not_updated_eps);
    
    return $not_updated_arr;
}

function get_episodes($tmdb){
    ini_set("memory_limit","1024M");
    global $vidsrc_eps_file;
    
    $vidsrc_eps = file($vidsrc_eps_file ,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    
    $eps = [];
    foreach($vidsrc_eps as $ep){
        preg_match("/^([0-9]+)_([0-9]+)x([0-9]+)$/" , $ep , $match_info);
        $ep_tmp = new stdClass();
        $ep_tmp->tmdb = $match_info[1];
        $ep_tmp->season = $match_info[2];
        $ep_tmp->episode = $match_info[3];
        $ep_key = $ep_tmp->tmdb."_".$ep_tmp->season."x".$ep_tmp->episode;
        
        if($ep_tmp->tmdb == $tmdb)
                $eps[$ep_key] = $ep_tmp;
    }
    
    return $eps;
}




function get_all_eps($by_vidsrc = 0){
    global $wpdb;
    
    $post_id_val_str = "";
    if($by_vidsrc)
        $post_id_val_str = ",\n posts.ID \n";
    
    
    $sql = "
        SELECT  
        	postmeta_tmdb.meta_value as tmdb_id ,
            postmeta_season.meta_value as season,
            postmeta_episode.meta_value as episode
            $post_id_val_str
        FROM
            $wpdb->posts as posts
        LEFT JOIN $wpdb->postmeta as postmeta_tmdb
        ON  postmeta_tmdb.meta_key = 'ids' AND
            postmeta_tmdb.post_id = posts.ID
        LEFT JOIN $wpdb->postmeta as postmeta_season
        ON  postmeta_season.meta_key = 'temporada' AND
            postmeta_season.post_id = posts.ID
        LEFT JOIN $wpdb->postmeta as postmeta_episode
        ON  postmeta_episode.meta_key = 'episodio' AND
            postmeta_episode.post_id = posts.ID
        WHERE   posts.post_type = 'episodes' AND 
                posts.post_status = 'publish'
        ";
    
    if($by_vidsrc)
        $sql .= " AND \n posts.post_author = 333";   
        
    
    $eps_rows = $wpdb->get_results($sql);	
    
    $eps_arr = [];
    
    foreach($eps_rows as $ep_row){
        $ep_key = $ep_row->tmdb_id."_".$ep_row->season."_".$ep_row->episode;
        $eps_arr[$ep_key] = "";
        if($by_vidsrc)
            $eps_arr[$ep_key] = $ep_row->ID;
    }
    
    
    return $eps_arr;
}

function get_all_ses($by_vidsrc = 0){
    global $wpdb;
    
    $post_id_val_str = "";
    if($by_vidsrc)
        $post_id_val_str = ",\n posts.ID \n";
    
    
    $sql = "
        SELECT  
        	postmeta_tmdb.meta_value as tmdb_id ,
            postmeta_season.meta_value as season
            $post_id_val_str
        FROM
            $wpdb->posts as posts
        LEFT JOIN $wpdb->postmeta as postmeta_tmdb
        ON  postmeta_tmdb.meta_key = 'ids' AND
            postmeta_tmdb.post_id = posts.ID
        LEFT JOIN $wpdb->postmeta as postmeta_season
        ON  postmeta_season.meta_key = 'temporada' AND
            postmeta_season.post_id = posts.ID
        WHERE   posts.post_type = 'seasons' AND 
                posts.post_status = 'publish'
        ";
    
    if($by_vidsrc)
        $sql .= " AND \n posts.post_author = 333";   
        
    
    $ses_rows = $wpdb->get_results($sql);	
    
    $ses_arr = [];
    
    foreach($ses_rows as $ses_row){
        $se_key = $ses_row->tmdb_id."_".$ses_row->season;
        $ses_arr[$se_key] = "";
        if($by_vidsrc)
            $ses_arr[$se_key] = $ses_row->ID;
    }
    
    
    return $ses_arr;
}

function get_all_tvs($by_vidsrc = 0){
    global $wpdb;
    
    $post_id_val_str = "";
    if($by_vidsrc)
        $post_id_val_str = ",\n posts.ID \n";
    
    
    $sql = "
        SELECT  
        	postmeta_tmdb.meta_value as tmdb_id 
            $post_id_val_str
        FROM
            $wpdb->posts as posts
        LEFT JOIN $wpdb->postmeta as postmeta_tmdb
        ON  postmeta_tmdb.meta_key = 'ids' AND
            postmeta_tmdb.post_id = posts.ID
        WHERE   posts.post_type = 'tvshows' AND 
                posts.post_status = 'publish'
        ";
    
    if($by_vidsrc)
        $sql .= " AND \n posts.post_author = 333";   
        
    
    $tvs_rows = $wpdb->get_results($sql);	
    
    $tvs_arr = [];
    
    foreach($tvs_rows as $tvs_row){
        $tv_key = $tvs_row->tmdb_id;
        $tvs_arr[$tv_key] = "";
        if($by_vidsrc)
            $tvs_arr[$tv_key] = $tvs_row->ID;
    }
    
    
    return $tvs_arr;
}