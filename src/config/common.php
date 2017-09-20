<?php

return [
    'components' => [
        'rcptraf' => [
            'class' => \hiapi\rcptraf\components\RcpTraf::class,
        ],
    ],
    'container' => [
        'definitions' => [
            'rcptraf:server_traf'   => \hiapi\rcptraf\collectors\ServerTrafCollector::class,
            'rcptraf:server_traf95' => \hiapi\rcptraf\collectors\ServerTraf95Collector::class,
            'rcptraf:server_du'     => \hiapi\rcptraf\collectors\ServerDuCollector::class,
        ],
    ],
];
