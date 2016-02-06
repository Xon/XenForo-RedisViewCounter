<?php

class SV_RedisViewCounter_Install
{
    public static function install($installedAddon, array $addonData, SimpleXMLElement $xml)
    {
        XenForo_Model::create("XenForo_Model_Thread")->updateThreadViews();
    }

    public static function uninstall()
    {
        XenForo_Model::create("XenForo_Model_Thread")->updateThreadViews();
    }
}