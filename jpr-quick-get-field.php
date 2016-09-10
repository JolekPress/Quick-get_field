<?php
/*
Plugin Name: JolekPress quick get_field
Plugin URI: https://github.com/JolekPress/Quick-get_field
Description: A quicker, more efficient way of retrieving Advanced Custom Fields (ACF) data.
Version: 0.1.0
Author: John Oleksowicz
Author URI: http://jolekpress.com
*/

require 'quick-get-classes/Getter.php';
require 'quick-get-classes/CacheInterface.php';
require 'quick-get-classes/DatabaseCacher.php';
require 'quick-get-classes/Helper.php';
require 'quick-get-classes/WPObjectCacheCacher.php';

$jpQuickGetFieldCacher = new JP\QuickGetField\DatabaseCacher();

/**
 * In case you want to use a different caching mechanism, here is a filter to do that. $dbCacher MUST implement
 * the JP\QuickGetField\CacheInterface.
 */
$jpQuickGetFieldCacher = apply_filters('jpr_quick_get_field_cacher', $jpQuickGetFieldCacher);

$jpQuickGetFieldGetter = new JP\QuickGetField\Getter($jpQuickGetFieldCacher);

function jpr_quick_get_field($fieldId, $postId = null) {
    global $jpQuickGetFieldGetter;

    return $jpQuickGetFieldGetter->getField($fieldId, $postId);
}

/**
 * Shortcode for efficient display of text based ACF values
 *
 * @param $userProvidedAtts
 * @return string
 */
function jpr_quick_field_shortcode($userProvidedAtts)
{
    global $post;
    $defaults = [
        'field' => '',
        'post_id' => $post->ID,
    ];

    $filteredAtts = shortcode_atts($defaults, $userProvidedAtts, 'jpr_quick_field');

    $field = $filteredAtts['field'];
    $post_id = $filteredAtts['post_id'];

    if (empty($field)) {
        return '';
    }

    $value = jpr_quick_get_field($field, $post_id);

    if (!is_string($value)) {
        return '';
    }

    return $value;
}

add_shortcode('jpr_quick_field', 'jpr_quick_field_shortcode');


