<?php

namespace JPR\QuickGetField;

/**
 * Class JP_Quick_Get_Field
 *
 * Wrapper for all quick getting functionality
 */
class Getter
{
    private $cacher;
    const ALLOWABLE_POST_TYPES_FILTER = 'jp_quick_get_field_allowable_post_types_array';
    const CACHE_ACF_OPTIONS_FILTER = 'jp_quick_get_field_cache_acf_options';

    /**
     * Value used if no ACF data found for a specific post. Allows us to identify which posts haven't been cached yet.
     */
    const NO_ACF_FIELDS_EXIST = 'noFieldsFound';

    private function init()
    {
        // @see https://www.advancedcustomfields.com/resources/acfsave_post/. Priority 20 ensures the new ACF
        // data has been saved and we're caching the correct stuff.
        \add_action('acf/save_post', [$this, 'maybeCacheAcfDataForPostId'], 20);
    }

    public function __construct(CacheInterface $cacher)
    {
        $this->cacher = $cacher;
        $this->init();
    }

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

        $this->cacher->updatePostAcfCache($postId);
    }

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

        if (is_preview()) {
            return $this->getBackupValue($fieldId, $postId);
        }

        $value = $this->cacher->getValue($fieldId, $postId);

        if ($value !== null) {
            return $value;
        }

        return $this->getBackupValue($fieldId, $postId);
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
