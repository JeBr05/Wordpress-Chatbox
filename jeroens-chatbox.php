<?php
/**
 * Jeroen's Chatbox
 *
 * @package JeroensChatbox
 *
 * @wordpress-plugin
 * Plugin Name:       Jeroen's Chatbox
 * Plugin URI:        https://github.com/JeBr05/Wordpress-Chatbox
 * Description:       Build a site-aware chatbox from selected WordPress content using an OpenAI API key and vector store.
 * Version:           0.7.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Open Source Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jeroens-chatbox
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JCB_VERSION', '0.7.0' );
define( 'JCB_PLUGIN_FILE', __FILE__ );
define( 'JCB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JCB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JCB_REST_NAMESPACE', 'jeroens-chatbox/v1' );

require_once JCB_PLUGIN_DIR . 'includes/class-jcb-activator.php';
require_once JCB_PLUGIN_DIR . 'includes/class-jcb-plugin.php';

register_activation_hook( __FILE__, array( 'JCB_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JCB_Activator', 'deactivate' ) );

JCB_Plugin::instance()->run();
