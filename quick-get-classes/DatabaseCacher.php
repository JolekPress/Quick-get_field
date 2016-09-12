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

    const NO_DATA_EXISTS = 'noAcfDataExistsForThisPostId';

    /**
     * Update the $postId's ACF cache with the provided $data.
     *
     * @param $postId
     * @param $data
     *
     * @return mixed
     */
    public function updatePostAcfCache($postId, $data)
    {
        if (empty($data)) {
            $data = self::NO_DATA_EXISTS;
        }

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

        // If empty, it means we haven't even checked it yet
        if (empty($data)) {
            $allFields = \get_fields($postId); // TODO: Figure out a better way to handle this.
            $data = $this->updatePostAcfCache($postId, $allFields);
        }

        $this->fetchedCacheData[$postId] = $data;

        return $this->fetchedCacheData[$postId];
    }

    /**
     * Checks to see if the cache contains the specified $fieldId
     *
     * @param $fieldId
     * @param $postId
     *
     * @return bool
     */
    public function cachedValueExists($fieldId, $postId)
    {
        $cachedData = $this->getPostAcfCache($postId);

        if (isset($cachedData[$fieldId])) {
            return true;
        }

        if ($cachedData === self::NO_DATA_EXISTS) {
            return false;
        }
    }

    /**
     * Retrieves a $fieldId value from the cache.
     *
     * @param $fieldId
     * @param $postId
     *
     * @return null
     */
    public function getValue($fieldId, $postId)
    {
        if (!$this->cachedValueExists($fieldId, $postId)) {
            return null;
        }

        $data = $this->getPostAcfCache($postId);
        return $data[$fieldId];
    }
}
