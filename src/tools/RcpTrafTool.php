<?php

namespace hiapi\rcptraf\tools;

use apiTool;

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
