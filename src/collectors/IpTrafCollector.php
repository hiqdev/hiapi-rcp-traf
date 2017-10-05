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

class IpTrafCollector extends AbstractCollector
{
    public $keys = ['ip'];

    public $fields = ['ip_traf', 'ip_traf_in'];

    public $aggregation = FileParser::AGGREGATION_SUM;

    public function findObjects()
    {
        return $this->queryObjects([
            'query' => "
                SELECT      o.obj_id AS object_id, o.ip AS object,
                            v.obj_id AS device_id,v.ip AS device_ip,
                            v.name AS group,
                            \$last_time_select AS last_time
                FROM        ip          o
                JOIN        service     e ON e.obj_id=o.service_id
                JOIN        device      v ON v.obj_id=e.device_id
                JOIN        install     j ON j.object_id=v.obj_id
                JOIN        soft        f ON f.obj_id=j.soft_id AND f.name='rcp_ipfw_ip_traf_counter'
                \$last_time_join
                WHERE       TRUE \$filter_cond
                ORDER BY    \"group\"
            ",
        ]);
    }
}
