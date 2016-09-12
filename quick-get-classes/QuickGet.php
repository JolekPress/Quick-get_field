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

    /**
     * Stores the postId of the post that is currently being saved. Helps us identify which meta values to skip when
     * handling external meta data updates.
     */
    private $postIdBeingUpdated;

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
        // Using priority 1 lets us do things before the actual ACF values are saved.
        \add_action('acf/save_post', [$this, 'identifyWhichPostIsBeingUpdated'], 1);

        // @see https://www.advancedcustomfields.com/resources/acfsave_post/.
        //
        // Priority 20 ensures the new ACF data has been saved and we're caching the updated things.
        \add_action('acf/save_post', [$this, 'maybeCacheAcfDataForPostId'], 20);


        // This allows us to update the ACF cache for a post if something else updates the meta value, like a standard
        // update_post_meta call. Without this, the cache could become stale, especially if ACF gets disabled.
        \add_action('update_postmeta', [$this, 'maybeUpdateCacheAfterExternalMetaDataChange'], 10, 4);
    }

    /**
     * By storing this value BEFORE ACF fields are updated, we can know which $postId is appropriate to skip
     *
     * @param $postId
     */
    public function identifyWhichPostIsBeingUpdated($postId)
    {
        $this->postIdBeingUpdated = $postId;
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
        if ($object_id === $this->postIdBeingUpdated) {
            return;
        }

        // Don't do anything with hidden values.
        if (Helper::isMetaKeyHidden($meta_key)) {
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

        $cachedData[$meta_key] = $meta_value;

        $this->cacher->updatePostAcfCache($object_id, $cachedData);
    }

    /**
     * @return bool - whether or not we are caching the ACF options. Default is true but can be disabled via filter.
     */
    private function areWeCachingAcfOptions()
    {
        return \apply_filters(self::CACHE_ACF_OPTIONS_FILTER, true);
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

        $currentData = $this->getAllCurrentAcfValues($postId);

        $this->cacher->updatePostAcfCache($postId, $currentData);
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
     * Returns an array of the post types that this plugin should attempt to cache.
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

        $value = $this->cacher->getValue($fieldId, $postId);

        if ($value !== null) {
            return $value;
        }

        return $this->getBackupValue($fieldId, $postId);
    }

    /**
     * Very expensive call. Used to retrieve all current ACF data.
     *
     * @param $postId
     *
     * @return array|bool
     */
    private function getAllCurrentAcfValues($postId)
    {
        return \get_fields($postId);
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
}
