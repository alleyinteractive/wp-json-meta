<?php
/**
 * Json_Meta class
 *
 * @package wp-json-meta
 */

namespace Alley\WP\Json_Meta;

/**
 * Main class for the plugin.
 */
class Json_Meta {
	/**
	 * The meta keys this plugin should take over encoding/decoding.
	 *
	 * @var string[]
	 */
	protected $meta_keys;

	/**
	 * Boot the plugin's functionality.
	 */
	public function boot(): void {
		/**
		 * Register the meta keys this plugin should listen for.
		 *
		 * @param string[] $meta_keys The meta keys to JSON encode vs serialize.
		 */
		$this->meta_keys = apply_filters( 'wp_json_meta_keys', [] );

		if ( empty( $this->meta_keys ) ) {
			return;
		}

		// Hook into all post meta functions.
		add_filter( 'get_post_metadata', [ $this, 'get_post_metadata' ], 0, 4 );
		foreach ( $this->meta_keys as $meta_key ) {
			add_filter( "sanitize_post_meta_{$meta_key}", [ $this, 'maybe_encode' ], 0, 2 );
		}
	}

	/**
	 * Get the registered meta keys.
	 *
	 * @return string[]
	 */
	public function get_meta_keys(): array {
		return $this->meta_keys;
	}

	/**
	 * Determine if the plugin should intercept the meta function for the given key.
	 *
	 * @param string $meta_key The meta key.
	 * @return bool
	 */
	protected function should_handle_key( string $meta_key ): bool {
		return in_array( $meta_key, $this->meta_keys, true );
	}

	/**
	 * Determine if the meta value should be JSON encoded for the given key.
	 *
	 * @param mixed  $value    The value to encode.
	 * @param string $meta_key The meta key.
	 * @return bool
	 */
	public function should_encode( $value, string $meta_key): bool {
		return is_array( $value )
			|| is_object( $value )
			/**
			 * Filter whether scalar values should be JSON encoded for the given meta key.
			 *
			 * @param bool   $encode   Whether the meta key should always be JSON encoded.
			 * @param string $meta_key The meta key.
			 * @param mixed  $value    The value to encode.
			 */
			|| apply_filters( 'wp_json_meta_encode_scalar_values', false, $meta_key, $value );
	}

	/**
	 * Attempt to decode an encoded value.
	 *
	 * @param string $value    Meta value to maybe decode.
	 * @param string $meta_key The meta key.
	 * @return mixed The decoded value, or the original value if it could not be decoded.
	 */
	public function maybe_decode( $value, string $meta_key ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$last_error = json_last_error();

			if ( $last_error === JSON_ERROR_NONE ) {
				/**
				 * Filter the JSON decoded value.
				 *
				 * @param mixed  $decoded  The decoded value.
				 * @param string $meta_key The meta key.
				 * @param string $value    The raw value.
				 */
				return apply_filters( 'wp_json_meta_value_after_decoding', $decoded, $meta_key, $value );
			}

			// As a last ditch attempt, try to unserialize the value.
			if ( is_serialized( $value ) ) {
				return maybe_unserialize( $value );
			}
		}

		return $value;
	}

	/**
	 * JSON encode non-scalar values.
	 *
	 * @param mixed  $value    The value to (maybe) encode.
	 * @param string $meta_key The meta key.
	 * @return mixed JSON encoded value, or the original value if scalar.
	 */
	public function maybe_encode( $value, string $meta_key ) {
		if ( $this->should_encode( $value, $meta_key ) ) {
			/**
			 * Filter the value before JSON encoding it.
			 *
			 * @param mixed  $value    The meta value to JSON encode.
			 * @param string $meta_key The meta key.
			 */
			return wp_json_encode( apply_filters( 'wp_json_meta_value_before_encoding', $value, $meta_key ) );
		}

		return $value;
	}

	/**
	 * Get post meta.
	 *
	 * @param mixed  $value      The value to return.
	 * @param int    $object_id  The post ID.
	 * @param string $meta_key   The meta key.
	 * @param bool   $single     Whether to return a single value.
	 * @return mixed The value to return.
	 */
	public function get_post_metadata( $value, int $object_id, string $meta_key, bool $single ) {
		if ( ! $this->should_handle_key( $meta_key ) ) {
			return $value;
		}

		$meta_cache = wp_cache_get( $object_id, 'post_meta' );

		if ( ! $meta_cache ) {
			$meta_cache = update_meta_cache( 'post', array( $object_id ) );
			$meta_cache = $meta_cache[ $object_id ] ?? null;
		}

		if ( isset( $meta_cache[ $meta_key ] ) ) {
			if ( $single ) {
				return $this->maybe_decode( $meta_cache[ $meta_key ][0], $meta_key );
			} else {
				return array_map( [ $this, 'maybe_decode' ], $meta_cache[ $meta_key ], array_fill( 0, count( $meta_cache[ $meta_key ] ), $meta_key ) );
			}
		}

		return null;
	}
}
