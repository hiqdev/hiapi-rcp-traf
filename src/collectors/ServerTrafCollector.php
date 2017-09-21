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

    public function findObjects()
    {
        return $this->tool->dbc->rows("
            WITH sws AS (
                SELECT      w.obj_id,w.name,w.ip,str2int(v.value) AS traf_server_id
                FROM        switch  w
                JOIN        value   v ON v.obj_id=w.obj_id AND v.prop_id=prop_id('device,switch:traf_server_id')
                WHERE       w.type_id=switch_type_id('net')
            )
            SELECT      t.ip AS group, w.obj_id AS switch_id, s.obj_id AS object_id,
                        coalesce(host(w.ip),w.name)||' '||coalesce(l.zport,'') AS object,
                        t.obj_id AS device_id, t.ip AS device_ip
            FROM        device          s
            JOIN        device2switchz  l ON l.device_id=s.obj_id
            JOIN        sws             w ON w.obj_id=l.switch_id
            JOIN        device          t ON t.obj_id=w.traf_server_id AND t.state_id!=state_id('device,deleted')
            ORDER BY    \"group\"
        ");
    }
}
