<?php
namespace Alley\WP\Json_Meta\Tests\Feature;

use Alley\WP\Json_Meta\Tests\Test_Case;

use function Alley\WP\Json_Meta\main;

class Json_Meta_Test extends Test_Case {
	/**
	 * Get a raw post meta value from the database.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta Key.
	 * @return string|null
	 */
	protected function get_meta_value_from_database( int $post_id, string $meta_key ): ?string {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, $meta_key ) );
	}

	/** @test */
	public function meta_should_store_as_json_when_registered_to_do_so() {
		$post_id = $this->factory()->post->create();
		$json_meta = 'json';
		$serialized_meta = 'serialized';
		$value = ['test' => 123];

		// Register the json meta key.
		add_filter( 'wp_json_meta_keys', function ( $meta_keys ) use ( $json_meta ) {
			$meta_keys[] = $json_meta;
			return $meta_keys;
		} );

		// Initialize the plugin.
		main();

		add_post_meta( $post_id, $json_meta, $value );
		add_post_meta( $post_id, $serialized_meta, $value );

		// Verify the raw meta values in the database are json and serialized php respectively.
		$this->assertEquals( wp_json_encode( $value ), $this->get_meta_value_from_database( $post_id, $json_meta ) );
		$this->assertEquals( serialize( $value ), $this->get_meta_value_from_database( $post_id, $serialized_meta ) );
	}

	/** @test */
	public function scalar_meta_should_not_store_as_json() {
		$post_id = $this->factory()->post->create();
		$meta_key = 'scalar';
		$value = 123;

		// Register the json meta key.
		add_filter( 'wp_json_meta_keys', function ( $meta_keys ) use ( $meta_key ) {
			$meta_keys[] = $meta_key;
			return $meta_keys;
		} );

		// Initialize the plugin.
		main();

		add_post_meta( $post_id, $meta_key, $value );

		// Verify the raw meta values are NOT json.
		$this->assertEquals( (string) $value, get_post_meta( $post_id, $meta_key, true ) );
		$this->assertEquals( (string) $value, $this->get_meta_value_from_database( $post_id, $meta_key ) );
	}
}
