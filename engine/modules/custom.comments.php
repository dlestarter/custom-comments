<?php
/*
=====================================================
 MWS Custom Comments v1.0 - Mehmet Hanoğlu
-----------------------------------------------------
 http://dle.net.tr/ -  Copyright (c) 2015
-----------------------------------------------------
 Mail: mehmethanoglu@dle.net.tr
-----------------------------------------------------
 Lisans : MIT License
=====================================================
*/

if ( ! defined( 'DATALIFEENGINE' ) ) {
	die( "Hacking attempt!" );
}

$comm_conf = array(
	'sel_user_info' => "1",		// Get user info
	'sel_news_info' => "1",		// Get news info
	'prev_text_len' => 100,		// Preview text length
);

if ( $comm_conf['sel_news_info'] ) {

	function comm_fulllink( $id, $category, $alt_name, $date ) {
		global $config;
		if ( $config['allow_alt_url'] ) {
			if ( $config['seo_type'] == 1 OR $config['seo_type'] == 2 ) {
				if ( $category and $config['seo_type'] == 2 ) {
					$full_link = $config['http_home_url'] . get_url( $category ) . "/" . $id . "-" . $alt_name . ".html";
				} else {
					$full_link = $config['http_home_url'] . $id . "-" . $alt_name . ".html";
				}
			} else {
				$full_link = $config['http_home_url'] . date( 'Y/m/d/', $date ) . $alt_name . ".html";
			}
		} else {
			$full_link = $config['http_home_url'] . "index.php?newsid=" . $id;
		}
		return $full_link;
	}

	function comm_title( $count, $title ) {
		global $config;
		if ( $count AND dle_strlen( $title, $config['charset'] ) > $count ) {
			$title = dle_substr( $title, 0, $count, $config['charset'] );
			if ( ($temp_dmax = dle_strrpos( $title, ' ', $config['charset'] )) ) $title = dle_substr( $title, 0, $temp_dmax, $config['charset'] );
		}
		return $title;
	}
}


