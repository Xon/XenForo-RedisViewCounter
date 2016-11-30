<?php

class SV_RedisViewCounter_XenForo_Model_Attachment extends XFCP_SV_RedisViewCounter_XenForo_Model_Attachment
{
    public function logAttachmentView($attachmentId)
    {
        $registry = $this->_getDataRegistryModel();
        $cache = $this->_getCache(true);
        if (!method_exists($registry, 'getCredis') || !($credis = $registry->getCredis($cache)))
        {
            parent::logAttachmentView($attachmentId);
            return;
        }
        $pattern = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . 'views.attachment.';

        $key = $pattern . $attachmentId;

        $credis->incr($key);
    }

    const LUA_GETDEL_SH1 = '6ba37a6998bb00d0b7f837a115df4b20388b71e0';

    public function updateAttachmentViews()
    {
        $registry = $this->_getDataRegistryModel();
        $cache = $this->_getCache(true);
        if (!method_exists($registry, 'getCredis') || !($credis = $registry->getCredis($cache)))
        {
            parent::updateAttachmentViews();
            return;
        }
        $db = $this->_getDb();
        $useLua = method_exists($registry, 'useLua') && $registry->useLua($cache);
        $pattern = Cm_Cache_Backend_Redis::PREFIX_KEY . $cache->getOption('cache_id_prefix') . 'views.attachment.';

        // indicate to the redis instance would like to process X items at a time.
        $count = 100;
        // prevent looping forever
        $loopGuard = 10000;
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
                $id = str_replace($pattern, '', $key);
                if (preg_match('/^[0-9]+$/', $id) != 1)
                {
                    continue;
                }
                // atomically get & delete the key
                if ($useLua)
                {
                    $view_count = $credis->evalSha(self::LUA_GETDEL_SH1, array($key), 1);
                    if (is_null($view_count))
                    {
                        $script =
                            "local oldVal = redis.call('GET', KEYS[1]) ".
                            "redis.call('DEL', KEYS[1]) ".
                            "return oldVal ";
                        $view_count = $credis->eval($script, array($key), 1);
                    }
                }
                else
                {
                    $credis->pipeline()->multi();
                    $credis->get($key);
                    $credis->del($key);
                    $arrData = $credis->exec();
                    $view_count = $arrData[0];
                }
                $view_count = intval($view_count);
                // only update the database if a thread view happened
                if (!empty($view_count))
                {

                    $db->query('UPDATE xf_attachment SET view_count = view_count + ? where attachment_id = ?', array($view_count, $id));
                }
            }
        }
        while($loopGuard > 0 && !empty($cursor));
    }
}