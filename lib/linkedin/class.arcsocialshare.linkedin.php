<?php
/**
 * @package ARC - Social Share
 * @version 1.0.0
 *
 */
require_once ARC__PLUGIN_DIR . 'lib/linkedin/vendor/autoload.php';
use GuzzleHttp\Client;

class ARCSocialShare_LinkedIn {
  private static $initiated = false;

  public static function init() {
    if ( ! self::$initiated )
      self::init_hooks();
  }

  private static function init_hooks() {
    self::$initiated = true;
    add_action('admin_menu', array('ARCSocialShare_LinkedIn', 'add_linkedin_settings_panel'));
    add_action('admin_init', array('ARCSocialShare_LinkedIn', 'arc_linkedin_settings_fields'));
    add_action('publish_post', array('ARCSocialShare_LinkedIn', 'arc_linkedin_share_update'), 10, 2);
  }

  /**
   * LinkedIn Authorization panel
   */
  public static function arc_linkedin_auth_panel() {
    if ( "success" === $_REQUEST['status'] ) 
      echo '<div class="notice notice-success is-dismissible"><p>Done! You\'ve successfully authenticate with LinkedIn API.</p></div>';
    elseif ( "error" === $_REQUEST['status'] ) 
      echo '<div class="notice notice-error is-dismissible"><pre>' . $_REQUEST['message'] . '</pre></div>';
    
    if ( !get_option('arc_linkedin_access_token') ) {
      $state        = substr( str_shuffle( "0123456789abcHGFRlki" ), 0, 10 );
      $client_id    = get_option( 'arc_linkedin_client_id' );
      $redirect_uri = get_option( 'arc_linkedin_redirect_uri' );
      $scopes       = 'rw_company_admin,r_emailaddress,r_liteprofile,w_member_social';

      $url = "https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=".$client_id."&redirect_uri=".$redirect_uri."&scope=".$scopes."&state=".$state;

      echo '<h2>Authenticate with LinkedIn</h2>';
      echo '<p>Please, click the below link to authenticate your LinkedIn profile.</p>';
      printf('<a href="%1$s" rel="noopener noreferrer" target="_blank">Login with LinkedIn</a>', $url);
    } else {
      echo '<p>Done! You\'ve successfully authenticate with LinkedIn API.</p>';
    }
  }

  /**
   * ARC Social Share - LinkedIn Settings Panel
   */
  public static function add_linkedin_settings_panel() {
  	add_submenu_page( 
  		"arc-social-share-settings", 
  		"ARC Social Share - LinkedIn Settings", 
  		"LinkedIn API Settings", 
  		"manage_options", 
  		"arc-social-share-linkedin-settings", 
  		function () {
        echo '<div class="wrap">';
        echo '<h1>ARC Social Share - LinkedIn Settings</h1>';
        self::arc_linkedin_auth_panel();
        echo '<form method="post" enctype="multipart/form-data" action="options.php">';
        settings_fields("section_linkedin");
        do_settings_sections("arc-social-share-linkedin-settings");      
        submit_button(); 
        echo '</form>';
        echo '</div> '; 
      }
    );
  }

  public static function arc_linkedin_client_id_context() {
		echo '<input 
			type="password" 
			class="regular-text" 
			id="arc_linkedin_client_id" 
			name="arc_linkedin_client_id" 
			value="' . get_option( 'arc_linkedin_client_id' ) . '">';
  }
  
  public static function arc_linkedin_client_secret_context() {
		echo '<input 
			type="password" 
			class="regular-text" 
			id="arc_linkedin_client_secret" 
			name="arc_linkedin_client_secret" 
			value="' . get_option( 'arc_linkedin_client_secret' ) . '">';
  }
  
  public static function arc_linkedin_redirect_uri_context() {
		echo '<input 
			type="text" 
			class="regular-text" 
			id="arc_linkedin_redirect_uri" 
			name="arc_linkedin_redirect_uri" 
			value="' . get_option( 'arc_linkedin_redirect_uri' ) . '">';
	}

