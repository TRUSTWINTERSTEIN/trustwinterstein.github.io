<?php


function vidsrc_do_action_for_movies() {
    
    if(!checkLoad())
        return 0;
        
    if(get_option('vidsrc_cron') != 'off' && get_option('vidsrc_active') || @$_GET['test']){

        
        global $movies_add_limit;
        global $movies_not_added;
        global $movies_add_count;
        
        $movies_add_limit = cron_add_limit('m');
        $movies_not_added = get_movies_not_added();
        
        
        $movies_add_count = 0;
        
        
        if(!empty($movies_not_added)){
            foreach($movies_not_added as $vidsrc_m){
                $post_movie_res = vidsrc_post_movie($vidsrc_m);
                if( $post_movie_res == 'insert' || 
                    $post_movie_res == 'update' ){
                
                    vidsrc_insert_indb_row($vidsrc_m);
                    echo $post_movie_res;
                    
                    $movies_add_count++;
                    if($movies_add_count >= $movies_add_limit) 
                        exit;
                    
                    if(is_script_time_limit())
                        exit();
                }
            } 
        }
            

        $movies_not_updated = get_movies_not_updated();
        if(!empty($movies_not_updated)){
            foreach($movies_not_updated as $vidsrc_m){
                if(vidsrc_post_movie($vidsrc_m) == 'update'){
                    $movies_add_count++;
                    if($movies_add_count >= $movies_add_limit){ exit; }

                    if(is_script_time_limit())
                        exit();
                }
            }
        }
        
        
    }
}



