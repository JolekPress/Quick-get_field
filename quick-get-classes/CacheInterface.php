<?php

namespace JPR\QuickGetField;

interface CacheInterface
{
    public function updatePostAcfCache($postId);

    public function getPostAcfCache($postId);

    public function getValue($fieldId, $postId);
}