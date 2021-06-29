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

class ServerTraf95Collector extends AbstractCollector
{
    public array $keys = ['switch_ip', 'port'];

    public array $fields = ['server_traf95', 'server_traf95_in'];

    public string $aggregation = FileParser::AGGREGATION_LAST;

    public function findObjects()
    {
        return $this->queryObjects([
            'query' => "
                WITH obs AS (
                    SELECT      s.obj_id,l.zport,l.switch_id
                    FROM        device          s
                    JOIN        device2switchz  l ON l.device_id=s.obj_id
                UNION
                    SELECT      s.obj_id, full_port(b.value, s.name) AS zport, l.obj_id AS switch_id
                    FROM        target  s
                    LEFT JOIN   (
                        SELECT obj_id, type_id FROM device WHERE name = 'vCDN'
                    )                   l ON TRUE
                    LEFT JOIN value b ON b.obj_id = l.obj_id AND b.prop_id = prop_id('device,switch:base_port_no'::text) AND l.type_id = switch_type_id('net'::text)
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
                SELECT      o.obj_id AS object_id,
                            coalesce(host(w.ip),w.name) || ' ' || coalesce(o.zport,'') AS object,
                            t.ip AS group,w.obj_id AS switch_id,
                            t.obj_id AS device_id,t.ip AS device_ip,
                            \$last_time_select AS last_time
                FROM        obs         o
                JOIN        sws         w ON w.obj_id=o.switch_id
                JOIN        device      t ON t.obj_id=w.traf_server_id AND t.state_id!=zstate_id('device,deleted')
                \$last_time_join
                WHERE       TRUE \$filter_cond
                ORDER BY    \"group\"
            ",
        ]);
    }

    public function buildConfig($ip)
    {
        $rows = $this->tool->dbc->rows("
            SELECT      t.obj_id AS id,join((host(w.ip)||':'||x.zport,' ')) AS ps
            FROM        tariff          t
            JOIN        sale            s ON s.tariff_id=t.obj_id
            JOIN        device          v ON v.obj_id=s.object_id
            JOIN        device2switchz  x ON x.device_id=s.object_id
            JOIN        switch          w ON w.obj_id=x.switch_id AND w.type_id=switch_type_id('net')
            JOIN        value           c ON c.obj_id=t.obj_id AND c.prop_id=prop_id('tariff:count_resources')
            WHERE       t.type_id=ztype_id('tariff,server') AND t.is_grouping
            GROUP BY    t.obj_id
            HAVING      count( *)>1
        ");

        return $this->renderConfig($rows, '127.127.127.127 %-15s   %s');
    }
}