	public static function arc_linkedin_access_token_context() {
		echo '<input 
			type="hidden" 
			class="regular-text" 
			id="arc_linkedin_access_token" 
      name="arc_linkedin_access_token"
      readonly="true" 
      value="' . get_option( 'arc_linkedin_access_token' ) . '">
      <strong>' . substr(get_option( 'arc_linkedin_access_token' ), 0, 12) . '**********</strong>';
  }
  
  public static function arc_linkedin_access_token_expires_context() {
		echo '<input 
			type="hidden" 
			class="regular-text" 
			id="arc_linkedin_access_token_expires" 
      name="arc_linkedin_access_token_expires" 
      readonly="true" 
      value="' . get_option( 'arc_linkedin_access_token_expires' ) . '">
      <strong>' . date( 'D j M Y h:i:s A', (time() + get_option( 'arc_linkedin_access_token_expires' )) ) . '</strong>';
	}

  public static function arc_linkedin_settings_fields() {
    add_settings_section("section_linkedin", null, null, "arc-social-share-linkedin-settings");
    add_settings_field("arc_linkedin_client_id", "Client Id", array( __CLASS__, 'arc_linkedin_client_id_context' ), "arc-social-share-linkedin-settings", "section_linkedin");
    add_settings_field("arc_linkedin_client_secret", "Client Seccret", array( __CLASS__, 'arc_linkedin_client_secret_context' ), "arc-social-share-linkedin-settings", "section_linkedin");
    add_settings_field("arc_linkedin_redirect_uri", "Redirect URI", array( __CLASS__, 'arc_linkedin_redirect_uri_context' ), "arc-social-share-linkedin-settings", "section_linkedin");
    add_settings_field("arc_linkedin_access_token", "Access Token", array( __CLASS__, 'arc_linkedin_access_token_context' ), "arc-social-share-linkedin-settings", "section_linkedin");
    add_settings_field("arc_linkedin_access_token_expires", "Access Token Expires In", array( __CLASS__, 'arc_linkedin_access_token_expires_context' ), "arc-social-share-linkedin-settings", "section_linkedin");
    register_setting("section_linkedin", "arc_linkedin_client_id");
    register_setting("section_linkedin", "arc_linkedin_client_secret");
    register_setting("section_linkedin", "arc_linkedin_redirect_uri");
    register_setting("section_linkedin", "arc_linkedin_access_token");
    register_setting("section_linkedin", "arc_linkedin_access_token_expires");
    register_setting("section_linkedin", "include_script");
  }

