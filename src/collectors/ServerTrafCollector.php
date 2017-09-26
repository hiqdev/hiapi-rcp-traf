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

class ServerTrafCollector extends AbstractCollector
{
    public $keys = ['switch_ip', 'port'];

    public $fields = ['server_traf', 'server_traf_in'];

    public $aggregation = FileParser::AGGREGATION_SUM;

    public $configName = "switch_list";

    public $configFormat = "%-15s %-2s %-20s %s";

    public function findObjects()
    {
        return $this->tool->base->smartSearch($this->params, [
            'filters' => [
                'object_ids' => ['cond'=>'in', 'check'=>'ids', 'sql'=>'s.obj_id'],
            ],
            'query' => "
                WITH sws AS (
                    SELECT      w.obj_id,w.name,w.ip,str2int(v.value) AS traf_server_id
                    FROM        switch  w
                    JOIN        value   v ON v.obj_id=w.obj_id AND v.prop_id=prop_id('device,switch:traf_server_id')
                    WHERE       w.type_id=switch_type_id('net')
                )
                SELECT      s.obj_id AS object_id,
                            coalesce(host(w.ip),w.name)||' '||coalesce(l.zport,'') AS object,
                            t.ip AS group, w.obj_id AS switch_id,
                            t.obj_id AS device_id, t.ip AS device_ip
                FROM        device          s
                JOIN        device2switchz  l ON l.device_id=s.obj_id
                JOIN        sws             w ON w.obj_id=l.switch_id
                JOIN        device          t ON t.obj_id=w.traf_server_id AND t.state_id!=zstate_id('device,deleted')
                WHERE       TRUE \$filter_cond
                ORDER BY    \"group\"
            ",
        ]);
    }

    protected function findConfigs($group)
    {
        return $this->tool->base->smartSearch($group, [
            'dbcop' => 'rows',
            'filters' => [
                'device_id' => ['cond' => 'eq', 'check' => 'id', 'sql' => 'str2int(ts.value)' ],
            ],
            'query' => "
                SELECT      sw.ip,st.name AS version,coalesce(cv.value,'AHswitch1Mon249') AS password,bt.name AS bits
                FROM        switch  sw
                JOIN        value   ts ON ts.obj_id=sw.obj_id AND ts.prop_id=prop_id('device,switch:traf_server_id')
                LEFT JOIN   value   cv ON cv.obj_id=sw.obj_id AND cv.prop_id=prop_id('device,switch:community')
                LEFT JOIN   value   sv ON sv.obj_id=sw.obj_id AND sv.prop_id=prop_id('device,switch:snmp_version_id')
                LEFT JOIN   prop    sp ON sp.obj_id=prop_id('device,switch:snmp_version_id')
                LEFT JOIN   type    st ON st.obj_id=coalesce(sv.value,sp.def)::integer
                LEFT JOIN   value   bv ON bv.obj_id=sw.obj_id AND bv.prop_id=prop_id('device,switch:digit_capacity_id')
                LEFT JOIN   prop    bp ON bp.obj_id=prop_id('device,switch:digit_capacity_id')
                LEFT JOIN   type    bt ON bt.obj_id=coalesce(bv.value,bp.def)::integer
                WHERE       sw.type_id=switch_type_id('net') AND sw.ip IS NOT NULL
                    AND     sw.state_id!=zstate_id('device,deleted')
                    \$filter_cond
                ORDER BY    bt.name,sw.ip
            ",
        ]);
    }
}
