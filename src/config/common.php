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
            'rcptrafTool' => [
                '__class' => \hiapi\rcptraf\RcpTrafTool::class,
            ],
            'rcptrafTool:account_du' => [
                '__class' => \hiapi\rcptraf\collectors\AccountDuCollector::class,
            ],
            'rcptrafTool:domain_traf' => [
                '__class' => \hiapi\rcptraf\collectors\DomainTrafCollector::class,
            ],
            'rcptrafTool:ip_traf' => [
                '__class' => \hiapi\rcptraf\collectors\IpTrafCollector::class,
            ],
            'rcptrafTool:mail_du' => [
                '__class' => \hiapi\rcptraf\collectors\MailDuCollector::class,
            ],
            'rcptrafTool:server_du' => [
                '__class' => \hiapi\rcptraf\collectors\ServerDuCollector::class,
            ],
            'rcptrafTool:server_traf' => [
                '__class' => \hiapi\rcptraf\collectors\ServerTrafCollector::class,
            ],
            'rcptrafTool:server_traf95' => [
                '__class' => \hiapi\rcptraf\collectors\ServerTraf95Collector::class,
            ],
        ],
    ],
];
