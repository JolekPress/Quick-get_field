<?php

namespace JPR\QuickGetField;

class DatabaseCacher implements CacheInterface
{
    /**
     * @var array Holds all fetched cached data in an associative array
     */
    private $fetchedCacheData = [];

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

    /**
     * Update the $postId's ACF cache with the provided $data.
     *
     * @param $postId
     * @param $data
     *
     * @return mixed
     */
    public function updatePostAcfCache($postId, array $data)
    {
        if (Helper::postIdIsOptionsPage($postId)) {
            $autoload = false;
            \update_option(self::OPTIONS_PAGE_OPTION_KEY, $data, $autoload);
        } else {
            \update_post_meta($postId, self::POSTMETA_CACHE_KEY, $data);
        }

        $this->fetchedCacheData[$postId] = $data;

        return $this->fetchedCacheData[$postId];
    }

    /**
     * Retrieve's the ACF cache for a $postId.
     *
     * If the cache has never been checked for the post, it will be updated.
     *
     * @param $postId
     *
     * @return mixed
     */
    public function getPostAcfCache($postId)
    {
        if (isset($this->fetchedCacheData[$postId])) {
            return $this->fetchedCacheData[$postId];
        }

        if (Helper::postIdIsOptionsPage($postId)) {
            $data = \get_option(self::OPTIONS_PAGE_OPTION_KEY);
        } else {
            $getSingleValue = true;
            $data = \get_post_meta($postId, self::POSTMETA_CACHE_KEY, $getSingleValue);
        }

        $this->fetchedCacheData[$postId] = $data;

        return $this->fetchedCacheData[$postId];
    }
}
