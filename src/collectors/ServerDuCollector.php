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

class ServerDuCollector extends AbstractCollector
{
    public array $keys = ['switch_ip', 'port'];

    public array$fields = ['server_du', 'server_files', 'server_ssd', 'server_sata'];

    public string $aggregation = FileParser::AGGREGATION_LAST;

    public function findObjects()
    {
        return $this->queryObjects([
            'query' => "
                WITH sws AS (
                    SELECT      w.obj_id,w.name,w.ip,str2int(v.value) AS traf_server_id
                    FROM        switch  w
                    JOIN        value   v ON v.obj_id=w.obj_id AND v.prop_id=prop_id('device,switch:traf_server_id')
                    JOIN        type    t ON t.obj_id=w.type_id AND t.name IN ('net', 'virtual')
                ),
                statserver AS (
                    SELECT      st.*
                    FROM        device  st
                    JOIN        install i   ON i.object_id = st.obj_id
                    JOIN        soft    so  ON so.obj_id = i.soft_id
                    WHERE       st.state_id != zstate_id('device,deleted')
                        AND     so.name IN ('rcp_server_du_counter')
                ),
                zdevicez AS (
                    SELECT      obj_id FROM device
                UNION ALL
                    SELECT  obj_id FROM target WHERE type_id = class_id('videocdn')
                ),
                zdevice2switchz AS (
                    SELECT      device_id, switch_id, zport
                    FROM        device2switchz
                UNION ALL
                    SELECT      t.obj_id, s.obj_id AS switch_id, full_port(b.value, t.name) AS zport
                    FROM        target  t
                    LEFT JOIN   (
                        SELECT obj_id, type_id FROM device WHERE name = 'vCDN'
                    )                   s ON TRUE
                    LEFT JOIN value b ON b.obj_id = s.obj_id AND b.prop_id = prop_id('device,switch:base_port_no'::text) AND s.type_id = switch_type_id('net'::text)
                )
                SELECT      o.obj_id AS object_id,
                            coalesce(host(w.ip),w.name)||' '||coalesce(l.zport,'') AS object,
                            t.ip AS group, w.obj_id AS switch_id,
                            t.obj_id AS device_id, t.ip AS device_ip,
                            \$last_time_select AS last_time
                FROM        zdevicez        o
                JOIN        zdevice2switchz l ON l.device_id=o.obj_id
                JOIN        sws             w ON w.obj_id=l.switch_id
                JOIN        statserver      t ON t.obj_id=w.traf_server_id
                \$last_time_join
                WHERE       TRUE \$filter_cond
                ORDER BY    \"group\"
            ",
        ]);
    }
}
