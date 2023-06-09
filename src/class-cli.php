<?php
/**
 * This file contains the CLI class
 */

namespace Alley\WP\Json_Meta;

\WP_CLI::add_command( 'post meta', __NAMESPACE__ . '\CLI' );

/**
 * WP-CLI commands for the plugin.
 */
class CLI extends \WP_CLI_Command {
	/**
	 * For a given meta key, convert all serialized values to JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--key=<meta_key>]
	 * : The meta key(s) to convert.
	 *
	 * [--registered]
	 * : Whether or not to convert all registered meta keys.
	 *
	 * [--dry-run]
	 * : Whether or not to actually update the database.
	 *
	 * ## EXAMPLES
	 *
	 *    wp post meta convert-to-json --key=some_meta_key
	 *    wp post meta convert-to-json --key=some_meta_key --key=another_meta_key
	 *    wp post meta convert-to-json --registered
	 *
	 * @subcommand convert-to-json
	 *
	 * @param array $args The arguments passed to the command.
	 * @param array $assoc_args The associative arguments passed to the command.
	 * @return void
	 */
	public function convert_to_json( $args, $assoc_args ) {
		global $wpdb;
		$json_meta_plugin = get_plugin_instance();
		$dry_run          = $assoc_args['dry-run'];
		$batch_size       = 1000;

		$meta_keys = array_unique(
			array_merge(
				! empty( $assoc_args['registered'] ) ? $json_meta_plugin->get_meta_keys() : [],
				! empty( $assoc_args['key'] ) ? (array) $assoc_args['key'] : []
			)
		);

		if ( empty( $meta_keys ) ) {
			\WP_CLI::error( 'No meta keys provided.' );
		}

		$stats = [
			'updated' => 0,
			'failed'  => 0,
			'skipped' => 0,
		];

		$meta_key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );

		// First look at how many rows there are to convert and create a progress bar.
		$total_rows = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE `meta_key` IN ({$meta_key_placeholders})", $meta_keys ) );
		$progress = \WP_CLI\Utils\make_progress_bar( "Converting {$total_rows} meta values", $total_rows );

		$query = $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE `meta_key` IN ({$meta_key_placeholders}) ORDER BY meta_id ASC", $meta_keys );
		$start_at = 0;
		do {
			$meta = $wpdb->get_results( $wpdb->prepare( $query . ' LIMIT %d, %d', [ $start_at, $batch_size ] ) );

			foreach ( $meta as $row ) {
				if ( is_serialized( $row->meta_value ) ) {
					$meta_value = maybe_unserialize( $row->meta_value );
					if ( ! is_array( $meta_value ) && ! is_object( $meta_value ) ) {
						\WP_CLI::warning( "Failed to unserialize meta value for post {$row->post_id} and meta key {$row->meta_key}. Meta value:" );
						\WP_CLI::line( var_export( $row->meta_value, true ) );
						$stats['failed']++;
						$progress->tick();
						continue;
					}

					if ( ! $dry_run ) {
						// TODO: Test multiple meta values matching same key, and ensure the previous value matches.
						update_post_meta( $row->post_id, $row->meta_key, $json_meta_plugin->maybe_encode( $meta_value ), $meta_value );
					}
					$stats['updated']++;
				} else {
					$stats['skipped']++;
				}
				$progress->tick();
			}

			$start_at += $batch_size;
		} while ( count( $meta ) === 1000 );

		$progress->finish();

		\WP_CLI::success( "Process complete! Updated {$stats['updated']} meta value(s), skipped {$stats['skipped']}, and failed to update {$stats['failed']}" );
	}

	/**
	 * For a given meta key, convert all JSON values to serialized PHP.
	 *
	 * ## OPTIONS
	 *
	 * [--key=<meta_key>]
	 * : The meta key(s) to convert.
	 *
	 * [--registered]
	 * : Whether or not to convert all registered meta keys.
	 *
	 * [--dry-run]
	 * : Whether or not to actually update the database.
	 *
	 * [--verbose]
	 * : Whether or not to output verbose information.
	 *
	 * ## EXAMPLES
	 *
	 *    wp post meta convert-to-serialized --key=some_meta_key
	 *    wp post meta convert-to-serialized --key=some_meta_key --key=another_meta_key
	 *    wp post meta convert-to-serialized --registered
	 *
	 * @subcommand convert-to-serialized
	 *
	 * @param array $args The arguments passed to the command.
	 * @param array $assoc_args The associative arguments passed to the command.
	 * @return void
	 */
	public function convert_to_serialized( $args, $assoc_args ) {
		global $wpdb;
		$json_meta_plugin = get_plugin_instance();
		$dry_run          = ! empty( $assoc_args['dry-run'] );
		$verbose          = ! empty( $assoc_args['verbose'] );
		$batch_size       = 1000;

		$meta_keys = array_unique(
			array_merge(
				! empty( $assoc_args['registered'] ) ? $json_meta_plugin->get_meta_keys() : [],
				! empty( $assoc_args['key'] ) ? (array) $assoc_args['key'] : []
			)
		);

		if ( empty( $meta_keys ) ) {
			\WP_CLI::error( 'No meta keys provided.' );
		}

		$stats = [
			'updated' => 0,
			'failed'  => 0,
			'skipped' => 0,
		];

		$meta_key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );

		// First look at how many rows there are to convert and create a progress bar.
		$total_rows = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE `meta_key` IN ({$meta_key_placeholders})", $meta_keys ) );
		$progress = \WP_CLI\Utils\make_progress_bar( "Converting {$total_rows} meta values", $total_rows );

		$query = $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE `meta_key` IN ({$meta_key_placeholders}) ORDER BY meta_id ASC", $meta_keys );
		$start_at = 0;
		do {
			$meta = $wpdb->get_results( $wpdb->prepare( $query . ' LIMIT %d, %d', [ $start_at, $batch_size ] ) );

			foreach ( $meta as $row ) {
				// Attempt to JSON decode the value.
				$decoded = json_decode( $row->meta_value, true );
				$last_error = json_last_error();

				if ( $last_error === JSON_ERROR_NONE ) {
					// If the value is not an array or object, it can be left alone.
					if ( is_array( $decoded ) || is_object( $decoded ) ) {
						if ( ! $dry_run ) {
							update_post_meta( $row->post_id, $row->meta_key, $decoded, $row->meta_value );
						} else {
							$stats['updated']++;
						}
					} else {
						// This is a scalar value, so we can skip it.
						$stats['skipped']++;
					}
				} else {
					// The most common error we'll see is trying to decode a string that is not actually JSON. However,
					// we might see valid malformed syntax, so don't ignore those.
					if ( $verbose || $last_error !== JSON_ERROR_SYNTAX || preg_match('/^[\[\{]/', $row->meta_value ) ) {
						\WP_CLI::warning( "Failed to JSON decode meta value for post {$row->post_id} and meta key {$row->meta_key}!" );
						\WP_CLI::warning( 'Error: ' . json_last_error_msg() );
						\WP_CLI::warning( 'Meta value: ' . var_export( $row->meta_value, true ) );
					}
					$stats['failed']++;
				}
				$progress->tick();
			}

			$start_at += $batch_size;
		} while ( count( $meta ) === 1000 );

		$progress->finish();
		\WP_CLI::success( "Process complete! Updated {$stats['updated']} meta value(s), skipped {$stats['skipped']}, and failed to update {$stats['failed']}" );
	}
}
