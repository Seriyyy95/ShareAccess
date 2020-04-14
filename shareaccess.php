<?php

/*
 * Plugin Name: Share Access
 * Plugin URI: https://losst.ru
 * Description: Plugin helps to allow to users edit a certain posts
 * Version: 1.1
 * Author: Seriyyy95
 * Author URI: https://losst.ru
 */

register_activation_hook( __FILE__, function(){
        global $wpdb;
        $table_name = $wpdb->prefix . 'share_access';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
 id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
 post_id mediumint(9) NOT NULL,
 user_id mediumint(9) NOT NULL,
 PRIMARY KEY  (id)
 );";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
});

add_action('add_meta_boxes', 'shareaccess_add_custom_box');
function shareaccess_add_custom_box(){
	$screens = array( 'post', 'page' );
	if(current_user_can('administrator')){
		add_meta_box( 'shareaccess_select_author', 'Разрешить доступ к записи пользователю', 'shareaccess_meta_box_callback', $screens );
	}
}

function shareaccess_meta_box_callback( $post, $meta ){
        global $wpdb;
        $table_name = $wpdb->prefix . 'share_access';
	$post_id = $post->ID;

	$shared_to = $wpdb->get_row( "SELECT user_id FROM $table_name WHERE post_id=$post_id" );
	if(isset($shared_to->user_id)){
		$selected_user = $shared_to->user_id;
	}else{
		$selected_user = -1;
	}
	
	wp_dropdown_users( $args = array(
		"name" => "shareaccess_user",
		"show_option_none" => "Не выбран",
		"selected" => $selected_user,
	));
	wp_nonce_field( plugin_basename(__FILE__), 'shareaccess_nonce' );
}

add_action( 'save_post', 'shareaccess_save_postdata' );
function shareaccess_save_postdata( $post_id ) {
	if ( ! isset( $_POST['shareaccess_user'] ) )
		return;

	if ( ! wp_verify_nonce( $_POST['shareaccess_nonce'], plugin_basename(__FILE__) ) )
		return;

	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
		return;

	if( ! current_user_can( 'edit_post', $post_id ) )
		return;

	if( ! current_user_can( 'administrator' ) )
		return;

	$user_id = sanitize_text_field( $_POST['shareaccess_user'] );

	global $wpdb;
	$table_name = $wpdb->prefix . 'share_access';

	$wpdb->delete( $table_name, array( 'post_id' => $post_id) );
	if($user_id > -1){
		$wpdb->query("INSERT INTO $table_name (post_id, user_id) VALUES ('$post_id', '$user_id')");	
	}
}

function shareaccess_user_can_edit( $user_id, $post_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'share_access';

	$shared_to = $wpdb->get_row( "SELECT user_id FROM $table_name WHERE post_id=$post_id" );
	if(isset($shared_to->user_id)){
		if($user_id == $shared_to->user_id){
			return true;
		}
	}	
}

function shareaccess_user_can_edit_other( $user_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'share_access';

	$shared_to = $wpdb->get_row( "SELECT user_id FROM $table_name WHERE user_id=$user_id" );
	if(isset($shared_to->user_id)){
		return true;
	}else{
		return false;
	}
}

add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id, $args ) {
    $to_filter = array( 'edit_post', 'edit_others_posts' );

    // If the capability being filtered isn't of our interest, just return current value
    if ( ! in_array( $cap, $to_filter, true ) ) {
        return $caps;
    }

    if(isset($args[0])){
	$bool = shareaccess_user_can_edit( $user_id, $args[0] );
    }else{
	$bool = shareaccess_user_can_edit_other( $user_id );
    }

    if ($bool){
	return [ 'edit_posts' ];
    }
    // Otherwise just return current value
    return $caps;

}, 10, 4 );

function shareaccess_only_user_posts($query) {
    global $pagenow;
    global $wpdb;

    $table_name = $wpdb->prefix . 'share_access';
    if( 'edit.php' != $pagenow || !$query->is_admin )
        return $query;
    if(!$query->is_main_query())
        return $query;
    if(current_user_can('administrator')){
	return $query;
    }
    if(current_user_can('editor')){
	return $query;
    }

    global $user_ID;
    $shared_rows = $wpdb->get_results( "SELECT post_id FROM $table_name WHERE user_id=$user_ID" );
    $shared_ids = array();
    foreach($shared_rows as $row){
	$shared_ids[] = $row->post_id;
    }
    if(count($shared_ids) > 0){
	$args = array(
		'author'        =>  $user_ID, 
		'orderby'       =>  'post_date',
		'order'         =>  'ASC',
		'posts_per_page' => -1 
	);
	$authors_query = new WP_Query($args);
	$author_ids = wp_list_pluck($authors_query->posts, 'ID');
	$shared_ids = array_merge($shared_ids, $author_ids);
    	$query->set('post__in', $shared_ids );
    }else{
    	$query->set('author', $user_ID );
    }
    return $query;
}
add_filter('pre_get_posts', 'shareaccess_only_user_posts');