function get_all_movies($by_vidsrc = 0){

    global $wpdb;	
    
    
    if($by_vidsrc){
        $imdb_rows = $wpdb->get_results("
            SELECT  
            	postmeta_imdb.meta_value as imdb_id ,
                posts.ID
            FROM
                $wpdb->posts as posts
            LEFT JOIN $wpdb->postmeta as postmeta_imdb
            ON  postmeta_imdb.meta_key = 'ids' AND
                postmeta_imdb.post_id = posts.ID
            WHERE   posts.post_type = 'movies' AND 
                    posts.post_status = 'publish' AND 
                    posts.post_author = 333
            ");
    }else{
        $imdb_rows = $wpdb->get_results("
            SELECT  
        		postmeta_imdb.meta_value as imdb_id ,
        		qualities.quality
            FROM 
                wp_posts as posts
            LEFT JOIN $wpdb->postmeta as postmeta_imdb
            ON  postmeta_imdb.meta_key = 'ids' AND
                postmeta_imdb.post_id = posts.id
            LEFT JOIN (
                SELECT  posts.ID as post_id,
                        terms.name as quality
                FROM 
                    $wpdb->posts as posts
                LEFT JOIN $wpdb->term_relationships as term_rel
                ON 	term_rel.object_id = posts.id
                LEFT JOIN $wpdb->term_taxonomy as term_tax
                ON 	term_tax.term_taxonomy_id = term_rel.term_taxonomy_id AND
                    term_tax.taxonomy = 'dtquality'
                LEFT JOIN $wpdb->terms as terms
                ON 	terms.term_id = term_tax.term_id 
                WHERE   posts.post_type = 'movies' AND 
                        posts.post_status = 'publish' AND
                        terms.name IS NOT NULL
            ) as qualities
            ON	qualities.post_id = posts.ID
            WHERE   posts.post_type = 'movies' AND 
        			posts.post_status = 'publish'
                    ");
    
    }
    $all_imdb_arr = [];
    
    foreach($imdb_rows as $imdb_row){
        if($by_vidsrc)
            $all_imdb_arr[$imdb_row->imdb_id] = $imdb_row->ID;
        else
            $all_imdb_arr[$imdb_row->imdb_id] = $imdb_row->quality;
    }
                
    return $all_imdb_arr;
}


function get_movies_not_added(){
    ini_set("memory_limit","1024M");
    
    global $movies_add_limit;
    global $vidsrc_mov_file;
    global $indb_mov_file;
    
    $vidsrc_mov = file($vidsrc_mov_file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    $indb_mov = file($indb_mov_file ,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    
    $mov_diff = array_diff($vidsrc_mov , $indb_mov);
    
    
    $not_added_mov = [];
    $not_added_br = 0;
    foreach($mov_diff as $mov){
        $mov = trim($mov);
        if(strlen($mov)){
            $mov_tmp = new stdClass();
            $mov_tmp->imdb_id = $mov;
            $not_added_mov[$mov] = $mov_tmp;
            $not_added_br++;
            if($not_added_br > $movies_add_limit){
                break;
            }
        }
    }
    
    return $not_added_mov;
}



function get_movies_not_updated(){
    ini_set("memory_limit","1024M");
    
    global $wpdb;
    global $movies_add_limit;
    global $vidsrc_mov_file;

    if(!@is_numeric($movies_add_limit))
        $movies_add_limit = cron_add_limit('m');
    $sql = "
    SELECT
        postmeta_imdb.meta_value as imdb_id
    FROM
        $wpdb->posts as posts
	LEFT JOIN $wpdb->postmeta as postmeta_embed
	ON 	posts.ID = postmeta_embed.post_id AND 
        postmeta_embed.meta_value LIKE '%vidsrc.me%' AND 
        postmeta_embed.meta_key = 'repeatable_fields'
	LEFT JOIN $wpdb->postmeta as postmeta_imdb
	ON 	posts.ID = postmeta_imdb.post_id AND  
        postmeta_imdb.meta_key = 'ids'
    WHERE
    	posts.post_type = 'movies' AND 
        posts.post_status = 'publish' AND 
        postmeta_embed.post_id IS NULL AND 
        postmeta_imdb.post_id IS NOT NULL
	LIMIT ".$movies_add_limit;
    
    
    $not_updated_movs = $wpdb->get_results($sql);
    
    $not_updated_movs_tmp = [];
    foreach($not_updated_movs as $mov){
        $not_updated_movs_tmp[] = $mov->imdb_id;
    }
    $not_updated_movs = $not_updated_movs_tmp;
    unset($not_updated_movs_tmp);
    
    $vidsrc_mov = file($vidsrc_mov_file ,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    
    $not_updated_movs = array_intersect($not_updated_movs , $vidsrc_mov);
    
    unset($vidsrc_mov);
    
    $not_updated_movs_tmp = [];
    foreach($not_updated_movs as $mov){
        $mov_tmp = new stdClass();
        $mov_tmp->imdb_id = $mov;
        $not_updated_movs_tmp[$mov] = $mov_tmp;
    }
    unset($mov_tmp);
    $not_updated_movs = $not_updated_movs_tmp;
    unset($not_updated_movs_tmp);
    
    
    return $not_updated_movs;
}


function vidsrc_post_movie($vidsrc_data){

    global $movies_not_added;

    $imdb_id = $vidsrc_data->imdb_id;

    
    if(empty($imdb_id)){	return ;}

    $vidsrc_iframe = array();
    $vidsrc_iframe[] = array('name' => 'VidSrc','select' => 'iframe','idioma' => '','url'=>'https://vidsrc.me/embed/'.$imdb_id."/".getPlayerColor());

    $vidsrc_iframe_updating = array('name' => 'VidSrc','select' => 'iframe','idioma' => '','url'=>'https://vidsrc.me/embed/'.$imdb_id."/".getPlayerColor());


    global $wpdb;

    $db_post = $wpdb->get_row("
        SELECT  posts.* ,
                postmeta_imdb.meta_value as imdb_id
        FROM $wpdb->posts as posts
        LEFT JOIN $wpdb->postmeta as postmeta_imdb
        ON  postmeta_imdb.post_id = posts.ID AND
            postmeta_imdb.meta_key = 'ids'
        WHERE
            posts.post_status = 'publish' AND
            postmeta_imdb.meta_value = '$imdb_id'");	



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
        
        
        
        /*
        $terms = get_the_terms($db_post->ID , 'dtquality');
        
        if(@count($terms)){
            $term_ids = [];
            foreach($terms as $term){
                $term_ids[] = $term->term_id;
            }
            wp_remove_object_terms($db_post->ID , $term_ids , 'dtquality');	
        }
        
        
        
        $quality = $vidsrc_data->quality;
        
        
        $quality_cat_term = get_term_by( 'name', $quality, 'dtquality' );

        if($quality_cat_term){
            $quality_cat_id[] = $quality_cat_term->term_id;
        }else{
            $quality_cat_term = wp_insert_term($quality, 'dtquality');
            if($quality_cat_term){
                $quality_cat_id[] = $quality_cat_term['term_id'];
            }
        }
        
                
            
        wp_set_post_terms($db_post->ID,$quality_cat_id,'dtquality',false);
        */
        return 'update';
        
    }else{

        
        
        $vidsrc_mov_data = json_decode(curl_get_data('https://v2.vidsrc.me/api/m/'.$imdb_id));
        
        
        
        if(is_object($vidsrc_mov_data)){
            
            $movie_data = new stdClass();
            
            $movie_data->title = $vidsrc_mov_data->general->title;
            
            
            
            
            if(!empty($vidsrc_mov_data->tmdb->overview)){
                $movie_data->plot = $vidsrc_mov_data->tmdb->overview;
            }else{
                $movie_data->plot = $vidsrc_mov_data->general->plot;
            }
            
            
            
            $movie_data->poster = $vidsrc_mov_data->general->image;
            
            if(!empty($vidsrc_mov_data->tmdb->backdrop_path)){
                $movie_data->backdrop = 'https://image.tmdb.org/t/p/w780'.$vidsrc_mov_data->tmdb->backdrop_path;
            }
            
            if($vidsrc_mov_data->tmdb->runtime != 0){
                $movie_data->runtime = $vidsrc_mov_data->tmdb->runtime;
            }
            
            
            $movie_data->terms = [];
            
            
            // genres terms
            $movie_data->terms["genres"] = [];
            if(is_array($vidsrc_mov_data->tmdb->genres)){
                foreach($vidsrc_mov_data->tmdb->genres as $genre){
                    $movie_data->terms["genres"][] = $genre->name;
                }
            }elseif(count($vidsrc_mov_data->general->genres)){
                $movie_data->terms["genres"] = $vidsrc_mov_data->general->genres;
            }
            
            
            // quality terms
            //$movie_data->terms["dtquality"] = [$vidsrc_data->quality];
            
            
            // year terms
            if(!empty($vidsrc_mov_data->general->year)){
                $movie_data->terms["dtyear"] = $vidsrc_mov_data->general->year;
            }
            
            
            // directors terms
            $movie_data->terms["dtdirector"] = [];
            if(is_array($vidsrc_mov_data->tmdb->credits->crew)){
                foreach($vidsrc_mov_data->tmdb->credits->crew as $person){
                    if($person->department == "Directing" || $person->job == "Director"){
                        $movie_data->terms["dtdirector"][] = $person->name;
                    }
                }
            }
            if( is_array($vidsrc_mov_data->general->people->director) &&
                !count($movie_data->terms["dtdirector"])){
                foreach($vidsrc_mov_data->general->people->director as $person){
                    $movie_data->terms["dtdirector"][] = $person;
                }
            }
            
            
            
            // cast terms
            $movie_data->terms["dtcast"] = [];
            if(is_array($vidsrc_mov_data->tmdb->credits->cast)){
                $count = 0;
                foreach($vidsrc_mov_data->tmdb->credits->cast as $person){
                    $movie_data->terms["dtcast"][] = $person->name;
                    $count++;
                    if($count > 9)
                        break;
                }
            }
            if(is_array($vidsrc_mov_data->general->people->cast) &&
                !count($movie_data->terms["dtcast"])){
                $count = 0;
                foreach($vidsrc_mov_data->general->people->cast as $person){
                    $movie_data->terms["dtcast"][] = $person;
                    $count++;
                    if($count > 9)
                        break;
                }
            }
            
            
            
            $movie_data->meta = [];
            
            // imdb 
            $movie_data->meta['ids'] = $imdb_id;
            
            if(is_numeric($vidsrc_mov_data->general->imdb_rating))
                $movie_data->meta['imdbRating'] = $vidsrc_mov_data->general->imdb_rating;
            
            // tmdb
            if(is_numeric($vidsrc_mov_data->tmdb->id)){
                $movie_data->meta['idtmdb'] = $vidsrc_mov_data->tmdb->id;
            }
            
            if(!empty($vidsrc_mov_data->tmdb->poster_path)){
                $movie_data->meta['dt_poster'] = $vidsrc_mov_data->tmdb->poster_path;
            }
            
            if(!empty($vidsrc_mov_data->tmdb->backdrop_path)){
                $movie_data->meta['dt_backdrop'] = $vidsrc_mov_data->tmdb->backdrop_path;
            }
            
            if(isset($vidsrc_mov_data->tmdb->original_title)){
                $movie_data->meta['original_title'] = $vidsrc_mov_data->tmdb->original_title;
            }
            
            if(!empty($vidsrc_mov_data->tmdb->release_date)){
                $movie_data->meta['release_date'] = $vidsrc_mov_data->tmdb->release_date;
            }
            
            if(!empty($vidsrc_mov_data->tmdb->vote_average)){
                $movie_data->meta['vote_average'] = $vidsrc_mov_data->tmdb->vote_average;
            }
            
            if(!empty($vidsrc_mov_data->tmdb->vote_count)){
                $movie_data->meta['vote_count'] = $vidsrc_mov_data->tmdb->vote_count;
            }
            
            if(!empty($vidsrc_mov_data->tmdb->tagline)){
                $movie_data->meta['tagline'] = $vidsrc_mov_data->tmdb->tagline;
            }
            
            if(!empty($vidsrc_mov_data->tmdb->runtime)){
                $movie_data->meta['runtime'] = $vidsrc_mov_data->tmdb->runtime;
            }
            
            if(!empty($vidsrc_mov_data->tmdb->credits->cast)){
                $cast_meta_value = [];
                foreach($vidsrc_mov_data->tmdb->credits->cast as $casting){
                    if(empty($casting->profile_path))
                        $casting->profile_path = "null";
                    $cast_meta_value[] = "[".$casting->profile_path.";".$casting->name.",".$casting->character."]";
                }
            }
            if(is_array($cast_meta_value) && count($cast_meta_value))
                $movie_data->meta['dt_cast'] = implode("",$cast_meta_value);
            else
                $movie_data->meta['dt_cast'] = $cast_meta_value;
            
            
            if(!empty($vidsrc_mov_data->tmdb->credits->crew)){
                $dir_meta_value = [];
                foreach($vidsrc_mov_data->tmdb->credits->crew as $person){
                    if($person->department == "Directing" || $person->job == "Director"){
                        if(empty($person->profile_path))
                            $person->profile_path = "null";
                        
                        $dir_meta_value = "[".$person->profile_path.";".$person->name."]";
                    }
                }
            }
            if(is_array($dir_meta_value) && count($dir_meta_value))
                $movie_data->meta['dt_dir'] = implode("",$dir_meta_value);
            else
                $movie_data->meta['dt_dir'] = $dir_meta_value;
                
            
            $movie_data->meta['repeatable_fields'] = $vidsrc_iframe;
            
            
            
            
            $post_insert_data = array(
            'post_title'    => $movie_data->title,
            'post_content'  => $movie_data->plot,
            'post_status'   => 'publish',
            'post_type'   => 'movies',
            'post_author'   => "333",
        
            );
            
            $new_post_id = wp_insert_post( $post_insert_data );
            
            if(@is_numeric($new_post_id)){
                foreach($movie_data->meta as $meta_key => $meta_value){
                    add_post_meta($new_post_id,$meta_key,$meta_value, true);
                }
                foreach($movie_data->terms as $taxonomy => $terms){
                    setTerms($new_post_id , $terms , $taxonomy);
                }
            }else{
                exit("failed post insert movie");
            }
            
            
            return "insert";
            
            
            
        }
        return 0 ;
        

    }

}

