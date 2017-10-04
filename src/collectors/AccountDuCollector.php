<?php
/**
 * hiAPI RCP Traf Collector
 *
 * @link      https://github.com/hiqdev/hiapi-rcp-traf
 * @package   hiapi-rcp-traf
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\rcptraf\collectors;

use hiapi\rcptraf\utils\FileParser;

class AccountDuCollector extends AbstractCollector
{
    public $keys = ['login'];

    public $fields = ['account_du', 'account_maxdir'];

    public $aggregation = FileParser::AGGREGATION_LAST;

    public function findObjects()
    {
        return $this->tool->base->smartSearch($this->params, [
            'filters' => [
                'object_ids' => ['cond'=>'in', 'check'=>'ids', 'sql'=>'o.obj_id'],
            ],
            '$last_time_select_cond' => $this->getLastTimeSelectCond(),
            '$last_time_join_cond' => $this->getLastTimeJoinCond(),
            'query' => "
                SELECT      o.obj_id AS object_id,
                            o.login AS object,
                            v.ip AS group,
                            v.obj_id AS device_id, v.ip AS device_ip,
                            \$last_time_select_cond AS last_time
                FROM        account     o
                JOIN        service     e ON e.obj_id=o.service_id
                JOIN        device      v ON v.obj_id=e.device_id AND v.state_id=zstate_id('device,ok')
                JOIN        install     j ON j.object_id=v.obj_id
                JOIN        soft        f ON f.obj_id=j.soft_id AND f.name='rcp_account_du_counter'
                \$last_time_join_cond
                WHERE       TRUE    \$filter_cond
                ORDER BY    \"group\"
            ",
        ]);
    }
}
