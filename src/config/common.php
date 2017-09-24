<?php
/**
 * hiAPI RCP Traf Collector
 *
 * @link      https://github.com/hiqdev/hiapi-rcp-traf
 * @package   hiapi-rcp-traf
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017, HiQDev (http://hiqdev.com/)
 */

return [
    'container' => [
        'definitions' => [
            'rcptraf-tool' => [
                'class' => \hiapi\rcptraf\tools\RcpTrafTool::class,
            ],
            'rcptraf-tool:account_du' => [
                'class' => \hiapi\rcptraf\collectors\AccountDuCollector::class,
            ],
            'rcptraf-tool:domain_traf' => [
                'class' => \hiapi\rcptraf\collectors\DomainTrafCollector::class,
            ],
            'rcptraf-tool:ip_traf' => [
                'class' => \hiapi\rcptraf\collectors\IpTrafCollector::class,
            ],
            'rcptraf-tool:mail_du' => [
                'class' => \hiapi\rcptraf\collectors\MailDuCollector::class,
            ],
            'rcptraf-tool:server_du' => [
                'class' => \hiapi\rcptraf\collectors\ServerDuCollector::class,
            ],
            'rcptraf-tool:server_traf' => [
                'class' => \hiapi\rcptraf\collectors\ServerTrafCollector::class,
            ],
            'rcptraf-tool:server_traf95' => [
                'class' => \hiapi\rcptraf\collectors\ServerTraf95Collector::class,
            ],
        ],
    ],
];
