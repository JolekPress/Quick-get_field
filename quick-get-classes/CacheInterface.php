<?php

namespace JP\QuickGetField;

interface CacheInterface
{
    /**
     * Updates the post's cache by retrieving all ACF fields and storing them. How it retrieves the ACF data is
     * up to the concrete class.
     *
     * @param $postId
     *
     * @param $data - should be an array, but is usually the result of get_fields(), which could be "false".
     *
     * @return mixed
     */
    public function updatePostAcfCache($postId, $data);

    /**
     * Retrieves the ACF cache for the specified $postId. Returns an associative array of $key => $value pairs.
     *
     * @param $postId
     *
     * @return mixed
     */
    public function getPostAcfCache($postId);

    /**
     * Retrieve a specific value from the ACF cache.
     *
     * @param $fieldId
     * @param $postId
     *
     * @return mixed
     */
    public function getValue($fieldId, $postId);
}
