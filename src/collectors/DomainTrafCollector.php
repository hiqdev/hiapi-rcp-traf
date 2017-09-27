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

class DomainTrafCollector extends AbstractCollector
{
    public $keys = ['login', 'name'];

    public $fields = ['domain_traf', 'domain_traf_in'];

    public $aggregation = FileParser::AGGREGATION_SUM;

    public function findObjects()
    {
        return $this->tool->base->smartSearch($this->params, [
            'filters' => [
                'object_ids' => [ 'cond'=>'in', 'check'=>'ids', 'sql'=>'w.obj_id' ],
            ],
            'query' => "
                WITH domains AS (
                    SELECT      min(obj_id) AS obj_id,max(ip_id) AS ip_id,name,account_id
                    FROM        domain
                    WHERE       ip_id IS NOT NULL AND state_id IN (zstate_id('domain,ok'), zstate_id('domain,blocked'))
                    GROUP BY    name, account_id
                UNION
                    SELECT      obj_id,ip_id,name||'.'||obj_id,account_id
                    FROM        domain
                    WHERE       ip_id IS NOT NULL AND state_id IN (zstate_id('domain,ok'), zstate_id('domain,blocked'))
                ), devices AS (
                    SELECT      d.obj_id, d.name, d.ip
                    FROM        device      d
                    JOIN        install     i ON i.object_id = d.obj_id
                    JOIN        soft        s ON s.obj_id = i.soft_id AND s.name = 'rcp_stats_domain_traf_counter'
                    WHERE       d.state_id=zstate_id('device,ok')
                )
                SELECT      w.obj_id as object_id, w.account||' '||w.name AS object,
                            w.account, w.device_id, w.device_ip, w.group
                FROM        (
                    SELECT      d.obj_id, d.name, a.login AS account,
                                v.obj_id AS device_id, v.name AS group, v.ip AS device_ip
                    FROM        domains     d
                    JOIN        ip          i ON i.obj_id = d.ip_id
                    JOIN        service     s ON s.obj_id = i.service_id
                    JOIN        devices     v ON v.obj_id = s.device_id
                    JOIN        account     a ON a.obj_id = d.account_id
                )           AS          w
                WHERE       TRUE \$filter_cond
                ORDER BY    w.group
            ",
        ]);
    }
}
