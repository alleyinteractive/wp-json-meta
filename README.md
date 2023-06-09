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

## Caveats

* This plugin is a one-way road. Once you've stored meta as json, you can't go back to storing it as serialized php (unless you write your own code to do so).
