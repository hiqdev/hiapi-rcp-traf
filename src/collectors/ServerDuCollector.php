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

class ServerDuCollector extends AbstractCollector
{
    public $keys = ['switch_ip', 'port'];

    public $fields = ['server_du', 'server_files', 'server_ssd', 'server_sata'];

    public $aggregation = FileParser::AGGREGATION_LAST;

    public function findObjects()
    {
        return $this->tool->base->smartSearch($this->params, [
            'filters' => [
                'object_ids' => array('cond'=>'in', 'check'=>'ids', 'sql'=>'s.obj_id'),
            ],
            'query' => "
                WITH sws AS (
                    SELECT      w.obj_id,w.name,w.ip,str2int(v.value) AS traf_server_id
                    FROM        switch  w
                    JOIN        value   v ON v.obj_id=w.obj_id AND v.prop_id=prop_id('device,switch:traf_server_id')
                    WHERE       w.type_id=switch_type_id('net')
                ),
                statserver AS (
                    SELECT      st.*
                    FROM        device  st
                    JOIN        install i   ON i.object_id = st.obj_id
                    JOIN        soft    so  ON so.obj_id = i.soft_id
                    WHERE       st.state_id != zstate_id('device,deleted')
                        AND     so.name IN ('rcp_server_du_counter')
                )
                SELECT      s.obj_id AS object_id,
                            coalesce(host(w.ip),w.name)||' '||full_port(b.value,l.port,s.dc) AS object,
                            t.ip AS group, w.obj_id AS switch_id,
                            t.obj_id AS device_id, t.ip AS device_ip
                FROM        device          s
                JOIN        device2switchz  l ON l.device_id=s.obj_id
                JOIN        sws             w ON w.obj_id=l.switch_id
                JOIN        statserver      t ON t.obj_id=w.traf_server_id
                LEFT JOIN   value           b ON b.obj_id=l.switch_id AND b.prop_id=prop_id('device,switch:base_port_no')
                ORDER BY    \"group\"
            ",
        ]);
    }
}
