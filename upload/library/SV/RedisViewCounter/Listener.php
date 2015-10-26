<?php

class SV_RedisViewCounter_Listener
{
    const AddonNameSpace = 'SV_RedisViewCounter_';

    public static function install($installedAddon, array $addonData, SimpleXMLElement $xml)
    {
        XenForo_Model::create("XenForo_Model_Thread")->updateThreadViews();
    }

    public static function uninstall()
    {
        XenForo_Model::create("XenForo_Model_Thread")->updateThreadViews();
    }

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }
}