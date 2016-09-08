<?php

namespace JP\QuickGetField;

class Helper
{
    /**
     * Helper method to determine if a given $postId is actually a request for an ACF option.
     *
     * @param $postId
     * @return bool
     */
    public static function postIdIsOptionsPage($postId)
    {
        return in_array($postId, ['option', 'options']);
    }

    /**
     * Helper method to determine if ACF plugin is enabled.
     *
     * @return bool
     */
    public static function isAcfEnabled()
    {
        return function_exists('get_field');
    }
}