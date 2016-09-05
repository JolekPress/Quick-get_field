=== JolekPress quick get_field ===
Contributors: JohnOlek
Tags: acf, advanced custom fields, get_field, meta, postmeta, post_meta
Requires at least: 4.5
Tested up to: 4.6
Stable tag: trunk
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A quicker, more efficient way of retrieving Advanced Custom Fields (ACF) data.

== Description ==

This plugin is specifically for developers. It is intended to provide a more efficient way of accessing Advanced Custom Fields (ACF) data. Once installed, there will be a new function named `jp_quick_get_field()` that accepts the same parameters as the standard ACF `get_field()` function and returns the same data, but faster.

== Installation ==

This plugin requires Advanced Custom Fields to work as expected.

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
    1. Alternatively, if you want the plugin to be active all the time, consider uploading to the `/wp-content/mu-plugins/` directory.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. You can now use `jp_quick_get_field()` in your templates the same way you would normally use `get_field()`.

== Frequently Asked Questions ==

= How does it work? =

The plugin works by caching all ACF data in a single postmeta field when a post is saved. It does this by using the ACF `get_fields()` function. This cached data is then used, if it exists, instead of using the more intensive `get_fields()` function.

If the cached data doesn't exist, the plugin will fall back to ACF's standard `get_field()` function.

= Won't this make it slightly slower to save posts? =

Sure, but wouldn't you rather it be slow to save posts than display them?

= Does this work with Repeaters and Flexible Content? =

Yes, but **not** using the `have_rows()`, `the_row()`, `get_row_layout()`, or `the_sub_field()` functions. Repeater and flexible content data will be returned as an associative array, exactly like if you called `get_field()` for the repeater key.

= Is this plugin appropriate for all use cases? =

Probably not.