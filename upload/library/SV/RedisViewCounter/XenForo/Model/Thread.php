<?php

class SV_RedisViewCounter_XenForo_Model_Thread extends XFCP_SV_RedisViewCounter_XenForo_Model_Thread
{
    public function logThreadView($threadId)
    {
        $registry = $this->_getDataRegistryModel();
        $cache = $this->_getCache(true);
        if (!method_exists($registry, 'getCredis') || !($credis = $registry->getCredis($cache)))
        {
            parent::logThreadView($threadId);
            return;
        }
        $pattern = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . 'views.thread.';

        $key = $pattern . $threadId;

        $credis->incr($key);
    }

    const LUA_GETDEL_SH1 = '6ba37a6998bb00d0b7f837a115df4b20388b71e0';

    public function updateThreadViews()
    {
        $registry = $this->_getDataRegistryModel();
        $cache = $this->_getCache(true);
        if (!method_exists($registry, 'getCredis') || !($credis = $registry->getCredis($cache)))
        {
            parent::updateThreadViews();
            return;
        }
        $db = $this->_getDb();
        $useLua = method_exists($registry, 'useLua') && $registry->useLua($cache);
        $pattern = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . 'views.thread.';

        // indicate to the redis instance would like to process X items at a time.
        $count = 10000;
        // prevent looping forever
        $loopGuard = 100;
        // find indexes matching the pattern
        $cursor = null;
        do
        {
            $keys = $credis->scan($cursor, $pattern ."*", $count);
            $loopGuard--;
            if ($keys === false)
            {
                break;
            }

            foreach($keys as $key)
            {
                // atomically get & delete the key
                if ($useLua)
                {
                    $thread_view_count = $credis->evalSha(self::LUA_GETDEL_SH1, array($key), 1);
                    if (is_null($thread_view_count))
                    {
                        $script =
                            "local oldVal = redis.call('GET', KEYS[1]) ".
                            "redis.call('DEL', KEYS[1]) ".
                            "return oldVal ";
                        $thread_view_count = $credis->eval($script, array($key), 1);
                    }
                }
                else
                {
                    $credis->pipeline()->multi();
                    $credis->get($key);
                    $credis->del($key);
                    $arrData = $credis->exec();
                    $thread_view_count = $arrData[0];
                }
                $thread_view_count = intval($thread_view_count);
                // only update the database if a thread view happened
                if (!empty($thread_view_count))
                {
                    $thread_id = str_replace($pattern, '', $key);
                    $db->query('UPDATE xf_thread SET view_count = view_count + ? where thread_id = ?', array($thread_view_count, $thread_id));
                }
            }
        }
        while($loopGuard > 0 && !empty($cursor));
    }
}