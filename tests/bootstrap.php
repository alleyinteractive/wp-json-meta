<?php
/**
 * Test Bootstrap
 *
 * @package wp-json-meta
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Visit {@see https://mantle.alley.co/testing/test-framework.html} to learn more.
 */
\Mantle\Testing\manager()
	->maybe_rsync_plugin()
	// Load the main file of the plugin.
	->loaded( function () {
		require_once __DIR__ . '/../wp-json-meta.php';

		// We'll boot in tests.
		remove_action( 'after_setup_theme', 'Alley\WP\Json_Meta\main' );
	} )
	->install();
