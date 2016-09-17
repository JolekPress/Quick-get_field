<?php

namespace JPR\QuickGetField;

/**
 * Class JP_Quick_Get_Field
 *
 * Wrapper for all quick getting functionality
 */
class QuickGet
{
    /** @var CacheInterface $cacher */
    private $cacher;

    const ALLOWABLE_POST_TYPES_FILTER = 'jpr_quick_get_field_allowable_post_types_array';
    const CACHE_ACF_OPTIONS_FILTER = 'jpr_quick_get_field_cache_acf_options';

    /**
     * Value used if no ACF data found for a specific post. Allows us to identify which posts haven't been cached yet.
     */
    const NO_ACF_FIELDS_EXIST = 'noFieldsFound';

    public function __construct(CacheInterface $cacher)
    {
        $this->cacher = $cacher;

        // @see https://www.advancedcustomfields.com/resources/acfsave_post/.
        //
        // Priority 20 ensures the new ACF data has been saved and we're caching the updated things.
        \add_action('acf/save_post', [$this, 'maybeCacheAcfDataForPostId'], 20);

        // This allows us to update the ACF cache for a post if something else updates the meta value, like a standard
        // update_post_meta call. Without this, the cache could become stale, especially if ACF gets disabled.
        \add_action('update_postmeta', [$this, 'maybeUpdateCacheAfterExternalMetaDataChange'], 10, 4);

        $this->setupActionHooksForCachedOptions();
    }

    /**
     *  Here we are adding action hooks so we can monitor when non-ACF functionality might be updating one of our
     *  cached option values. If that happens, we fire a method to update the cache, if necessary.
     */
    public function setupActionHooksForCachedOptions()
    {
        // If options aren't cached then there's nothing to do.
        if ($this->areWeCachingAcfOptions()) {
            return;
        }

        $options = $this->cacher->getPostAcfCache('options');

        if (empty($options)) {
            return;
        }

        // NOTE: The option is prefixed in the wp_options table with "options_" by ACF, which is why the action is
        // update_option_options_ plus the key and not just the key itself. We have to re-add the prefix ourselves.
        foreach ($options as $key => $value) {
            \add_action('update_option_options_' . $key, [$this, 'maybeUpdateOptionsCacheAfterExternalMetaDataChange'], 10, 4);
        }
    }

    /**
     * It should be possible to use this just like get_field()
     *
     * @param $fieldId
     * @param null $postId
     * @return mixed|null|void
     */
    public function getField($fieldId, $postId = null)
    {
        if ($postId === null) {
            global $post;
            $postId = $post->ID;
        }

        if (is_preview() || !$this->shouldWeCachePostId($postId)) {
            return $this->getBackupValue($fieldId, $postId);
        }

        $cachedValues = $this->cacher->getPostAcfCache($postId);

        // If the returned value is false, it means we haven't ever updated its cache, so let's check it now.
        if ($cachedValues === false) {
            $cachedValues = $this->updateAndReturnAcfCacheForPostId($postId);
        }

        if (isset($cachedValues[$fieldId])) {
            return $cachedValues[$fieldId];
        }

        // The value wasn't in the cache for some reason, so let's rely on the backup methods.
        return $this->getBackupValue($fieldId, $postId);
    }

    public function maybeUpdateOptionsCacheAfterExternalMetaDataChange($old_value, $new_value, $option)
    {
        // No need to do anything since this is an ACF request and the cache will be updated.
        if (isset($_POST['acf'])) {
            return;
        }

        $cachedOptions = $this->cacher->getPostAcfCache('options');

        $storedOptionKey = preg_replace('/^options_/', '', $option);

        if (!isset($cachedOptions[$storedOptionKey]) || $cachedOptions[$storedOptionKey] === $new_value) {
            return;
        }

        $cachedOptions[$storedOptionKey] = $new_value;

        $this->cacher->updatePostAcfCache('options', $cachedOptions);
    }

    /**
     * Returns a fallback value. Useful if the cached value is not found or if we're on a preview page where we
     * don't want to use the cached value.
     *
     * @param $fieldId
     * @param $postId
     *
     * @return mixed|null|void
     */
    public function getBackupValue($fieldId, $postId)
    {
        if (Helper::isAcfEnabled()) {
            return get_field($fieldId, $postId);
        }

        // TODO: Check if repeater or flexible content and parse
        return \get_post_meta($postId, $fieldId);
    }

