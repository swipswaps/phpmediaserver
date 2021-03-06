<?php
    
    //CRON FUNCTIONS
    
    function initializeCron(){
        //Result
        $result = FALSE;
        global $G_DATA;
        $cronid = 'cron_hour';
        
        if( O_CRON 
        && array_key_exists( 'cronlaunch', $G_DATA )
        ){
        
            if( !file_exists( PPATH_CRON_FILE ) ){
                file_put_contents( PPATH_CRON_FILE , 'CREATED' );
            }
            if( !file_exists( PPATH_CRON_HOUR_FILE ) ){
                file_put_contents( PPATH_CRON_HOUR_FILE , 'CREATED' );
            }
            
            //O_CRON_LONG_TIME
            $cmd = O_PHP . ' -f "' . PPATH_ACTIONS . DS . 'cronlong.php"';
            $cronid = 'cron';
            if( ( $data = sqlite_log_check_cron( $cronid, ( O_CRON_LONG_TIME ) ) ) != FALSE 
            && count( $data ) > 0
            ){
                
            }elseif( file_exists( PPATH_CRON_FILE )
            && sqlite_log_insert( $cronid, 'Cron ' . O_CRON_LONG_TIME .'mins launched: ' . date( 'Y-m-d H:s:i' ) ) !== FALSE 
            && run_in_background( $cmd, 0, PPATH_CRON_FILE ) 
            ){
                
            }
            
            //O_CRON_SHORT_TIME
            $cmd = O_PHP . ' -f "' . PPATH_ACTIONS . DS . 'cronshort.php"';
            $cronid = 'cron_hour';
            if( ( $data = sqlite_log_check_cron( $cronid, O_CRON_SHORT_TIME ) ) != FALSE 
            && count( $data ) > 0
            ){
                
                
            }elseif( file_exists( PPATH_CRON_HOUR_FILE )
            && sqlite_log_insert( $cronid, 'Cron ' . O_CRON_SHORT_TIME . 'mins launched: ' . date( 'Y-m-d H:s:i' ) ) !== FALSE 
            && run_in_background( $cmd, 0, PPATH_CRON_HOUR_FILE ) 
            ){
                run_in_background( O_CRON_JOB, 0 );
            }
        }elseif( O_CRON 
        && ( $data = sqlite_log_check_cron( $cronid, ( O_CRON_SHORT_TIME + 5 ) ) ) !== FALSE 
        && is_array( $data )
        && count( $data ) <= 0
        && sqlite_log_insert( $cronid, 'Cron ' . O_CRON_LONG_TIME .'mins launched: ' . date( 'Y-m-d H:s:i' ) ) !== FALSE 
        ){
            //first time, time*2
            //O_CRON_SHORT_TIME
            $cmd = O_PHP . ' -f "' . PPATH_ACTIONS . DS . 'cronshort.php"';
            run_in_background( $cmd, 0, PPATH_CRON_HOUR_FILE );
            run_in_background( O_CRON_JOB, 0 );
            
            //O_CRON_LONG_TIME
            $cmd = O_PHP . ' -f "' . PPATH_ACTIONS . DS . 'cronlong.php"';
            $cronid = 'cron';
            if( ( $data = sqlite_log_check_cron( $cronid, ( O_CRON_LONG_TIME ) ) ) != FALSE 
            && count( $data ) > 0
            ){
                
            }elseif( file_exists( PPATH_CRON_FILE )
            && sqlite_log_insert( $cronid, 'Cron ' . O_CRON_LONG_TIME .'mins launched: ' . date( 'Y-m-d H:s:i' ) ) !== FALSE 
            && run_in_background( $cmd, 0, PPATH_CRON_FILE ) 
            ){
                
            }
        }
        
        return $result;
    }
    
    function run_in_background( $Command, $Priority = 0,  $file = '/dev/null')
    {
        if( $Priority ){
            $cmd = "nice -n $Priority $Command > '" . $file . "' 2> /dev/null & echo $!";
        }else{
            $cmd = "$Command > '" . $file . "' 2> /dev/null & echo $!";
        }
        $PID = shell_exec( $cmd );
        
        return $PID;
    }
    
    //clean space X times Y files forced passed, no space check
	function cleanLowDiskSpace( $preview = TRUE, $maxpass = 10, $fileseachpass = 10 ){
        $G_SEARCH = '';
        
        //Autoclean Space on Low
        if( defined( 'O_WEBSCRAP_LIMIT_FREESPACE' ) 
        && defined( 'O_WEBSCRAP_LIMIT_FREESPACE_AUTOCLEAN' )
        && O_WEBSCRAP_LIMIT_FREESPACE_AUTOCLEAN != FALSE
        && ( $freespace = disk_free_space( PPATH_DOWNLOADS ) ) != FALSE
        && $freespace  < ( O_WEBSCRAP_LIMIT_FREESPACE * 1024 * 1024 * 1024 )
        ){
            $G_REMOVE = !$preview; //PREVIEW MODE
            $CLEANSIZE = ( O_WEBSCRAP_LIMIT_FREESPACE_AUTOCLEAN * 1024 * 1024 * 1024 );
            $MAXFILES = $fileseachpass; //delete X each time
            $MAXTIMES = $maxpass;
            while( ( $freespace = disk_free_space( PPATH_DOWNLOADS ) ) != FALSE
            && $freespace  < ( O_WEBSCRAP_LIMIT_FREESPACE * 1024 * 1024 * 1024 ) 
            ){
                echo "<br />(Pass:" . $MAXTIMES . ")LOW DISK FREE SPACE: " . formatSizeUnits( $freespace );
                echo "<br />Clean Size: " . formatSizeUnits( $CLEANSIZE );
                $MAXFILESNOW = $MAXFILES;
                
                if( ( $edata = sqlite_media_getdata_identify( $G_SEARCH, 1000000 ) ) ){
                    foreach( $edata AS $lrow ){
                        $file = $lrow[ 'file' ];
                        if( file_exists( $file ) ){
                            $filesize = filesize( $file );
                            if( $filesize > $CLEANSIZE ){
                                echo "<br />FILE: " . $file;
                                echo "<br />FILESIZE: " . formatSizeUnits( $filesize ) . "<br />";
                                if( $G_REMOVE ){
                                    if( @unlink( $file )
                                    ){
                                        echo "<br />---- FILE REMOVED: " . $file . "<br />";
                                    }else{
                                        echo "<br />!!!!! ERROR FILE REMOVED: " . $file . "<br />";
                                    }
                                }
                                $MAXFILESNOW--;
                            }else{
                                //echo " .";
                            }
                            if( $MAXFILESNOW <= 0 ){
                                break;
                            }
                        }
                    }
                }
                
                //New Clean Size (-0.1)
                $CLEANSIZE -= ( 0.1 * 1024 * 1024 * 1024 );
                $MAXTIMES--;
                if( $MAXTIMES <= 0 
                || $CLEANSIZE <= 0
                ){
                    break;
                }
            }
            
            if( $G_REMOVE ){
                //CLEAN DOWNLOAD MEDIA NOT EXIST
                echo "<br />" . date( 'Y-m-d H:i:s' );
                echo "<br />Clean Inexistents Downloads: ";
                echo "<br />";
                media_clean_downloads( 500, TRUE );
            }
        }
	}
	
    //clean space forced to min space needed from old to new files
	function cleanLowDiskSpaceOldFiles( $preview = TRUE, $maxfiles = 25 ){
        $G_SEARCH = '';
        
        //Autoclean Space on Low
        if( defined( 'O_WEBSCRAP_LIMIT_FREESPACE' ) 
        && defined( 'O_WEBSCRAP_LIMIT_FREESPACE_AUTOCLEAN_OLD' )
        && O_WEBSCRAP_LIMIT_FREESPACE_AUTOCLEAN_OLD != FALSE
        ){
            $filenum = 0;
            $LOWSPACE = O_WEBSCRAP_LIMIT_FREESPACE;
            while( $filenum < $maxfiles
            && ( $freespace = disk_free_space( PPATH_DOWNLOADS ) ) != FALSE
            && $freespace  < ( $LOWSPACE * 1024 * 1024 * 1024 )
            ){
                echo "<br />LOW DISK FREE SPACE: " . formatSizeUnits( $freespace );
                if( ( $file = sqlite_media_getdata_old_file( 1 ) ) != FALSE ){
                    //Get last file
                    if( array_key_exists( 0, $file ) ){
                        $file = $file[ 0 ];
                    }
                    if( array_key_exists( 'file', $file ) 
                    && file_exists( $file[ 'file' ] )
                    ){
                        echo "<br />FILE(" . $filenum . '/' . $maxfiles . "): " . $file[ 'file' ];
                        echo "<br />SIZE: " . formatSizeUnits( filesize( $file[ 'file' ] ) );
                        if( $preview ){
                            echo "<br />PREVIEW DELETE: " . $file[ 'file' ];
                        }else{
                            if( @unlink( $file[ 'file' ] ) ){
                                sqlite_media_delete( $file[ 'idmedia' ] );
                                echo "<br />DELETED: " . $file[ 'file' ];
                            }else{
                                echo "<br />ERROR DELETING: " . $file[ 'file' ];
                            }
                        }
                    }else{
                        echo "<br />FILE NOT EXIST: " . $file[ 'file' ];
                    }
                }
                $filenum++;
            }
        }
	}
	
	function cronLiveTV( $cronid = 'cron_7d' ){
        echo "<br />LIVETV Clean: " . $cronid;
        echo "<br />";
        //LiveTV Clean
        if( ( $da = sqlite_medialive_getdata( FALSE, 10000 ) ) 
        && is_array( $da )
        && array_key_exists( 0, $da )
        ){
            $URLs_OK = 0;
            $URLs_DEL = 0;
            $URLs_DEL_E = 0;
            foreach( $da AS $d ){
                if( ( $ldata = ffprobe_get_data( $d[ 'url' ] ) ) != FALSE 
                && is_array( $ldata )
                && array_key_exists( 'width', $ldata )
                && $ldata[ 'width' ] > 0
                ){
                    //echo get_msg( 'DEF_EXIST' );
                    $URLs_OK++;
                }else{
                    if( sqlite_medialive_delete( $d[ 'idmedialive' ] )
                    ){
                        //echo get_msg( 'DEF_DELETED' );
                        $URLs_DEL++;
                    }else{
                        //echo get_msg( 'DEF_DELETED_ERROR' );
                        $URLs_DEL_E++;
                    }
                }
            }
            echo get_msg( 'WEBSCRAP_ADDOK', FALSE ) . ' OKs: ' . $URLs_OK . '/Del:' . $URLs_DEL . '/DelError:' . $URLs_DEL_E;
        }else{
            echo get_msg( 'WEBSCRAP_ADDKO' );
        }
        
        //LiveTVUrls UPDATE
        echo "<br />LIVETVURLs Update: " . $cronid;
        echo "<br />";
        if( ( $da = sqlite_medialiveurls_getdata( FALSE, 10000 ) ) 
        && is_array( $da )
        && array_key_exists( 0, $da )
        ){
            $URLs_OK = 0;
            $URLs_DEL = 0;
            $URLs_DEL_E = 0;
            foreach( $da AS $d ){
                if( ( $ldata = @file_get_contents( $d[ 'url' ] ) ) != FALSE 
                && strlen( $ldata ) > 0
                ){
                    //echo get_msg( 'DEF_EXIST' );
                    //ADD URLS
                    $G_LISTLINKS = $ldata;
                    if( $G_LISTLINKS
                    && strlen( $G_LISTLINKS ) > 0
                    ){
                        $G_LISTLINKS = trim( $G_LISTLINKS );
                        $G_LISTLINKS = explode( PHP_EOL, $G_LISTLINKS );
                        $G_LISTLINKS = array_filter( $G_LISTLINKS, 'trim' );
                        $ltitle = '';
                        $URLs = 0;
                        $URLs_ERROR = 0;
                        $URLs_DUPLY = 0;
                        $LINES = count( $G_LISTLINKS );
                        foreach( $G_LISTLINKS AS $line ){
                            if( filter_var( $line, FILTER_VALIDATE_URL )
                            && sqlite_medialive_checkexist( $line ) != FALSE
                            ){
                                $URLs_DUPLY++;
                            }elseif( filter_var( $line, FILTER_VALIDATE_URL )
                            && sqlite_medialive_checkexist( $line ) == FALSE
                            && ( $ldata = ffprobe_get_data( $line ) ) != FALSE 
                            && is_array( $ldata )
                            && array_key_exists( 'width', $ldata )
                            && $ldata[ 'width' ] > 0
                            ){
                                //+1 url
                                if( $ltitle != FALSE ){
                                    if( strlen( $ltitle[ 'titleg' ] ) > 0 ){
                                        $lltitle = $ltitle[ 'titleg' ] . ': ' . $ltitle[ 'title' ];
                                    }else{
                                        $lltitle = $ltitle[ 'title' ];
                                    }
                                    $poster = $ltitle[ 'poster' ];
                                    if( ( $lastid = sqlite_medialive_insert( 0, $lltitle, $line, $poster ) ) != FALSE ){
                                        //download poster
                                        if( filter_var( $poster, FILTER_VALIDATE_URL ) ){
                                            $fileimg = PPATH_MEDIAINFO . DS . $lastid . '.livetv';
                                            downloadPosterToFile( $poster, $fileimg );
                                        }
                                        //echo get_msg( 'DEF_ELEMENTUPDATED' );
                                    }else{
                                        //echo get_msg( 'WEBSCRAP_ADDKO' );
                                        $URLs_ERROR++;
                                    }
                                    $ltitle = FALSE;
                                    $URLs++;
                                }
                            }elseif( startsWith( $line, '#EXTINF' ) ){
                                //extract title data
                                $ltitle = liveTVLineData( $line );
                            }elseif( filter_var( $line, FILTER_VALIDATE_URL ) ){
                                //no data valid
                                $URLs_ERROR++;
                            }else{
                                //no data valid
                            }
                        }
                        echo '<br />' . get_msg( 'WEBSCRAP_ADDOK', FALSE ) . ' URL: ' . $d[ 'url' ] . ' STATUS ' . $URLs . '/ERRORs:' . $URLs_ERROR . '/DUPLYs:' . $URLs_DUPLY . '/LINES:' . $LINES;
                    }else{
                        echo '<br />' . get_msg( 'WEBSCRAP_ADDKO' ) . ' URL: ' . $d[ 'url' ];
                    }
                    //END ADD
                    $URLs_OK++;
                }else{
                    $URLs_DEL++;
                    echo '<br />' . get_msg( 'WEBSCRAP_ADDKO' ) . ' URL: ' . $d[ 'url' ];
                }
            }
            //echo '<br />' . get_msg( 'WEBSCRAP_ADDOK', FALSE ) . ' OKs: ' . $URLs_OK . '/Del:' . $URLs_DEL . '/DelError:' . $URLs_DEL_E;
        }else{
            echo '<br />' . get_msg( 'WEBSCRAP_ADDKO' );
        }
    }
    
    function liveTVLineData( $data ){
        //extract info from #EXTINF: line
        $result = array( 
            'title' => '', 
            'poster' => '',
            'titleg' => '', 
        );
        
        //get title: *, TITLE
        $pg = '/.*,(.*)$/';
        if( ( preg_match_all( $pg, $data, $match ) ) !== FALSE 
        && count( $match ) > 0
        && array_key_exists( 1, $match )
        && is_array( $match[ 1 ] )
        && array_key_exists( 0, $match[ 1 ] )
        && strlen( trim( $match[ 1 ][ 0 ] ) ) > 0
        ){
            $result[ 'title' ] = trim( $match[ 1 ][ 0 ] );
        }else{
            $result[ 'title' ] = 'NO-TITLE';
        }
        //get poster: tvg-logo="urlimg"
        $pg = '/.*tvg-logo=\"(.*?)\".*$/';
        if( ( preg_match_all( $pg, $data, $match ) ) !== FALSE 
        && count( $match ) > 0
        && array_key_exists( 1, $match )
        && is_array( $match[ 1 ] )
        && array_key_exists( 0, $match[ 1 ] )
        && strlen( trim( $match[ 1 ][ 0 ] ) ) > 0
        && filter_var( trim( $match[ 1 ][ 0 ] ), FILTER_VALIDATE_URL )
        ){
            $result[ 'poster' ] = trim( $match[ 1 ][ 0 ] );
        }else{
            $result[ 'poster' ] = '';
        }
        //get titleg: group-title="TITLEG"
        $pg = '/.*group-title=\"(.*?)\".*$/';
        if( ( preg_match_all( $pg, $data, $match ) ) !== FALSE 
        && count( $match ) > 0
        && array_key_exists( 1, $match )
        && is_array( $match[ 1 ] )
        && array_key_exists( 0, $match[ 1 ] )
        && strlen( trim( $match[ 1 ][ 0 ] ) ) > 0
        ){
            $result[ 'titleg' ] = trim( $match[ 1 ][ 0 ] );
        }else{
            $result[ 'titleg' ] = '';
        }
        
        return $result;
    }
	
	function cleanDownloadsFolders( $echo = FALSE, $days = 1 ){
        $result = FALSE;
        
        //Clean folders with size below O_CRON_FOLDERS_CLEAN_LOWSIZE Mb and created more than $days
        if( defined( 'O_CRON_FOLDERS_CLEAN_LOWSIZE' ) 
        && O_CRON_FOLDERS_CLEAN_LOWSIZE != FALSE
        ){
            //( O_CRON_FOLDERS_CLEAN_LOWSIZE * 1024 * 1024 ) in Mb
            $size = O_CRON_FOLDERS_CLEAN_LOWSIZE * 1024 * 1024;
            $path = PPATH_DOWNLOADS;
            
            if( ( $folders = getFolders( $path ) ) != FALSE 
            ){
                foreach( $folders AS $folder ){
                    //if( $echo ) echo "<br />F:" . $folder;
                    //if( $echo ) echo "<br />FS:" . formatSizeUnits( get_dir_size( $folder ) );
                    if( file_exists( $folder ) 
                    && media_scan_exclude_folders( $folder ) == FALSE
                    && ( $fsize = get_dir_size( $folder ) ) !== FALSE
                    && $fsize <= $size
                    && ( $mtime = filemtime( $folder ) ) != FALSE
                    && ( time() - $mtime ) > ( $days * 60 * 60 * 24 )
                    && delTree( $folder )
                    ){
                        if( $echo ) echo "<br /><br />DEL: " . $folder;
                        if( $echo ) echo "<br />DATE MODIF:" . date( 'Y-m-d H:i:s', $mtime );
                        if( $echo ) echo "<br />FS:" . formatSizeUnits( $fsize );
                    }
                }
            }
            
            $result = TRUE;
        }
        
        return $result;
	}
	
	//DOWNLOAD SEARCHED ITEMS WITH DEFINED WEBSCRAPPERS
	
	function cron_searchs_downloads( $minutesback, $echo = TRUE ){
        global $G_WEBSCRAPPER;
        global $G_CLEAN_FILENAME;
        $maxurls = 5;
        $genres = implode( ' ', O_MENU_GENRES );
        $genres .= implode( ' ', array_keys( O_MENU_GENRES ) );
        $excludedwords = implode( ' ', $G_CLEAN_FILENAME );
        
        if( ( $logs = sqlite_log_getsearchs( $minutesback ) ) != FALSE 
        && is_array( $logs )
        && count( $logs ) > 0
        ){
            //var_dump( $logs );
            $searchs = array();
            $searched = array();
            foreach( $logs AS $log ){
                if( array_key_exists( 'url', $log ) 
                && strlen( $log[ 'url' ] ) > 0
                && ( $parts = parse_url( $log[ 'url' ] ) ) != FALSE
                && is_array( $parts )
                && array_key_exists( 'query', $parts )
                && strlen( $parts[ 'query' ] ) > 0
                ){
                    parse_str( $parts[ 'query' ], $query );
                    if( array_key_exists( 'search', $query )
                    && strlen( $query[ 'search' ] ) > 0
                    && ( $bword = get_word_better( urldecode( $query[ 'search' ] ) ) ) != FALSE
                    && !in_array( $bword, $searched )
                    && stripos( $excludedwords, $bword ) === FALSE
                    ){
                        $searchs[] = urldecode( $query[ 'search' ] );
                        $searched[] = $bword;
                    }
                }
            }
            //search terms on base for results
            $iadded = array();
            if( defined( 'O_CRON_WEBSCRAP_SEARCH' ) 
            && is_array( O_CRON_WEBSCRAP_SEARCH )
            ){
                $s = O_CRON_WEBSCRAP_SEARCH;
                $links = array();
                $searched = array();
                $PASS = 0;
                foreach( $searchs AS $search ){
                    shuffle( $s );
                    $bword = get_word_better( $search );
                    $quantity = 0;
                    if( $echo ) echo "<br />SEARCH: " . $search;
                    if( $echo ) echo "<br />SEARCH-BWORD: " . $bword;
                    foreach( $s AS $scrapper ){
                        if( !in_array( $bword, $searched ) ){
                            if( $echo ) echo "<br />WEBSEARCH: " . $scrapper;
                            $quantity += cron_searchs_downloads_get( $scrapper, $bword, $echo );
                            if( $maxurls <= $quantity ){
                                break;
                            }
                        }
                    }
                    //send search on server
                    $searched[] = $bword;
                }
            }
        }
	}
	
	function cron_searchs_downloads_get( $scrapper, $search, $echo = TRUE ){
        $result = 0;
        $genres = implode( ' ', O_MENU_GENRES );
        $genres .= implode( ' ', array_keys( O_MENU_GENRES ) );
        $PASS = 0;
        
        if( defined( 'O_CRON_WEBSCRAP_SEARCH' ) 
        && is_array( O_CRON_WEBSCRAP_SEARCH )
        && in_array( $scrapper, O_CRON_WEBSCRAP_SEARCH )
        ){
            $s = O_CRON_WEBSCRAP_SEARCH;
            $links = array();
            $iadded = array();
            if( $echo ) echo "<br />WS-SEARCH: " . $search;
            if( $echo ) echo "<br />WS-WEBSEARCH: " . $scrapper;
            //send search on server
            if( strlen( $search ) > 0
            && ( $links = webscrapp_search( $scrapper, $search, FALSE ) ) != FALSE 
            && count( $links ) > 0
            ){
                //TODO CHECK DUPLYS
                //add each title => link
                foreach( $links AS $ltitle => $lurl ){
                    if( !inString( $ltitle, $search ) 
                    //check its O_MENU_GENRES and exclude search
                    && stripos( $genres, $search ) === FALSE
                    ){
                        if( $echo ) echo "<br />";
                        if( $echo ) echo 'TITLE DIFFER: ' . $ltitle . ' => ' . $search;
                    }elseif( in_array( $lurl, $iadded ) ){
                        if( $echo ) echo "<br />";
                        if( $echo ) echo 'ADDED BEFORE: ' . $ltitle;
                    }elseif( webscrapp_pass( $scrapper, $PASS, $lurl, $ltitle, FALSE ) ){
                        if( $echo ) echo "<br />";
                        //if( $echo ) echo get_msg( 'WEBSCRAP_ADDOK', FALSE ) . ': ' . $scrapper . ' => ' . $ltitle . ' => ' . $lurl;
                        if( $echo ) echo get_msg( 'WEBSCRAP_ADDOK', FALSE ) . ': ' . $ltitle;
                        $iadded[] = $lurl;
                        $result++;
                    }else{
                        if( $echo ) echo "<br />";
                        //if( $echo ) echo get_msg( 'WEBSCRAP_ADDKO', FALSE ) . ': ' . $scrapper . ' => ' . $ltitle . ' => ' . $lurl;
                        if( $echo ) echo get_msg( 'WEBSCRAP_ADDKO', FALSE ) . ': ' . $ltitle;
                    }
                }
            }
        }
        
        return $result;
	}
?>
