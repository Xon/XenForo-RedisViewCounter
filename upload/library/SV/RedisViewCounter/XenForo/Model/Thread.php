<?php

class SV_RedisViewCounter_XenForo_Model_Thread extends XFCP_SV_RedisViewCounter_XenForo_Model_Thread
{
    public function logThreadView($threadId)
    {
        $registry = $this->_getDataRegistryModel();
        if (!method_exists($registry, 'getCredis'))
        {
            parent::logThreadView($threadId);
            return;
        }
        $cache = $this->_getCache(true);
        $credis = $registry->getCredis($cache);
        $pattern = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . 'views.thread.';

        $key = $pattern . $threadId;

        $credis->incr($key);
    }

    public function updateThreadViews()
    {
        $registry = $this->_getDataRegistryModel();
        if (!method_exists($registry, 'getCredis'))
        {
            parent::updateThreadViews();
            return;
        }
        $cache = $this->_getCache(true);
        $credis = $registry->getCredis($cache);
        $pattern = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . 'views.thread.';

        // indicate to the redis instance would like to process X items at a time.
        $count = 10000;
        // find indexes matching the pattern
        $cursor = null;
        $keys = array();
        while(true)
        {
            $next_keys = $credis->scan($cursor, $pattern ."*", $count);
            // scan can return an empty array
            if($next_keys)
            {
                $keys += $next_keys;
            }
            if (empty($cursor) || $next_keys === false)
            {
                break;
            }
        }
        if ($keys)
        {
            $db = $this->_getDb();
            foreach($keys as $key)
            {
                // atomically get & delete the key
                $credis->pipeline()->multi();
                $credis->get($key);
                $credis->del($key);
                $arrData = $credis->exec();
                $thread_view_count = $arrData[0];
                // only update the database if a thread view happened
                if (!empty($thread_view_count))
                {
                    $thread_id = str_replace($pattern, '', $key);
                    $db->query('UPDATE xf_thread SET view_count = view_count + ? where thread_id = ?', array($thread_view_count, $thread_id));
                }
            }
        }
    }
}