  /**
   * ARC Social Share - LinkedIn API Caller
   */
  public static function arc_linkedin_share_update( $post_id, $post ) {
    if ( get_post_meta( $post_id, 'arc_linkedin_share_flag', true ) == 1 )
      return;

    if ( !in_array($post->post_type, get_option('arc_social_share_post_type')) )
      return;

    /** LinkedIn SDK **/
    $log_file     = ARC__PLUGIN_LOG . 'log/linkedin.log';
    $access_token = get_option( 'arc_linkedin_access_token' );

    /** get linkedin id for user */
    error_log( "\nInitiating linkedin share for post# {$post->ID}", 3,  $log_file);
    try {
      $client = new Client(['base_uri' => 'https://api.linkedin.com']);
      $response = $client->request('GET', '/v2/me', [
        'headers' => [
          "Authorization" => "Bearer " . $access_token,
        ],
      ]);
      $data = json_decode($response->getBody()->getContents(), true);

      /** prepare share data object */
      $thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ) );
      $body = new \stdClass();
      $body->content = new \stdClass();
      $body->content->contentEntities[0] = new \stdClass();
      $body->text = new \stdClass();
      $body->content->contentEntities[0]->thumbnails[0] = new \stdClass();
      $body->content->contentEntities[0]->entityLocation = get_permalink( $post_id );
      $body->content->contentEntities[0]->thumbnails[0]->resolvedUrl = $thumb[0];
      $body->content->title = $post->post_title;
      $body->owner = 'urn:li:person:' . $data['id'];
      $body->text->text = get_the_excerpt( $post );
      $body_json = json_encode($body, true);
      try {
        $client = new Client(['base_uri' => 'https://api.linkedin.com']);
        $response = $client->request('POST', '/v2/shares', [
          'headers' => [
            "Authorization" => "Bearer " . $access_token,
            "Content-Type"  => "application/json",
            "x-li-format"   => "json"
          ],
          'body' => $body_json,
        ]);

        if ($response->getStatusCode() !== 201) {
          update_post_meta( $post_id, 'arc_linkedin_share_flag', 2 );
          error_log( "\n" . $response->getLastBody()->errors[0]->message, 3,  $log_file);
        }
     
        update_post_meta( $post_id, 'arc_linkedin_share_flag', 1 );
        error_log( "\nSuccessfully update shared on linkedin for post# {$post->ID}", 3,  $log_file);
      } catch(Exception $e) {
        update_post_meta( $post_id, 'arc_linkedin_share_flag', 2 );
        error_log( "\n" . $e->getMessage(), 3,  $log_file);
      }
    } catch(Exception $e) {
      update_post_meta( $post_id, 'arc_linkedin_share_flag', 2 );
      error_log( "\n" . $e->getMessage(), 3,  $log_file);
    }
  }

  /**
   * ARC Social Share - LinkedIn share status viewer
   */
  public static function arc_linkedin_share_status($post_obj) {
    switch (get_post_meta( $post_obj->ID , 'arc_linkedin_share_flag', true )) {
      case 1:
        $color = '155724';
        $msg = 'Done! Shared on LinkedIn.';
        break;
      
      case 2:
        $color = '721c24';
        $msg = 'Error! Unable to share on LinkedIn...';
        break;
      
      default:
        $color = '0c5460';
        $msg = 'Waiting! Still not shared on LinkedIn.';
        break;
    }
     
    printf( '<div class="misc-pub-section misc-pub-section-linkedin-share" style="color: #%1$s"><span class="dashicons"><svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
    viewBox="0 0 382 382" style="enable-background:new 0 0 382 382;" xml:space="preserve"><path style="fill:#0077B7;" d="M347.445,0H34.555C15.471,0,0,15.471,0,34.555v312.889C0,366.529,15.471,382,34.555,382h312.889
    C366.529,382,382,366.529,382,347.444V34.555C382,15.471,366.529,0,347.445,0z M118.207,329.844c0,5.554-4.502,10.056-10.056,10.056
    H65.345c-5.554,0-10.056-4.502-10.056-10.056V150.403c0-5.554,4.502-10.056,10.056-10.056h42.806
    c5.554,0,10.056,4.502,10.056,10.056V329.844z M86.748,123.432c-22.459,0-40.666-18.207-40.666-40.666S64.289,42.1,86.748,42.1
    s40.666,18.207,40.666,40.666S109.208,123.432,86.748,123.432z M341.91,330.654c0,5.106-4.14,9.246-9.246,9.246H286.73
    c-5.106,0-9.246-4.14-9.246-9.246v-84.168c0-12.556,3.683-55.021-32.813-55.021c-28.309,0-34.051,29.066-35.204,42.11v97.079
    c0,5.106-4.139,9.246-9.246,9.246h-44.426c-5.106,0-9.246-4.14-9.246-9.246V149.593c0-5.106,4.14-9.246,9.246-9.246h44.426
    c5.106,0,9.246,4.14,9.246,9.246v15.655c10.497-15.753,26.097-27.912,59.312-27.912c73.552,0,73.131,68.716,73.131,106.472
    L341.91,330.654L341.91,330.654z"/></svg></span>&nbsp;<strong>%2$s</strong></div>', $color, $msg );
  }
}