<?php
/**
 * Plugin Name: WP JSON Meta
 * Plugin URI: https://github.com/alleyinteractive/wp-json-meta
 * Description: Quietly and seamlessly store post meta as json instead of serialized php
 * Version: 0.1.0
 * Author: Matthew Boynes
 * Author URI: https://alley.com/
 * Requires at least: 6.0
 * Tested up to: 6.2
 *
 * Text Domain: wp-json-meta
 * Domain Path: /languages/
 *
 * @package wp-json-meta
 */

namespace Alley\WP\Json_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the plugin's main files.
require_once __DIR__ . '/src/class-json-meta.php';

/**
 * Instantiate the plugin.
 */
function main(): void {
	// Create the core plugin object.
	$plugin = new Json_Meta();

	/**
	 * Announce that the plugin has been initialized and share the instance.
	 *
	 * @param Json_Meta $plugin Plugin class instance.
	 */
	do_action( 'wp_json_meta_init', $plugin );

	$plugin->boot();
}

add_action( 'after_setup_theme', __NAMESPACE__ . '\main' );