    /**
     * It's important that we update the cache if something else changes a meta value that is actually an ACF value.
     *
     * This should only apply to posts that are not currently in the process of being saved (acf/save_post filter)
     * because those updated values will be updated regardless.
     *
     * @param $meta_id
     * @param $object_id
     * @param $meta_key
     * @param $meta_value
     */
    public function maybeUpdateCacheAfterExternalMetaDataChange($meta_id, $object_id, $meta_key, $meta_value)
    {
        if (
            $this->shouldWeIgnoreKey($meta_key)
            || $this->isAcfCurrentlyUpdatingPostId($object_id)
        ) {
            return;
        }

        // Bail early if we know we're not caching this type of post
        if (!$this->shouldWeCachePostId($object_id)) {
            return;
        }

        $cachedData = $this->cacher->getPostAcfCache($object_id);

        // If the meta key isn't a cached ACF field, there's nothing to do here.
        if (!isset($cachedData[$meta_key])) {
            return;
        }

        // Update the cached data array with the new value
        $cachedData[$meta_key] = $meta_value;

        $this->cacher->updatePostAcfCache($object_id, $cachedData);
    }

    /**
     * When a post is saved, determines if that post should be cached, and if so, cache it.
     *
     * Hooked to acf/save_post
     *
     * @param $postId
     */
    public function maybeCacheAcfDataForPostId($postId)
    {
        if (!$this->shouldWeCachePostId($postId)) {
            return;
        }

        $this->updateAndReturnAcfCacheForPostId($postId);
    }

    /**
     * Updates the ACF cache for the provided postId and returns the associative array.
     *
     * @param $postId
     * @return array|bool
     */
    private function updateAndReturnAcfCacheForPostId($postId)
    {
        $currentData = $this->getAllCurrentAcfValues($postId);

        $this->cacher->updatePostAcfCache($postId, $currentData);

        return $currentData;
    }

    /**
     * Determines whether we should cache the specified $postId
     *
     * @param $postId
     *
     * @return bool
     */
    private function shouldWeCachePostId($postId)
    {
        if (Helper::postIdIsOptionsPage($postId)) {
            return $this->areWeCachingAcfOptions();
        }

        $postType = \get_post_type($postId);

        if (in_array($postType, $this->getPostTypesToCache())) {
            return true;
        }

        return false;
    }

    /**
     * Returns an array of the post types that this plugin should attempt to cache. Caches posts and pages by default.
     *
     * @return array
     */
    private function getPostTypesToCache()
    {
        $postTypes = [
            'post',
            'page',
        ];

        $postTypes = \apply_filters(self::ALLOWABLE_POST_TYPES_FILTER, $postTypes);

        if (empty($postTypes)) {
            $postTypes = [];
        }

        return $postTypes;
    }

    /**
     * Very expensive call. Used to retrieve all current ACF data.
     *
     * @param $postId
     *
     * @return array
     */
    private function getAllCurrentAcfValues($postId)
    {
        $fieldsData = \get_fields($postId);

        // get_fields will return false if it doesn't find anything, but we want to always be working with arrays
        if (empty($fieldsData)) {
            $fieldsData = [];
        }

        return $fieldsData;
    }

    /**
     * @return bool - whether or not we are caching the ACF options. Default is true but can be disabled via filter.
     */
    private function areWeCachingAcfOptions()
    {
        return \apply_filters(self::CACHE_ACF_OPTIONS_FILTER, true);
    }

    /**
     * Return an array of keys that we know we should never bother caching.
     *
     * @return array
     */
    private function getMetaKeysToAlwaysIgnore()
    {
        $ignoredKeys = [
            '_edit_lock',
            '_edit_last',
            DatabaseCacher::POSTMETA_CACHE_KEY,
        ];

        return $ignoredKeys;
    }

    /**
     * Check if the provided $metaKey should be ignored by the cache.
     *
     * @param $metaKey
     * @return bool
     */
    private function shouldWeIgnoreKey($metaKey)
    {
        if (in_array($metaKey, $this->getMetaKeysToAlwaysIgnore())) {
            return true;
        }

        return false;
    }

    /**
     * Check if ACF is currently in the process of updating $postId
     *
     * @param $postId
     * @return bool
     */
    private function isAcfCurrentlyUpdatingPostId($postId)
    {
        if (isset($_POST['acf'])
            && isset($_POST['post_ID'])
            && $_POST['post_ID'] == $postId)
        {
            return true;
        }

        return false;
    }
}
