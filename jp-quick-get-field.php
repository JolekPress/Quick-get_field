<?php
/*
Plugin Name: JolekPress quick get_field
Plugin URI: https://github.com/JolekPress/Quick-get_field
Description: A quicker, more efficient way of retrieving Advanced Custom Fields (ACF) data.
Version: 0.1.0
Author: John Oleksowicz
Author URI: http://jolekpress.com
*/

add_action('wp_loaded', function() {
    require 'src/Getter.php';
    require 'src/CacheInterface.php';
    require 'src/DatabaseCacher.php';
    require 'src/Helper.php';

    $dbCacher = new JP\QuickGetField\DatabaseCacher();

    global $getter;
    $getter = new JP\QuickGetField\Getter($dbCacher);

    function jp_quick_get_field($fieldId, $postId = null) {
        /** @var JP\QuickGetField\Getter $getter */
        global $getter;

        return $getter->getField($fieldId, $postId);
    }
});
