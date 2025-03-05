<?php


function vidsrc_add_data($force = false){
    
    global $vidsrc_mov_file;
    global $vidsrc_eps_file;
    
    $min_15 = 60*15;
    
	if(file_exists($vidsrc_mov_file) && filesize($vidsrc_mov_file) && !$force){
	    if(time()-filemtime($vidsrc_mov_file) > $min_15){
    	    $vidsrc_mov = curl_get_data("https://v2.vidsrc.me/ids/mov.txt");
    	    //echo "mov\n";
    	    //echo time()-filemtime($vidsrc_mov_file)."-".$min_15."\n";
	    }
	}else{
	    $vidsrc_mov = curl_get_data("https://v2.vidsrc.me/ids/mov.txt");
	}
    
    //echo "\n";
    
    if(file_exists($vidsrc_eps_file) && filesize($vidsrc_eps_file) && !$force){
	    if(time()-filemtime($vidsrc_eps_file) > $min_15){
    	    $vidsrc_eps = curl_get_data("https://v2.vidsrc.me/ids/eps.txt");
    	    //echo "eps\n";
    	    //echo time()-filemtime($vidsrc_eps_file)."-".$min_15."\n";
	    }
	}else{
	    $vidsrc_eps = curl_get_data("https://v2.vidsrc.me/ids/eps.txt");
	}
    
    
    if(@strlen($vidsrc_mov)){
        file_put_contents($vidsrc_mov_file , $vidsrc_mov);
    }
    
    if(@strlen($vidsrc_eps)){
        file_put_contents($vidsrc_eps_file , $vidsrc_eps);
    }
}

function vidsrc_add_data_indb($force = false){
    
	
    ini_set('max_execution_time', '120');
    
    global $wpdb;
	global $indb_mov_file;
	global $indb_eps_file;
    
	$sql_indb_eps = "
	SELECT 
    	CONCAT(postmeta_tmdb.meta_value,'_',postmeta_season.meta_value ,'x',postmeta_episode.meta_value) as ep
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
	
	
	$sql_indb_mov = "
	SELECT 
    	imdb.meta_value as imdb
    FROM $wpdb->posts as posts
    LEFT JOIN $wpdb->postmeta as imdb ON
       	imdb.post_id = posts.ID AND
    	imdb.meta_key = 'ids'
    WHERE 
    	posts.post_type = 'movies' AND
        posts.post_status = 'publish' AND
    	imdb.meta_id IS NOT NULL
	";
	
	
	$day = 3600*24;
	
	
	$flag_eps = 1;
	$flag_mov = 1;
	
	if(file_exists($indb_mov_file) && time()-filemtime($indb_mov_file) < $day && @filesize($indb_mov_file) > 0 && !$force){
	    $flag_mov = 0;
	}
	if(file_exists($indb_eps_file) && time()-filemtime($indb_eps_file) < $day && @filesize($indb_eps_file) > 0 && !$force){
	    $flag_eps = 0;
	}
	
	
	if($flag_mov){
	    
	    $indb_mov_ids = [];
        
        $res_mov = $wpdb->get_results($sql_indb_mov);
        
        foreach($res_mov as $row){
            $indb_mov_ids[] = $row->imdb;
        }
        
        unset($res_mov);
        if(empty($indb_mov_ids))
            touch($indb_mov_file);
        else
            file_put_contents($indb_mov_file , implode("\n",$indb_mov_ids));
	}
	
	if($flag_eps){
	    $indb_eps_ids = [];
    
        $res_eps = $wpdb->get_results($sql_indb_eps);
        
        foreach($res_eps as $row){
            $indb_eps_ids[] = $row->ep;
        }
        
        unset($res_eps);
        if(empty($indb_eps_file))
            touch($indb_eps_file);
        else
            file_put_contents($indb_eps_file , implode("\n",$indb_eps_ids));
	}
}


function vidsrc_insert_indb_row($data){
    global $wpdb;
    global $indb_mov_file;
    global $indb_eps_file;
    
    
    if(@is_numeric($data->tmdb) && @is_numeric($data->season) && @is_numeric($data->episode)){
        $indb_id = $data->tmdb."_".$data->season."x".$data->episode;
        file_put_contents($indb_eps_file , "\n".$indb_id , FILE_APPEND | LOCK_EX);
    }else{
        $indb_id = $data->imdb_id;
        print_r($indb_id);
        file_put_contents($indb_mov_file , "\n".$indb_id , FILE_APPEND | LOCK_EX);
    }
}