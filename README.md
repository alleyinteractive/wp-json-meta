# WP JSON Meta

Quietly and seamlessly store post meta as json instead of serialized php

## Description

This plugin allows you to store non-scalar post meta as json instead of serialized php.

## Instructions for Use

1. Install and activate the plugin.
2. Register the meta keys you want to store as json by adding them to the `wp_json_meta_keys` filter. For example:

   ```php
   add_filter( 'wp_json_meta_keys', function( $keys ) {
       $keys[] = 'my_meta_key';
       return $keys;
   } );
   ```

3. That's it! Now when you use post meta functions such as `get_post_meta` or `update_post_meta` with the registered meta key, the plugin will automatically encode and decode the value as json.

## Converting Existing Meta

If you have existing meta stored as serialized php, the plugin will continue to read the serialized php, and the next time it is updated, the plugin will convert it to json. If you'd like, you can speed up this process and convert the meta to json by using the `wp post meta convert-to-json` WP-CLI command. For example:

   ```bash
   wp post meta convert-to-json --registered
   ```

If you ever need to convert meta back to serialized php, you can use the `wp post meta convert-to-serialized` WP-CLI command. For example:

   ```bash
   wp post meta convert-to-serialized --registered
   ```

Using the `--registered` flag will only convert meta that has been registered with the `wp_json_meta_keys` filter. If you want to convert specific keys instead, you can use the `--key` flag and pass as many as you'd like. For example:

   ```bash
   wp post meta convert-to-json --key=this_meta_key --key=that_meta_key
   ```

## Caveats

* Once you've stored meta as json, if you want to disable and stop using the plugin, you may need to first convert that meta to serialized php so WordPress can read it. The plugin includes a WP-CLI command to do this as noted above.
* Just as with WordPress core, the plugin will only store meta as json if the value is an array or object. If the value is scalar, it will be stored as usual (as a string representation).
* If you're trying to store a class instance as post meta, the plugin may need some help encoding and later decoding the object. The plugin provides two filters for this purpose, `wp_json_meta_value_before_encoding` and `wp_json_meta_after_decoding`. If your object implements the `JsonSerializable` interface, you likely don't need to use the former filter, but you would still need the latter to instantiate a new object based on the data you stored.
