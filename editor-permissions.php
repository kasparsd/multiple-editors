<?php
/*
	Plugin Name: Multiple Editors
	Plugin URI: 
	Description: Allow multiple editors and contributors per post or page.
	Author: Metronet
	Version: 0.1
	Author URI: http://metronet.no
*/

multiple_editors::instance();


class multiple_editors {


	static function instance() {
		
		static $instance;

		if ( ! $instance )
			$instance = new self;
		
		return $instance;

	}


	private function __construct() {

		// Hook into user capabilities check
		add_action( 'init', array( $this, 'maybe_init_editor_caps' ) );

		// Add a UI to allow "real" editors adding custom editors
		add_action( 'add_meta_boxes', array( $this, 'custom_editor_metabox_init' ) );
		add_action( 'save_post', array( $this, 'save_post' ) );

	}


	function maybe_init_editor_caps() {

		// Filter caps only for logged-in users
		if ( is_user_logged_in() )
			add_filter( 'user_has_cap', array( $this, 'maybe_custom_editor_cap' ), 15, 3 );

	}


	function maybe_custom_editor_cap( $all, $caps, $args ) {
	
		global $current_user;
		global $post;

		if ( empty( $caps ) )
			return $all;

		// Get the associated object ID that the user might be allowed to edit
		if ( ! empty( $post ) ) 
			$post_id = $post->ID;
		elseif ( isset( $args[2] ) && is_numeric( $args[2] ) ) 
			$post_id = $args[2];
		else
			$post_id = null;

		switch ( $caps[0] ) {
			case 'upload_files':
				
				// Allow attaching and uploading files
				$all[ $caps[0] ] = true;
				
				break;

			case 'edit_pages':
			case 'edit_posts':

				// Allow adding new pages
				if ( ! isset( $args[2] ) )
					$all[ $caps[0] ] = true;

				break;

			case 'edit_page':
			case 'edit_post':
			case 'edit_others_pages':
			case 'edit_others_posts':
			case 'edit_published_pages':
			case 'edit_published_pages':

				// Make sure we verify permissions for posts/pages with valid object ID.
				// We pass null as object id when checking if user is a custom_editor.
				if ( isset( $args[2] ) && is_numeric( $args[2] ) ) {

					// Edit all pages where the user is listed
					$editors = (array) get_post_meta( $post_id, 'custom_editors', true );

					if ( in_array( $current_user->ID, $editors ) ) {
						foreach ( (array) $caps as $cap ) {
							$all[ $cap ] = true;
						}
					}

				}

				break;

		}

		return $all;

	}


	function custom_editor_metabox_init() {

		// Check if current user is not a custom editor already
		if ( ! current_user_can( 'edit_post', null ) )
			return;

		$post_types = get_post_types();

		foreach ( $post_types as $post_type ) {

			add_meta_box( 
				'ppeditor', 
				__( 'Contributors' ), 
				array( $this, 'custom_editor_metabox' ), 
				$post_type,
				'side' 
			);

		}
		
	}


	function custom_editor_metabox( $post ) {

		$current_used_id = get_current_user_id();
		$whitelist_roles = array( 'contributor', 'author' );
		$editors = (array) get_post_meta( $post->ID, 'custom_editors', true );

		$users = get_users( array( 
				'roles' => $whitelist_roles,
				'fields' => array( 'ID', 'user_login', 'display_name' ),
				'exclude' => array( $current_used_id )
			) );

		$users_html = array();

		foreach ( $users as $user ) {
			
			$users_html[] = sprintf( 
				'<li><label><input type="checkbox" name="custom_editors[]" value="%s" %s>%s (%s)</label></li>',
				$user->ID,
				checked( in_array( $user->ID, $editors ), true, false ),
				esc_html( $user->display_name ),
				esc_html( $user->user_login )
			);

		}

		if ( empty( $users_html ) ) {

			printf( 
				'<p>%s</p>',
				__( 'All users already have the edit access.' )
			);

		} else {

			printf( 
				'<div id="custom_editors">
					<ul>%s</ul>
				</div>',
				implode( '', $users_html )
			);

		}

		echo '<style type="text/css">
				#custom_editors { padding:0 1em; border:1px solid #ddd; max-height:200px; overflow:auto; }
				#custom_editors label { padding-left:1.5em; display:block; }
				#custom_editors input { float:left; display:inline-block; margin:0 -2em 0 -1.5em; }
			</style>';

	}


	function save_post( $post_id ) {

		// Update custom editors only if current user is not a contributor already
		if ( ! current_user_can( 'edit_post', null ) )
			return;

		if ( isset( $_POST['custom_editors'] ) )
			update_post_meta( $post_id, 'custom_editors', $_POST['custom_editors'] );
		else
			delete_post_meta( $post_id, 'custom_editors' );

	}


}

