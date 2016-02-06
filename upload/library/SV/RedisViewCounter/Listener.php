<?php

class SV_RedisViewCounter_Listener
{
    const AddonNameSpace = 'SV_RedisViewCounter_';

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }
}