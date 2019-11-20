<?php
/**
 * @package ARC - Social Share
 * @version 1.0.0
 *
 */
class ARCSocialShare_Facebook {
	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated )
			self::init_hooks();
	}

	private static function init_hooks() {
		self::$initiated = true;
		add_action('admin_menu', array('ARCSocialShare_Facebook', 'add_facebook_settings_panel'));
		add_action('admin_init', array('ARCSocialShare_Facebook', 'arc_facebook_settings_fields'));
		add_action('publish_post', array('ARCSocialShare_Facebook', 'arc_facebook_share_update'), 10, 2);
	}

  /**
   * ARC Social Share - Facebook Settings Panel
   */
	public static function add_facebook_settings_panel() {
		add_submenu_page( 
			"arc-social-share-settings", 
			"ARC Social Share - Facebook Settings", 
			"Facebook API Settings", 
			"manage_options", 
			"arc-social-share-facebook-settings", 
			function () {
				echo '<div class="wrap">';
				echo '<h1>ARC Social Share - Facebook Settings</h1>';
				echo '<form method="post" enctype="multipart/form-data" action="options.php">';
				settings_fields("section_facebook");
				do_settings_sections("arc-social-share-facebook-settings");      
				submit_button(); 
				echo '</form>';
				echo '</div> '; 
			}
		);
	}

	public static function arc_facebook_app_id_context() {
		echo '<input 
			type="password" 
			class="regular-text" 
			id="arc_facebook_app_id" 
			name="arc_facebook_app_id" 
			value="' . get_option( 'arc_facebook_app_id' ) . '">';
	}

	public static function arc_facebook_app_secret_context() {
		echo '<input 
			type="password" 
			class="regular-text" 
			id="arc_facebook_app_secret" 
			name="arc_facebook_app_secret" 
			value="' . get_option( 'arc_facebook_app_secret' ) . '">';
	}
  
  public static function arc_facebook_page_access_token_context() {
		echo '<input 
			type="password" 
			class="regular-text" 
			id="arc_facebook_page_access_token" 
			name="arc_facebook_page_access_token" 
			value="' . get_option( 'arc_facebook_page_access_token' ) . '">';
	}

	public static function arc_facebook_settings_fields() {
		add_settings_section("section_facebook", null, null, "arc-social-share-facebook-settings");
		add_settings_field("arc_facebook_app_id", "App Id", array( __CLASS__, 'arc_facebook_app_id_context' ), "arc-social-share-facebook-settings", "section_facebook");
		add_settings_field("arc_facebook_app_secret", "App Secret", array( __CLASS__, 'arc_facebook_app_secret_context' ), "arc-social-share-facebook-settings", "section_facebook");
    add_settings_field("arc_facebook_page_access_token", "Page Access Token", array( __CLASS__, 'arc_facebook_page_access_token_context' ), "arc-social-share-facebook-settings", "section_facebook");
		register_setting("section_facebook", "arc_facebook_app_id");
		register_setting("section_facebook", "arc_facebook_app_secret");
    register_setting("section_facebook", "arc_facebook_page_access_token");
		register_setting("section_facebook", "include_script");
	}

	/**
   * ARC Social Share - Facebook API Caller
   */
	public static function arc_facebook_share_update( $post_id, $post ) {
		if ( get_post_meta( $post_id, 'arc_facebook_share_flag', true ) == 1 )
			return;

    if ( !in_array($post->post_type, get_option('arc_social_share_post_type')) )
      return;
      
    $params = array( 
      'link' => get_permalink( $post->ID ),
      'message' => $post->post_title
    );

    /** Facebook SDK **/
		$log_file     = ARC__PLUGIN_LOG . 'log/facebook.log';
		$app_id       = get_option( 'arc_facebook_app_id' );
		$app_secret   = get_option( 'arc_facebook_app_secret' );
		$access_token = get_option( 'arc_facebook_page_access_token' );

    require_once ARC__PLUGIN_DIR . 'lib/facebook/vendor/autoload.php';
		$fb = new \Facebook\Facebook([
      'app_id'                => $app_id,
      'app_secret'            => $app_secret,
      'default_graph_version' => 'v2.10',
    ]);

    error_log( "\nInitiating facebook share for post# {$post->ID}", 3,  $log_file);
    try {
      $fb->post('/me/feed', $params, $access_token);
      update_post_meta( $post_id, 'arc_facebook_share_flag', 1 );
			error_log( "\nSuccessfully post facebook share for post# {$post->ID}", 3,  $log_file);
    } catch(Facebook\Exceptions\FacebookResponseException $e) {
      update_post_meta( $post_id, 'arc_facebook_share_flag', 2 );
			error_log( "\nGraph returned an error:" . $e->getMessage(), 3,  $log_file);
    } catch(Facebook\Exceptions\FacebookSDKException $e) {
      update_post_meta( $post_id, 'arc_facebook_share_flag', 2 );
			error_log( "\nFacebook SDK returned an error:" . $e->getMessage(), 3,  $log_file);
    }
  }
  
  /**
   * ARC Social Share - Facebook share status viewer
   */
  public static function arc_facebook_share_status( $post_obj ) {
    switch (get_post_meta( $post_obj->ID , 'arc_facebook_share_flag', true )) {
      case 1:
        $color = '155724';
        $msg = 'Done! Shared on Facebook.';
        break;
      
      case 2:
        $color = '721c24';
        $msg = 'Error! Unable to share on Facebook...';
        break;
      
      default:
        $color = '0c5460';
        $msg = 'Waiting! Still not shared on Facebook.';
        break;
    }

		printf( '<div class="misc-pub-section misc-pub-section-facebook-share" style="color: #%1$s"><span class="dashicons"><svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
		viewBox="0 0 455.73 455.73" style="enable-background:new 0 0 455.73 455.73;" xml:space="preserve"><path style="fill:#3A559F;" d="M0,0v455.73h242.704V279.691h-59.33v-71.864h59.33v-60.353c0-43.893,35.582-79.475,79.475-79.475
		h62.025v64.622h-44.382c-13.947,0-25.254,11.307-25.254,25.254v49.953h68.521l-9.47,71.864h-59.051V455.73H455.73V0H0z"/>
		</svg></span>&nbsp;<strong>%2$s</strong></div>', $color, $msg );
	}
}