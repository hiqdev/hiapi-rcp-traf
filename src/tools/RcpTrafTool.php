<?php
/**
 * hiAPI RCP Traf Collector
 *
 * @link      https://github.com/hiqdev/hiapi-rcp-traf
 * @package   hiapi-rcp-traf
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\rcptraf\tools;

/**
 * RCP traffic collector tool.
 *
 * @author Andrii Vasyliev <sol@hiqdev.com>
 */
class RcpTrafTool extends \hiapi\components\AbstractTool
{
    public function usesCollect($jrow)
    {
        $res = [];
        foreach ($jrow['types'] as $type) {
            $collector = $this->di->get("rcptraf-tool:$type", [$this, $type]);
            $res[$type] = $collector->collectAll($jrow);
        }

        return $res;
    }
}
