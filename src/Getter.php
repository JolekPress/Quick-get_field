<?php

namespace JP\QuickGetField;

/**
 * Class JP_Quick_Get_Field
 *
 * Wrapper for all quick getting functionality
 */
class Getter
{
    private $cacher;

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
        return \apply_filters('jp_quick_get_field_cache_acf_options', true);
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
            'options',
        ];

        $postTypes = \apply_filters('jp_quick_get_field_allowable_post_types_array', $postTypes);

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

        $value = $this->cacher->getValue($fieldId, $postId);

        if ($value !== null) {
            return $value;
        }

        if (Helper::isAcfEnabled()) {
            return \get_field($fieldId, $postId);
        }

        // TODO: Check if repeater and parse
        return \get_post_meta($postId, $fieldId);
    }
}