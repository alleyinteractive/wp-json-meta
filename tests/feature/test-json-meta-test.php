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

	/**
	 * Register a meta key to be stored as JSON.
	 *
	 * @param string $meta_key
	 * @param int    $option
	 */
	protected function register_meta_key( string $meta_key, int $option = 1 ): void {
		add_filter( 'wp_json_meta_keys', function ( $meta_keys ) use ( $meta_key, $option ) {
			$meta_keys[ $meta_key ] = $option;
			return $meta_keys;
		} );
	}

	/**
	 * Scalar meta dataProvider.
	 * @return array
	 */
	public function scalar_meta_provider() {
		return [
			[ 'string', 'string' ],
			[ 'int', 123 ],
			[ 'float', 123.456 ],
			[ 'bool', true ],
			[ 'null', null ],
		];
	}

	/** @test */
	public function meta_should_store_as_json_when_registered_to_do_so() {
		$post_id = $this->factory()->post->create();
		$json_meta = 'json';
		$serialized_meta = 'serialized';
		$value = ['test' => 123];
		$this->register_meta_key( $json_meta );

		// Initialize the plugin.
		main();

		add_post_meta( $post_id, $json_meta, $value );
		add_post_meta( $post_id, $serialized_meta, $value );

		// Verify the raw meta values in the database are json and serialized php respectively.
		$this->assertEquals( wp_json_encode( $value ), $this->get_meta_value_from_database( $post_id, $json_meta ) );
		$this->assertEquals( serialize( $value ), $this->get_meta_value_from_database( $post_id, $serialized_meta ) );
	}

	/**
	 * @test
	 * @dataProvider scalar_meta_provider
	 */
	public function scalar_meta_can_be_configured_to_not_store_as_json( $meta_key, $value ) {
		$post_id = $this->factory()->post->create();
		$this->register_meta_key( $meta_key );

		// Initialize the plugin.
		main();

		add_post_meta( $post_id, $meta_key, $value );

		// Values should be strings, matching WordPress's default behavior.
		$this->assertEquals( (string) $value, get_post_meta( $post_id, $meta_key, true ) );
		$this->assertEquals( [ (string) $value ], get_post_meta( $post_id, $meta_key ) );
		// Verify the raw meta values are NOT json.
		$this->assertEquals( (string) $value, $this->get_meta_value_from_database( $post_id, $meta_key ) );
	}

	/**
	 * @test
	 * @dataProvider scalar_meta_provider
	 */
	public function scalar_meta_can_be_configured_to_store_as_json( $meta_key, $value ) {
		$post_id = $this->factory()->post->create();
		$this->register_meta_key( $meta_key, 2 );

		// Initialize the plugin.
		main();

		add_post_meta( $post_id, $meta_key, $value );

		// Verify the meta values' types match.
		$this->assertSame( $value, get_post_meta( $post_id, $meta_key, true ) );
		$this->assertSame( [ $value ], get_post_meta( $post_id, $meta_key ) );
		// Verify the raw meta values are json.
		$this->assertSame( wp_json_encode( $value ), $this->get_meta_value_from_database( $post_id, $meta_key ) );
	}
}
