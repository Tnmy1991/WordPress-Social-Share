<?php
/**
 * @package ARC - Social Share
 * @version 1.0.0
 *
 * Plugin Name: ARC - Social Share
 * Plugin URI: https://www.e-arc.com/
 * Description: ARC - Social Share is a social sharing plugin, especially design and developed by 
 * the developers of ARC Document Solutions for own use. Once, newly added post publish in WordPress admin that will share on social networking sites like Facebook, LinkedIn, Twitter etc automatically.
 * Author: ARC Document Solutions
 * Version: 1.0.0
 * Author URI: https://www.e-arc.com/
 */

define( 'ARC_VERSION', '1.0.0' );
define( 'ARC__MINIMUM_WP_VERSION', '5.2.3' );
define( 'ARC__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARC__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ARC__PLUGIN_LOG', plugin_dir_path( __FILE__ ));

require_once( ARC__PLUGIN_DIR . 'class.arcsocialshare.php' );

register_activation_hook( __FILE__, array( 'ARCSocialShare', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'ARCSocialShare', 'plugin_deactivation' ) );

add_action( 'init', array( 'ARCSocialShare', 'init' ) );

if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require_once( ARC__PLUGIN_DIR . 'lib/twitter/class.arcsocialshare.twitter.php' );
	require_once( ARC__PLUGIN_DIR . 'lib/facebook/class.arcsocialshare.facebook.php' );
	require_once( ARC__PLUGIN_DIR . 'lib/linkedin/class.arcsocialshare.linkedin.php' );

	add_action( 'init', array( 'ARCSocialShare_Twitter', 'init' ) );
	add_action( 'init', array( 'ARCSocialShare_Facebook', 'init' ) );
	add_action( 'init', array( 'ARCSocialShare_LinkedIn', 'init' ) );
}