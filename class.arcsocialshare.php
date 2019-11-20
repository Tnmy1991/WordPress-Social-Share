<?php
/**
 * @package ARC - Social Share
 * @version 1.0.0
 *
 */
class ARCSocialShare {

  private static $initiated = false;

  public static function init() {
    if( ! self::$initiated )
      self::init_hooks();
  }

  private static function init_hooks() {
    self::$initiated = true;

    add_action('admin_menu', array('ARCSocialShare', 'add_arc_social_share_settings_panel'));
    add_action('admin_init', array('ARCSocialShare', 'arc_social_share_settings_fields'));
    add_action('post_submitbox_misc_actions', array('ARCSocialShare', 'arc_share_status'));
  }

  public static function plugin_activation() {
    if ( version_compare( $GLOBALS['wp_version'], ARC__MINIMUM_WP_VERSION, '<' ) ) {
      $message = sprintf(esc_html__( 'ARC - Social Share %s requires WordPress %s or higher.'), ARC_VERSION, ARC__MINIMUM_WP_VERSION ).' '.sprintf(__('Please upgrade WordPress to a current version.'));
      ARCSocialShare::bail_on_activation( $message );
    }
  }

  private static function bail_on_activation( $message, $deactivate = true ) {
  ?>
    <!doctype html>
    <html>
      <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>" />
        <style>
          * {
            text-align: center;
            margin: 0;
            padding: 0;
            font-family: "Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif;
          }
          p {
            margin-top: 1em;
            font-size: 18px;
          }
        </style>
      </head>
      <body>
        <p><?php echo esc_html( $message ); ?></p>
      </body>
    </html>
  <?php
    if ( $deactivate ) {
      $plugins = get_option( 'active_plugins' );
      $arc = plugin_basename( ARC__PLUGIN_DIR . 'arc-social-share.php' );
      $update  = false;
      foreach ( $plugins as $i => $plugin ) {
        if ( $plugin === $arc ) {
          $plugins[$i] = false;
          $update = true;
        }
      }

      if ( $update )
        update_option( 'active_plugins', array_filter( $plugins ) );
    }
    exit;
  }

  public static function plugin_deactivation( ) {
    delete_option( 'arc_social_share_post_type' );
    delete_option( 'arc_twitter_consumer_key' );
    delete_option( 'arc_twitter_consumer_secret' );
    delete_option( 'arc_twitter_access_token' );
    delete_option( 'arc_twitter_access_secret' );
    delete_option( 'arc_facebook_app_id' );
    delete_option( 'arc_facebook_app_secret' );
    delete_option( 'arc_facebook_page_access_token' );
    delete_option( 'arc_linkedin_client_id' );
    delete_option( 'arc_linkedin_client_secret' );
    delete_option( 'arc_linkedin_access_token' );
  }

  /**
   * ARC Social Share - General Settings Panel
   */
  public static function add_arc_social_share_settings_panel() {
    add_menu_page(
      "ARC Social Share - General Settings", 
      "ARC Social Share - General Settings", 
      "manage_options", 
      "arc-social-share-settings", 
      function () {
        echo '<div class="wrap">';
        echo '<h1>ARC Social Share - General Settings</h1>';
        echo '<form method="post" enctype="multipart/form-data" action="options.php">';
        settings_fields("section");
        do_settings_sections("arc-social-share-settings");      
        submit_button(); 
        echo '</form>';
        echo '</div> '; 
      }, 
      null, 
      99
    );
  }

  public static function display_supported_post_type_list() {
    $_retriveLastSetting = get_option( 'arc_social_share_post_type' );
    $args = array(
      'public'              => true,
      'show_ui'             => true,
      '_builtin'            => false
    );

    $output = 'objects'; 
    $operator = 'and';
    $post_types = get_post_types( $args, $output, $operator );
    if(is_array($_retriveLastSetting) && in_array('post', $_retriveLastSetting)) 
      echo '<label for="supported_on_post"><input name="arc_social_share_post_type[]" id="supported_on_post" value="post" type="checkbox" checked> Post</label><br>';
    else
      echo '<label for="supported_on_post"><input name="arc_social_share_post_type[]" id="supported_on_post" value="post" type="checkbox"> Post</label><br>';
    
    if(is_array($_retriveLastSetting) && in_array('page', $_retriveLastSetting))
      echo '<label for="supported_on_page"><input name="arc_social_share_post_type[]" id="supported_on_page" value="page" type="checkbox" checked> Page</label><br>';
    else
      echo '<label for="supported_on_page"><input name="arc_social_share_post_type[]" id="supported_on_page" value="page" type="checkbox"> Page</label><br>';
    
    foreach($post_types  as $post_type) {
      $id = "supported_on_" . $post_type->name;
      $status = (is_array($_retriveLastSetting) &&  in_array($post_type->name, $_retriveLastSetting)) ? 'checked' : '';
      echo '<label for="'.$id.'"><input name="arc_social_share_post_type[]" id="'.$id.'" value="'.$post_type->name.'" type="checkbox" '.$status.'> '.$post_type->label.'</label><br>';
    }
  } 

  public static function arc_social_share_settings_fields() {
    add_settings_section("section", null, null, "arc-social-share-settings");
    add_settings_field("arc_social_share_post_type", "Supported Post Types", array( __CLASS__, 'display_supported_post_type_list' ), "arc-social-share-settings", "section"); 
    register_setting("section", "arc_social_share_post_type");
    register_setting("section", "include_script");
  }

  /**
   * ARC Social Share - Meta Box
   */
  private static function arc_get_current_post_type() {
    global $post, $typenow, $current_screen;

    if ($post && $post->post_type) 
      return $post->post_type;
    elseif($typenow) 
      return $typenow;
    elseif($current_screen && $current_screen->post_type) 
      return $current_screen->post_type;
    elseif(isset($_REQUEST['post_type'])) 
      return sanitize_key($_REQUEST['post_type']);
    
    return null;
  }

  public static function arc_share_status( $post_obj ) {
    $post_types = get_option('arc_social_share_post_type');
    $current    = self::arc_get_current_post_type();

    if ( in_array( $current, $post_types ) ) {
      printf('<div class="misc-pub-section misc-pub-section-arc-share"><strong>%1$s</strong></div>', 'ARC - Social Share Status');

      ARCSocialShare_Facebook::arc_facebook_share_status( $post_obj );
      ARCSocialShare_Twitter::arc_twitter_share_status( $post_obj );
      ARCSocialShare_LinkedIn::arc_linkedin_share_status( $post_obj );
    }
  }
}