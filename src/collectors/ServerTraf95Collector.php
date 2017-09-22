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

class ServerTraf95Collector extends AbstractCollector
{
    public $keys = ['switch_ip', 'port'];

    public $fields = ['server_traf95', 'server_traf95_in'];

    public $aggregation = FileParser::AGGREGATION_LAST;

    public function findObjects()
    {
        return $this->tool->base->smartSearch($this->params, [
            'filters' => [
                'object_ids' => array('cond'=>'in', 'check'=>'ids', 'sql'=>'s.obj_id'),
            ],
            'query' => "
                WITH obs AS (
                    SELECT      s.obj_id,l.zport,l.switch_id
                    FROM        device          s
                    JOIN        device2switchz  l ON l.device_id=s.obj_id
                UNION
                    SELECT      t.obj_id,t.obj_id::text,device_id('virtual95')
                    FROM        tariff          t
                    JOIN        value           c ON c.obj_id=t.obj_id AND c.prop_id=prop_id('tariff:count_resources')
                    WHERE       t.type_id=tariff_type_id('server') AND t.is_grouping
                ), sws AS (
                    SELECT      w.obj_id,w.name,w.ip,str2int(v.value) AS traf_server_id
                    FROM        switch          w
                    JOIN        value           v ON v.obj_id=w.obj_id AND v.prop_id=prop_id('device,switch:traf_server_id')
                    WHERE       w.type_id=switch_type_id('net')
                )
                SELECT      s.obj_id AS object_id,
                            coalesce(host(w.ip),w.name)||' '||coalesce(s.zport,'') AS object,
                            t.ip AS group,w.obj_id AS switch_id,
                            t.obj_id AS device_id,t.ip AS device_ip
                FROM        obs         s
                JOIN        sws         w ON w.obj_id=s.switch_id
                JOIN        device      t ON t.obj_id=w.traf_server_id AND t.state_id!=zstate_id('device,deleted')
                WHERE       TRUE \$filter_cond
                ORDER BY    \"group\"
            ",
        ]);
    }
}