function custom_comments( $matches = array() ) {
	global $db, $_TIME, $config, $lang, $user_group, $comm_conf;

	if ( ! count( $matches ) ) return "";
	$yes_no_map = array( "yes" => "1", "no" => "0" );

	$param_str = trim( $matches[1] );
	$thisdate = date( "Y-m-d H:i:s", $_TIME );
	$where = array();

	if ( preg_match( "#template=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$comm_tpl = trim( $match[1] );
	} else return "";

	if ( preg_match( "#approve=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$where[] = "c.approve='" . $yes_no_map[ $match[1] ] . "'";
	}
	if ( preg_match( "#author=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$where[] = "c.autor='" . $db->safesql( trim( $match[1] ) ) . "'";
	}
	if ( preg_match( "#users=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$where[] = "c.is_register='" . $yes_no_map[ $match[1] ] . "'";
	}
	if ( preg_match( "#days=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$days = intval( trim( $match[1] ) );
		$where[] = "c.date >= '{$thisdate}' - INTERVAL {$days} DAY AND c.date < '{$thisdate}'";
	} else $days = 0;

	if ( preg_match( "#id=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$temp_array = array();
		$where_id = array();
		$match[1] = explode( ',', trim( $match[1] ) );
		foreach ( $match[1] as $value ) {
			if ( count( explode( '-', $value ) ) == 2 ) {
				$value = explode( '-', $value );
				$where_id[] = "id >= '" . intval( $value[0] ) . "' AND id <= '" . intval( $value[1] ) . "'";
			} else $temp_array[] = intval($value);
		}
		if ( count( $temp_array ) ) {
			$where_id[] = "id IN ('" . implode( "','", $temp_array ) . "')";
		}
		if ( count( $where_id ) ) { 
			$custom_id = implode( ' OR ', $where_id );
			$where[] = $custom_id;
		}
	}

	if ( preg_match( "#news=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$temp_array = array();
		$where_id = array();
		$match[1] = explode( ',', trim( $match[1] ) );
		foreach ( $match[1] as $value ) {
			if ( count( explode( '-', $value ) ) == 2 ) {
				$value = explode( '-', $value );
				$where_id[] = "c.post_id >= '" . intval( $value[0] ) . "' AND c.post_id <= '" . intval( $value[1] ) . "'";
			} else $temp_array[] = intval($value);
		}
		if ( count( $temp_array ) ) {
			$where_id[] = "c.post_id IN ('" . implode( "','", $temp_array ) . "')";
		}
		if ( count( $where_id ) ) { 
			$custom_id = implode( ' OR ', $where_id );
			$where[] = $custom_id;
		}
	}

	if ( preg_match( "#from=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$comm_from = intval( $match[1] ); $custom_all = $custom_from;
	} else {
		$comm_from = 0; $custom_all = 0;
	}
	if ( preg_match( "#limit=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$comm_limit = intval( $match[1] );
	} else {
		$comm_limit = $config['comm_nummers'];
	}

	if ( preg_match( "#order=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$allowed_order = array ( 'postid' => 'post_id', 'date' => 'date', 'author' => 'autor', 'rand' => 'RAND()' );
		if ( $allowed_order[ $match[1] ] ) $comm_order = $allowed_order[ $match[1] ];
	}
	if ( preg_match( "#sort=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$allowed_sort = array ( 'asc' => 'ASC', 'desc' => 'DESC' );
		if ( $allowed_sort[ $match[1] ] ) $comm_sort = $allowed_sort[ $match[1] ];
	}

	if ( preg_match( "#cache=['\"](.+?)['\"]#i", $param_str, $match ) ) {
		$comm_cache = $yes_no_map[ $match[1] ];
	} else {
		$comm_cache = "0";
	}

	if ( $comm_conf['sel_user_info'] ) {
		$u_select = ", u.foto, u.user_group, u.comm_num, u.news_num"; $u_from = " LEFT JOIN " . PREFIX . "_users u ON ( c.user_id = u.user_id )";
	} else {
		$u_select = ""; $u_from = "";
	}
	if ( $comm_conf['sel_news_info'] ) {
		$p_select = ", p.title, p.category, p.alt_name"; $p_from = " LEFT JOIN " . PREFIX . "_post p ON ( c.post_id = p.id )";
	} else {
		$p_select = ""; $p_from = "";
	}

	$comm_yes = false;
	$comm_sql = "SELECT c.*{$u_select}{$p_select} FROM " . PREFIX . "_comments c{$u_from}{$p_from} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$comm_order} {$comm_sort} LIMIT {$comm_from},{$comm_limit}";
	$comm_que = $db->query( $comm_sql );

	if ( $comm_cache ) {
		$comm_cacheid = $param_str . $comm_sql;
		$cache_content = dle_cache( "comm_custom", $comm_cacheid, true );
	} else $cache_content = false;
	if ( ! $cache_content ) {

		$tpl = new dle_template();
		$tpl->dir = TEMPLATE_DIR;
		$tpl->load_template( $comm_tpl . '.tpl' );

		while( $comm_row = $db->get_row( $comm_que ) ) {
			$comm_yes = true;

			if ( $config['allow_links'] AND function_exists('replace_links') AND isset( $replace_links['comments'] ) ) $comm_row['text'] = replace_links( $comm_row['text'], $replace_links['comments'] );
			if ( $user_group[$member_id['user_group']]['allow_hide'] ) $comm_row['text'] = str_ireplace( "[hide]", "", str_ireplace( "[/hide]", "", $comm_row['text']) );
			else $comm_row['text'] = preg_replace ( "#\[hide\](.+?)\[/hide\]#is", "<div class=\"quote\">" . $lang['news_regus'] . "</div>", $comm_row['text'] );
			$tpl->set( '{text}', stripslashes( $comm_row['text'] ) );

			if ( date( 'Ymd', $comm_row['date'] ) == date( 'Ymd', $_TIME ) ) {
				$tpl->set( '{date}', $lang['time_heute'] . langdate( ", H:i", $comm_row['date'] ) );
			} else if ( date( 'Ymd', $comm_row['date'] ) == date( 'Ymd', ( $_TIME - 86400 ) ) ) {
				$tpl->set( '{date}', $lang['time_gestern'] . langdate( ", H:i", $comm_row['date'] ) );
			} else {
				$tpl->set( '{date}', $comm_row['date'] );
			}
			$news_date = $comm_row['date'];
			$tpl->copy_template = preg_replace_callback( "#\{date=(.+?)\}#i", "formdate", $tpl->copy_template );

			if ( $comm_conf['sel_user_info'] ) {
				if ( count( explode( "@", $comm_row['foto'] ) ) == 2 ) {
					$tpl->set( '{author-foto}', 'http://www.gravatar.com/avatar/' . md5( trim( $comm_row['foto'] ) ) . '?s=' . intval( $user_group[$comm_row['user_group']]['max_foto'] ) );
				} else {
					if ( $comm_row['foto'] and ( file_exists( ROOT_DIR . "/uploads/fotos/" . $comm_row['foto'] ) ) ) $tpl->set( '{author-foto}', $config['http_home_url'] . "uploads/fotos/" . $comm_row['foto'] );
					else $tpl->set( '{author-foto}', "{THEME}/dleimages/noavatar.png" );
				}
				$tpl->set( "{author-colored}", $user_group[ $comm_row['user_group'] ]['group_prefix'] . $comm_row['autor'] . $user_group[ $comm_row['user_group'] ]['group_suffix'] );
				$tpl->set( "{author-group}", $user_group[ $comm_row['user_group'] ]['group_prefix'] . $user_group[ $comm_row['user_group'] ]['group_name'] . $user_group[ $comm_row['user_group'] ]['group_suffix'] );
				$tpl->set( "{author-group-icon}", $user_group['icon'] );
				$tpl->set( "{author-news}", intval( $comm_row['news_num'] ) );
				$tpl->set( "{author-comm}", intval( $comm_row['comm_num'] ) );
			} else {
				$tpl->set( "", array( "{author-comm}" => "", "{author-news}" => "", "{author-group-icon}" => "", "{author-group}" => "", "{author-foto}" => "" ) );
			}
			if ( $comm_conf['sel_news_info'] ) {
				if ( preg_match( "#\\{news-title limit=['\"](.+?)['\"]\\}#i", $tpl->copy_template, $matches ) ) { $count = intval( $matches[1] ); $tpl->set( $matches[0], comm_title( $count, $comm_row['title'] ) ); }
				else $tpl->set( '{news-title}', strip_tags( stripslashes( $comm_row['title'] ) ) );
				$tpl->set( '{news-link}', comm_fulllink( $comm_row['post_id'], $comm_row['category'], $comm_row['alt_name'], $comm_row['pdate'] ) );
				$tpl->set( '{news-cat}', get_categories( $comm_row['category'] ) );
			}
			$tpl->set( "{text-preview}", dle_substr( strip_tags( stripslashes( $comm_row['text'] ) ), 0, $comm_conf['prev_text_len'], $config['charset'] ) );
			$tpl->set( "{author-id}", $comm_row['user_id'] );
			$tpl->set( "{author-url}", ( $config['allow_alt_url'] ) ? $config['http_home_url'] . "user/" . urlencode( $comm_row['autor'] ) : $config['http_home_url'] . "index.php?subaction=userinfo&amp;user=" . urlencode( $comm_row['autor'] ) );
			$tpl->set( "{author}", $comm_row['autor'] );
			$tpl->set( "{approve}", $comm_row['approve'] );
			if ( $comm_row['is_register'] ) { $tpl->set( "[registered]", "" ); $tpl->set( "[/registered]", "" ); }
			else { $tpl->set_block( "'\\[registered\\](.*?)\\[/registered\\]'si", "" ); }
			$tpl->set( "{is_register}", $comm_row['is_register'] );
			$tpl->set( "{email}", $comm_row['email'] );
			$tpl->set( "{news-id}", $comm_row['post_id'] );
			$tpl->set( "{ip}", $comm_row['ip'] );
			$tpl->set( "{id}", $comm_row['id'] );

	    	$tpl->compile( "content" );
		}

		if ( $comm_cache ) {
			create_cache( "comm_custom", $tpl->result['content'], $comm_cacheid, true );
		}
		return $tpl->result['content'];
	} else return $cache_content;

}

?>
