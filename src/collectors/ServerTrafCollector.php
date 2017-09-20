<?php

namespace hiapi\rcptraf\collectors;

class ServerTrafCollector extends AbstractCollector
{
    public $keys = ['switch_ip', 'port'];

    public $fields = ['server_traf', 'server_traf_in'];

    public $aggregation = FileParser::AGGREGATION_SUM;

    public function findObjects()
    {
        return $this->tool->dbc->rows("
            SELECT      t.ip AS group,w.obj_id AS object_id,
                        coalesce(host(w.ip),w.name)||' '||coalesce(l.zport,'') AS element,
                        s.obj_id AS element_id,
                        r.time::date AS last_date,t.obj_id AS device_id,t.ip AS device_ip
            FROM        device          s
            JOIN        device2switchz  l ON l.device_id=s.obj_id
            JOIN        (
                SELECT      w.obj_id,w.name,w.ip,str2int(v.value) AS traf_server_id
                FROM        switch  w
                JOIN        value   v ON v.obj_id=w.obj_id AND v.prop_id=prop_id('device,switch:traf_server_id')
                WHERE       w.type_id=switch_type_id('net')
            )           AS              w ON w.obj_id=l.switch_id
            JOIN        device          t ON t.obj_id=w.traf_server_id AND t.state_id!=state_id('device,deleted')
            LEFT JOIN   (
                SELECT      object_id,max(time) AS time
                FROM        zuse
                WHERE       type_id=overuse_type_id('server_traf')
                        AND time>'2017-07-01'
                GROUP BY    object_id
            )           AS              r ON r.object_id=s.obj_id
            ORDER BY    \"group\",last_date ASC
        ");
    }
}
