<?php

function vidsrc_clean_dupl(){
    
    if(!checkLoad())
        return 0;
        
    global $wpdb;
    
    $dupl = [];
    
    $dupl['movs'] = 0;
    $dupl['tvs'] = 0;
    $dupl['ses'] = 0;
    $dupl['eps'] = 0;
    
    $limit_delete = 30;
    
    
    $mov_dupl_res = $wpdb->get_results(
    "SELECT 
    	GROUP_CONCAT(DISTINCT wp_posts.ID) as ids ,
    	wp_posts.ID , 
        wp_posts.post_title ,
        COUNT(*) as c
    FROM $wpdb->posts as wp_posts 
    LEFT JOIN $wpdb->postmeta as imdb ON
    	imdb.post_id = wp_posts.ID AND
        imdb.meta_key = 'ids'
    WHERE
    	wp_posts.post_type = 'movies' AND
        wp_posts.post_status = 'publish'
    GROUP by imdb.meta_value
    HAVING c>1
    LIMIT 30");	
    
    
    foreach($mov_dupl_res as $mov_dupl_row){
        $ids = explode("," , $mov_dupl_row->ids);
        rsort($ids);
        $delete_id = $ids[0];
        if(!wp_delete_post($delete_id , true)){
            echo "failed delete: ".$delete_id."</br>\n";
            exit();
        }else{
            $dupl['movs']++;
            if(count_deleted_dupl($dupl) >= $limit_delete)
                exit();
        }
    }
    
    $tv_dupl_res = $wpdb->get_results(
    "SELECT 
    	GROUP_CONCAT(DISTINCT wp_posts.ID) as ids ,
    	wp_posts.ID , 
        wp_posts.post_title ,
        COUNT(*) as c
    FROM $wpdb->posts as wp_posts 
    LEFT JOIN $wpdb->postmeta as tmdb ON
    	tmdb.post_id = wp_posts.ID AND
        tmdb.meta_key = 'ids'
    WHERE
    	wp_posts.post_type = 'tvshows' AND
        wp_posts.post_status = 'publish'
    GROUP by tmdb.meta_value
    HAVING c>1
    LIMIT 30");	
    
    foreach($tv_dupl_res as $tv_dupl_row){
        $ids = explode("," , $tv_dupl_row->ids);
        rsort($ids);
        $delete_id = $ids[0];
        if(!wp_delete_post($delete_id , true)){
            echo "failed delete: ".$delete_id."</br>\n";
            exit();
        }else{
            $dupl['tvs']++;
            if(count_deleted_dupl($dupl) >= $limit_delete)
                exit();
        }
    }
    
    
    $se_dupl_res = $wpdb->get_results("
    SELECT 
    	GROUP_CONCAT(DISTINCT ID) as ids , 
        se ,
        COUNT(*) as c
    FROM (
        SELECT 
        	wp_posts.ID , 
            wp_posts.post_title ,
            CONCAT(tmdb.meta_value , '_', season.meta_value) as se
        FROM $wpdb->posts as wp_posts 
        LEFT JOIN $wpdb->postmeta as tmdb ON
        	tmdb.post_id = wp_posts.ID AND
            tmdb.meta_key = 'ids'
        LEFT JOIN $wpdb->postmeta as season ON
        	season.post_id = wp_posts.ID AND
            season.meta_key = 'temporada'
        WHERE
        	wp_posts.post_type = 'seasons' AND
            wp_posts.post_status = 'publish'
        GROUP by wp_posts.ID
    ) as seasons
    GROUP by se
    HAVING c>1
    LIMIT 30");
    
    
    foreach($se_dupl_res as $se_dupl_row){
        $ids = explode("," , $se_dupl_row->ids);
        rsort($ids);
        $delete_id = $ids[0];
        if(!wp_delete_post($delete_id , true)){
            echo "failed delete: ".$delete_id."</br>\n";
            exit();
        }else{
            $dupl['ses']++;
            if(count_deleted_dupl($dupl) >= $limit_delete)
                exit();
        }
    }
    
    
    $ep_dupl_res = $wpdb->get_results("
    SELECT 
    	GROUP_CONCAT(DISTINCT ID) as ids , 
        sep ,
        COUNT(*) as c
    FROM (
    SELECT 
    	wp_posts.ID , 
        wp_posts.post_title ,
        CONCAT(tmdb.meta_value , '_' , season.meta_value , 'x' , episode.meta_value) as sep
    FROM $wpdb->posts as wp_posts 
    LEFT JOIN $wpdb->postmeta as tmdb ON
    	tmdb.post_id = wp_posts.ID AND
        tmdb.meta_key = 'ids'
    LEFT JOIN $wpdb->postmeta as season ON
    	season.post_id = wp_posts.ID AND
        season.meta_key = 'temporada'
    LEFT JOIN $wpdb->postmeta as episode ON
    	episode.post_id = wp_posts.ID AND
        episode.meta_key = 'episodio'
    WHERE
    	wp_posts.post_type = 'episodes' AND
        wp_posts.post_status = 'publish'
    GROUP by wp_posts.ID
    ) as episodes
    GROUP by sep
    HAVING c>1
    LIMIT 30");
    
    
    foreach($ep_dupl_res as $ep_dupl_row){
        $ids = explode("," , $ep_dupl_row->ids);
        rsort($ids);
        for($i = 0;$i<count($ids)-1;$i++){
            $delete_id = $ids[$i];
            if(!wp_delete_post($delete_id , true)){
                echo "failed delete: ".$delete_id."</br>\n";
                exit();
            }else{
                $dupl['eps']++;
                if(count_deleted_dupl($dupl) >= $limit_delete)
                    exit();
            }
        }
    }
    
    echo $dupl['movs']." duplicate movies cleaned</br>\n";
    echo $dupl['tvs']." duplicate tv shows cleaned</br>\n";
    echo $dupl['ses']." duplicate seasons cleaned</br>\n";
    echo $dupl['eps']." duplicate episodes cleaned</br>\n";
    
    
}


function vidsrc_clean_dead_titles(){
    global $vidsrc_mov_file;
    global $indb_mov_file;
    
    global $vidsrc_eps_file;
    global $indb_eps_file;
    
    
    if(!checkLoad())
        return 0;
        
    ini_set("memory_limit","1024M");
    
    $delete_limit = 100;
    $delete_c = 0;
    
    
    
    $vidsrc_mov = file($vidsrc_mov_file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    $indb_mov = file($indb_mov_file ,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    
    $dead_mov = array_diff($indb_mov,$vidsrc_mov);
    
    
    if(!empty($dead_mov)){
        // clean dead movs
        
        if($delete_c < $delete_limit){
            foreach($dead_mov as $mov){
                $delete_id = get_id_from_imdb($mov);
                if(!wp_delete_post($delete_id , true)){
                    echo "failed delete: ".$delete_id."</br>\n";
                    exit();
                }else{
                    $delete_c++;
                }
                if($delete_c >= $delete_limit)
                    break;
            }
        }
    }
    
    if($delete_c > 0)
        vidsrc_add_data_indb(true);
    
    $vidsrc_eps = file($vidsrc_eps_file ,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    $indb_eps = file($indb_eps_file ,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    
    $dead_eps = array_diff($indb_eps , $vidsrc_eps);
    
    if(!empty($dead_eps)){
        // clean dead eps
        
        if($delete_c < $delete_limit){
            foreach($dead_eps as $ep){
                $delete_id = get_id_from_tmdb_se($ep);
                if(!wp_delete_post($delete_id , true)){
                    echo "failed delete: ".$delete_id."</br>\n";
                    exit();
                }else{
                    $delete_c++;
                }
                if($delete_c >= $delete_limit)
                    break;
            }
        }
        
    }
    
    if($delete_c > 0)
        vidsrc_add_data_indb(true);
    
}

