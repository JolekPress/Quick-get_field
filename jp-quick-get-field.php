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

/**
 * Class JP_Quick_Get_Field
 *
 * Wrapper for all quick getting functionality
 */
class JP_Quick_Get_Field
{
    /**
     * @var array Holds all fetched cached data in an associative array
     */
    static $fetchedCacheData = [];

    /**
     * Value used if no ACF data found for a specific post. Allows us to identify which posts haven't been cached yet.
     */
    const NO_ACF_FIELDS_EXIST = 'noFieldsFound';

    /**
     * Key used in the postmeta table (via update_post_meta()) for cached data.
     *
     * NOTE: Prefixing with _underscore is important to identify this as a "hidden" postmeta field.
    */
    const POSTMETA_CACHE_KEY = '_jp_acf_cache';

    /**
     * Option key in the WordPress options table for ACF "Options" data. We store the data in the options table
     * because the options page isn't a post.
     */
    const OPTIONS_PAGE_OPTION_KEY = '_jp_acf_options_cache';

    public static function init()
    {
        // @see https://www.advancedcustomfields.com/resources/acfsave_post/. Priority 20 ensures the new ACF
        // data has been saved and we're caching the correct stuff.
        add_action('acf/save_post', [__CLASS__, 'maybeCacheAcfDataForPostId'], 20);
    }

    /**
     * Helper method to determine if ACF plugin is enabled.
     *
     * @return bool
     */
    private static function isAcfEnabled()
    {
        return function_exists('get_field');
    }

    /**
     * Helper method to determine if a given $postId is actually a request for an ACF option.
     *
     * @param $postId
     * @return bool
     */
    private static function postIdIsOptionsPage($postId)
    {
        return in_array($postId, ['option', 'options']);
    }

    /**
     * When a post is saved, determines if that post should be cached, and if so, cache it.
     *
     * Hooked to acf/save_post
     *
     * @param $postId
     */
    public static function maybeCacheAcfDataForPostId($postId)
    {
        if (self::postIdIsOptionsPage($postId)) {
            $postType = 'options';
        } else {
            $postType = get_post_type($postId);
        }

        if (!in_array($postType, self::getPostTypesToCache())) {
            return;
        }

        self::updateCacheForPostId($postId);
    }

    /**
     * The actual heavy lifting for caching ACF data associated with a given $postId. Uses the expensive get_fields()
     * function, but that's OK since it should only happen on post save or the first time we try to access data for
     * a post using this plugin.
     *
     * @param $postId
     */
    private static function updateCacheForPostId($postId)
    {
        // Get ALL ACF fields for the post_id
        $acfData = get_fields($postId);

        // If there are no fields, we need to still identify that we checked this postId, so we set the meta value
        // to be a specific string.
        if (empty($acfData)) {
            $acfData = self::NO_ACF_FIELDS_EXIST;
        }

        if (self::postIdIsOptionsPage($postId)) {
            $autoload_option = false;
            update_option(self::OPTIONS_PAGE_OPTION_KEY, $acfData, $autoload_option);
        } else {
            update_post_meta($postId, self::POSTMETA_CACHE_KEY, $acfData);
        }

        self::$fetchedCacheData[$postId] = $acfData;
    }

    /**
     * Returns an array of the post types that this plugin should attempt to cache.
     *
     * @return array
     */
    private static function getPostTypesToCache()
    {
        $postTypes = [
            'post',
            'page',
            'options',
        ];

        $postTypes = apply_filters('jp_quick_get_field_allowable_post_types_array', $postTypes);

        if (empty($postTypes)) {
            $postTypes = [];
        }

        return $postTypes;
    }

    /**
     * Retrieves all cached data for a given $postId and stores in memory in self::$fetchedCachedData.
     *
     * @param $postId
     */
    private static function fetchCachedDataForPostId($postId)
    {
        if (self::postIdIsOptionsPage($postId)) {
            $data = get_option(self::OPTIONS_PAGE_OPTION_KEY);
        } else {
            $getSingleValue = true;

            $data = get_post_meta($postId, self::POSTMETA_CACHE_KEY, $getSingleValue);
        }

        // The data wouldn't be empty if we had ever updated the cache for it before because we would have set
        // the value to the "no data found" constant if ACF returns nothing.
        if (empty($data)) {
            self::updateCacheForPostId($postId);
        }

        self::$fetchedCacheData[$postId] = $data;
    }

    /**
     * It should be possible to use this just like get_field()
     *
     * @param $fieldId
     * @param null $postId
     * @return mixed|null|void
     */
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