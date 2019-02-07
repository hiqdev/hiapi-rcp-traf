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

class MailDuCollector extends AbstractCollector
{
    public $keys = ['obj_id'];

    public $fields = ['mail_du', 'mail_letters'];

    public $aggregation = FileParser::AGGREGATION_SUM;

    public function findObjects()
    {
        return $this->queryObjects([
            'query' => "
                SELECT      o.obj_id            AS object_id,
                            o.obj_id            AS object,
                            v.ip                AS group,
                            v.obj_id            AS device_id,
                            v.ip                AS device_ip,
                            \$last_time_select  AS last_time
                FROM        zmail       o
                JOIN        mx          x ON x.domain_id=o.hdomain_id AND x.mx=-1
                JOIN        service     e ON e.obj_id=x.service_id
                JOIN        device      v ON v.obj_id=e.device_id AND v.state_id=zstate_id('device,ok')
                JOIN        install     j ON j.object_id=v.obj_id
                JOIN        soft        f ON f.obj_id=j.soft_id AND f.name='rcp_mail_du_counter'
                \$last_time_join
                WHERE       TRUE \$filter_cond
                ORDER BY    \"group\"
            ",
        ]);
    }
}
