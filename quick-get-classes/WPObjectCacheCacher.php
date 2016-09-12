<?php

namespace JPR\QuickGetField;

class WPObjectCacheCacher implements CacheInterface
{
    const CACHE_GROUP = 'jpQuickGetFieldCacheGroup';

    public function updatePostAcfCache($postId, array $data)
    {
        \wp_cache_set($postId, $data, self::CACHE_GROUP);

        return $data;
    }

    public function getPostAcfCache($postId)
    {
        $data = \wp_cache_get($postId, self::CACHE_GROUP);

        return $data;
    }
}
