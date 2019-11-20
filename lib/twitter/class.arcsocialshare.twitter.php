<?php
/**
 * @package ARC - Social Share
 * @version 1.0.0
 *
 */
class ARCSocialShare_Twitter {
	private static $initiated = false;
	
	public static function init() {
		if ( ! self::$initiated )
			self::init_hooks();
	}

	private static function init_hooks() {
		self::$initiated = true;
		add_action('admin_menu', array('ARCSocialShare_Twitter', 'add_twitter_settings_panel'));
		add_action('admin_init', array('ARCSocialShare_Twitter', 'arc_twitter_settings_fields'));
		add_action('publish_post', array('ARCSocialShare_Twitter', 'arc_twitter_share_update'), 10, 2);
	}

  /**
   * ARC Social Share - Twitter Settings Panel
   */
	public static function add_twitter_settings_panel() {
		add_submenu_page( 
			"arc-social-share-settings", 
			"ARC Social Share - Twitter Settings", 
			"Twitter API Settings", 
			"manage_options", 
			"arc-social-share-twitter-settings", 
			function () {
				echo '<div class="wrap">';
				echo '<h1>ARC Social Share - Twitter Settings</h1>';
				echo '<form method="post" enctype="multipart/form-data" action="options.php">';
				settings_fields("section_twitter");
				do_settings_sections("arc-social-share-twitter-settings");      
				submit_button(); 
				echo '</form>';
				echo '</div> '; 
			}
		);
	}

	public static function arc_twitter_consumer_key_context() {
		echo '<input 
			type="password" 
			class="regular-text" 
			id="arc_twitter_consumer_key" 
			name="arc_twitter_consumer_key" 
			value="' . get_option( 'arc_twitter_consumer_key' ) . '">';
	}

	public static function arc_twitter_consumer_secret_context() {
		echo '<input 
			type="password" 
			class="regular-text" 
			id="arc_twitter_consumer_secret" 
			name="arc_twitter_consumer_secret" 
			value="' . get_option( 'arc_twitter_consumer_secret' ) . '">';
	}

	public static function arc_twitter_access_token_context() {
		echo '<input 
			type="password" 
			class="regular-text" 
			id="arc_twitter_access_token" 
			name="arc_twitter_access_token" 
			value="' . get_option( 'arc_twitter_access_token' ) . '">';
	}

	public static function arc_twitter_access_secret_context() {
		echo '<input 
			type="password" 
			class="regular-text" 
			id="arc_twitter_access_secret" 
			name="arc_twitter_access_secret" 
			value="' . get_option( 'arc_twitter_access_secret' ) . '">';
	}

	public static function arc_twitter_settings_fields() {
		add_settings_section("section_twitter", null, null, "arc-social-share-twitter-settings");
		add_settings_field("arc_twitter_consumer_key", "Consumer Key", array( __CLASS__, 'arc_twitter_consumer_key_context' ), "arc-social-share-twitter-settings", "section_twitter");
		add_settings_field("arc_twitter_consumer_secret", "Consumer Secret Key", array( __CLASS__, 'arc_twitter_consumer_secret_context' ), "arc-social-share-twitter-settings", "section_twitter");
		add_settings_field("arc_twitter_access_token", "Access Token", array( __CLASS__, 'arc_twitter_access_token_context' ), "arc-social-share-twitter-settings", "section_twitter");
		add_settings_field("arc_twitter_access_secret", "Access Secret", array( __CLASS__, 'arc_twitter_access_secret_context' ), "arc-social-share-twitter-settings", "section_twitter"); 
		register_setting("section_twitter", "arc_twitter_consumer_key");
		register_setting("section_twitter", "arc_twitter_consumer_secret");
		register_setting("section_twitter", "arc_twitter_access_token");
		register_setting("section_twitter", "arc_twitter_access_secret");
		register_setting("section_twitter", "include_script");
	}

