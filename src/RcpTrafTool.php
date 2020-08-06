<?php
/**
 * hiAPI RCP Traf Collector
 *
 * @link      https://github.com/hiqdev/hiapi-rcp-traf
 * @package   hiapi-rcp-traf
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\rcptraf;

/**
 * RCP traffic collector tool.
 *
 * @author Andrii Vasyliev <sol@hiqdev.com>
 */
class RcpTrafTool extends \hiapi\components\AbstractTool
{
    public function usesCollect($params)
    {
        $res = [];
        foreach ($params['types'] as $type) {
            $name = "rcptrafTool:$type";
            if (!$this->di->has($name)) {
                continue;
            }
            $collector = $this->di->get($name, [$this, $type, $params]);
            $res[$type] = $collector->collectAll();
        }

        return $res;
    }
}
