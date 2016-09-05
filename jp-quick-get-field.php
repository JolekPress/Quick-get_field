<?php
/*
Plugin Name: JolekPress quick get_field
Plugin URI: https://github.com/JolekPress/Quick-get_field
Description: A quicker, more efficient way of retrieving Advanced Custom Fields (ACF) data.
Version: 0.1.0
Author: John Oleksowicz
Author URI: http://jolekpress.com
*/

function jp_quick_get_field($fieldId, $postId = null) {
    return JP_Quick_Get_Field::getField($fieldId, $postId);
}

class JP_Quick_Get_Field
{
    static $fetchedCacheData = [];
    const NO_ACF_FIELDS_EXIST = 'noFieldsFound';

    // NOTE: Prefixing with _underscore is important to identify this as a "hidden" postmeta field.
    const POSTMETA_CACHE_KEY = '_jp_acf_cache';

    private static function isAcfEnabled()
    {
        return function_exists('get_field');
    }

    public static function init()
    {
        // @see https://www.advancedcustomfields.com/resources/acfsave_post/. Priority 20 ensures the new ACF
        // data has been saved and we're caching the correct stuff.
        add_action('acf/save_post', [__CLASS__, 'maybeCacheAcfDataForPostId'], 20);
    }

    public static function maybeCacheAcfDataForPostId($postId)
    {
        $postType = get_post_type($postId);

        if (!in_array($postType, self::getPostTypesToCache())) {
            return;
        }

        self::updateCacheForPostId($postId);
    }

    private static function updateCacheForPostId($postId)
    {
        // Get ALL ACF fields for the post_id
        $acfData = get_fields($postId);

        // If there are no fields, we need to still identify that we checked this postId, so we set the meta value
        // to be a specific string.
        if (empty($acfData)) {
            $acfData = self::NO_ACF_FIELDS_EXIST;
        }

        update_post_meta($postId, self::POSTMETA_CACHE_KEY, $acfData);
    }

    private static function getPostTypesToCache()
    {
        $postTypes = [
            'post',
            'page'
        ];

        $postTypes = apply_filters('jp_quick_get_field_allowable_post_types_array', $postTypes);

        return $postTypes;
    }

    private static function fetchCachedDataForPostId($postId)
    {
        $getSingleValue = true;

        $data = get_post_meta($postId, self::POSTMETA_CACHE_KEY, $getSingleValue);

        if ($data === "") {
            self::updateCacheForPostId($postId);
        }

        self::$fetchedCacheData[$postId] = $data;
    }

    public static function getField($fieldId, $postId = null)
    {
        if ($postId === null) {
            global $post;
            $postId = $post->ID;
        }

        if (!isset(self::$fetchedCacheData[$postId])) {
            self::fetchCachedDataForPostId($postId);
        }

        if (isset(self::$fetchedCacheData[$postId][$fieldId])) {
            return self::$fetchedCacheData[$postId][$fieldId];
        }

        if (self::isAcfEnabled()) {
            return get_field($fieldId, $postId);
        }

        // TODO: Check if repeater and parse
        return get_post_meta($postId, $fieldId);
    }
}

JP_Quick_Get_Field::init();