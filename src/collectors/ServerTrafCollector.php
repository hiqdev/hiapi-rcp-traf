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

class ServerTrafCollector extends AbstractCollector
{
    public $keys = ['switch_ip', 'port'];

    public $fields = ['server_traf', 'server_traf_in'];

    public $aggregation = FileParser::AGGREGATION_SUM;

    public function findObjects()
    {
        return $this->queryObjects([
            'query' => "
                WITH sws AS (
                    SELECT      w.obj_id,w.name,w.ip,str2int(v.value) AS traf_server_id
                    FROM        switch  w
                    JOIN        value   v ON v.obj_id=w.obj_id AND v.prop_id=prop_id('device,switch:traf_server_id')
                    WHERE       w.type_id=switch_type_id('net')
                )
                SELECT      o.obj_id AS object_id,
                            coalesce(host(w.ip),w.name) || ' ' || coalesce(l.zport,'') AS object,
                            t.ip AS group, w.obj_id AS switch_id,
                            t.obj_id AS device_id, t.ip AS device_ip,
                            \$last_time_select AS last_time
                FROM        device          o
                JOIN        device2switchz  l ON l.device_id = o.obj_id
                JOIN        sws             w ON w.obj_id = l.switch_id
                JOIN        device          t ON t.obj_id = w.traf_server_id AND t.state_id!=zstate_id('device,deleted')
                \$last_time_join
                WHERE       TRUE \$filter_cond
                ORDER BY    \"group\"
            ",
        ]);
    }

    public function buildConfig($ip)
    {
        $ip = $this->tool->dbc->quote($ip);
        $rows = $this->tool->dbc->rows("
            SELECT      sw.ip,st.name AS version,coalesce(cv.value,'AHswitch1Mon249') AS password,bt.name AS bits
            FROM        switch      sw
            JOIN        value       tv ON tv.obj_id = sw.obj_id AND tv.prop_id=prop_id('device,switch:traf_server_id')
            LEFT JOIN   value       cv ON cv.obj_id = sw.obj_id AND cv.prop_id=prop_id('device,switch:community')
            LEFT JOIN   prop        sp ON sp.obj_id = prop_id('device,switch:snmp_version_id')
            LEFT JOIN   value       sv ON sv.obj_id = sw.obj_id AND sv.prop_id=sp.obj_id
            LEFT JOIN   zref        st ON st.obj_id::text = coalesce(sv.value,sp.def)
            LEFT JOIN   prop        bp ON bp.obj_id = prop_id('device,switch:digit_capacity_id')
            LEFT JOIN   value       bv ON bv.obj_id = sw.obj_id AND bv.prop_id=bp.obj_id
            LEFT JOIN   zref        bt ON bt.obj_id::text = coalesce(bv.value,bp.def)
            WHERE       sw.type_id=switch_type_id('net') AND sw.ip IS NOT NULL
                AND     sw.state_id!=zstate_id('device,deleted')
                AND     tv.value=device_id(str2inet($ip))::text
            ORDER BY    bt.name,sw.ip
        ");

        return $this->renderConfig($rows, '%-15s %-2s %-20s %s');
    }
}
