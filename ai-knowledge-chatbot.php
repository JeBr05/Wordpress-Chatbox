<?php
/**
 * AI Knowledge Chatbot
 *
 * @package AIKnowledgeChatbot
 *
 * @wordpress-plugin
 * Plugin Name:       AI Knowledge Chatbot
 * Plugin URI:        https://github.com/JeBr05/Wordpress-Chatbox
 * Description:       Build a site-aware chatbot from selected WordPress content using an OpenAI API key and vector store.
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Open Source Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-knowledge-chatbot
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIKB_VERSION', '0.2.0' );
define( 'AIKB_PLUGIN_FILE', __FILE__ );
define( 'AIKB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIKB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIKB_REST_NAMESPACE', 'aikb/v1' );

require_once AIKB_PLUGIN_DIR . 'includes/class-aikb-activator.php';
require_once AIKB_PLUGIN_DIR . 'includes/class-aikb-plugin.php';

register_activation_hook( __FILE__, array( 'AIKB_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AIKB_Activator', 'deactivate' ) );

AIKB_Plugin::instance()->run();
