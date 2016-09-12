<?php

namespace JPR\QuickGetField;

class WPObjectCacheCacher implements CacheInterface
{
    const NO_DATA_EXISTS = 'noAcfDataExistsForThisPostId';
    const CACHE_GROUP = 'jpQuickGetFieldCacheGroup';

    private function getCache($key)
    {
        $value = \wp_cache_get($key, self::CACHE_GROUP);

        if ($value === false) {
            return null;
        }

        return $value;
    }

    public function updatePostAcfCache($postId, $data)
    {
        if (empty($data)) {
            $data = self::NO_DATA_EXISTS;
        }

        \wp_cache_set($postId, $data, self::CACHE_GROUP);

        return $data;
    }

    public function getPostAcfCache($postId)
    {
        $data = $this->getCache($postId);

        if ($data === null) {
            $data = \get_fields($postId);
            $this->updatePostAcfCache($postId, $data);
        }

        return $data;
    }

    public function getValue($fieldId, $postId)
    {
        $cache = $this->getPostAcfCache($postId);

        if ($cache === self::NO_DATA_EXISTS) {
            return null;
        }

        if (isset($cache[$fieldId])) {
            return $cache[$fieldId];
        }

        return null;
    }
}