  /**
   * ARC Social Share - Twitter API Caller
   */
	public static function arc_twitter_share_update( $post_id, $post ) {
		if ( get_post_meta( $post_id, 'arc_twitter_share_flag', true ) == 1 )
			return;

    if ( !in_array($post->post_type, get_option('arc_social_share_post_type')) )
			return;

		$params = array( 'status' => $post->post_title . ' ' . get_permalink( $post->ID ) );

		/** Twitter SDK **/
		$log_file        = ARC__PLUGIN_LOG . 'log/twitter.log';
		$consumer_key    = get_option( 'arc_twitter_consumer_key' );
		$consumer_secret = get_option( 'arc_twitter_consumer_secret' );
		$access_token    = get_option( 'arc_twitter_access_token' );
		$access_secret   = get_option( 'arc_twitter_access_secret' );

		require_once(ARC__PLUGIN_DIR . 'lib/twitter/codebird-php/src/codebird.php');
		\Codebird\Codebird::setConsumerKey( $consumer_key, $consumer_secret);
		$cb = \Codebird\Codebird::getInstance();
		$cb->setToken( $access_token, $access_secret);

		error_log( "\nInitiating tweet update for post# {$post->ID}", 3,  $log_file);
		$response = $cb->statuses_update($params);
		if( $response->httpstatus == 200 ) {
			update_post_meta( $post_id, 'arc_twitter_share_flag', 1 );
			update_post_meta( $post_id, 'arc_twitter_share_id', $response->id );
			error_log( "\nSuccessfully update tweet for post# {$post->ID}", 3,  $log_file);
		} else {
			update_post_meta( $post_id, 'arc_twitter_share_flag', 2 );
			error_log( "\nError occured during tweet update for post# {$post->ID}", 3,  $log_file);
			error_log( "\n" . print_r( $response, true ), 3,  $log_file);
		}
  }
  
  /**
   * ARC Social Share - Twitter share status viewer
   */
  public static function arc_twitter_share_status( $post_obj ) {
    switch (get_post_meta( $post_obj->ID , 'arc_twitter_share_flag', true )) {
      case 1:
        $color = '155724';
        $msg = 'Done! Shared on Twitter.';
        break;
      
      case 2:
        $color = '721c24';
        $msg = 'Error! Unable to share on Twitter...';
        break;
      
      default:
        $color = '0c5460';
        $msg = 'Waiting! Still not shared on Twitter.';
        break;
    }
    
		printf( '<div class="misc-pub-section misc-pub-section-twitter-share" style="color: #%1$s"><span class="dashicons"><svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
		viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve"><path style="fill:#03A9F4;" d="M512,97.248c-19.04,8.352-39.328,13.888-60.48,16.576c21.76-12.992,38.368-33.408,46.176-58.016
		c-20.288,12.096-42.688,20.64-66.56,25.408C411.872,60.704,384.416,48,354.464,48c-58.112,0-104.896,47.168-104.896,104.992
		c0,8.32,0.704,16.32,2.432,23.936c-87.264-4.256-164.48-46.08-216.352-109.792c-9.056,15.712-14.368,33.696-14.368,53.056
		c0,36.352,18.72,68.576,46.624,87.232c-16.864-0.32-33.408-5.216-47.424-12.928c0,0.32,0,0.736,0,1.152
		c0,51.008,36.384,93.376,84.096,103.136c-8.544,2.336-17.856,3.456-27.52,3.456c-6.72,0-13.504-0.384-19.872-1.792
		c13.6,41.568,52.192,72.128,98.08,73.12c-35.712,27.936-81.056,44.768-130.144,44.768c-8.608,0-16.864-0.384-25.12-1.44
		C46.496,446.88,101.6,464,161.024,464c193.152,0,298.752-160,298.752-298.688c0-4.64-0.16-9.12-0.384-13.568
		C480.224,136.96,497.728,118.496,512,97.248z"/></svg></span>&nbsp;<strong>%2$s</strong></div>', $color, $msg );
	}
}