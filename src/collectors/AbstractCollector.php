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
use DateTime;

abstract class AbstractCollector
{
    protected $tool;

    protected $type;

    protected $params;

    public $keys = ['name'];

    public $fields;

    public $aggregation = FileParser::AGGREGATION_LAST;

    public $dataDir = '/home/LOGS';

    public $configPath = '';

    public function __construct($tool, $type, $params)
    {
        $this->tool = $tool;
        $this->type = $type;
        $this->params = $params;
    }

    public function getType()
    {
        return $this->tupe;
    }

    public function collectAll()
    {
        $groups = $this->groupObjects($this->findObjects());
        if (empty($groups)) {
            return true;
        }

        foreach ($groups as $group) {
            $parser = new FileParser($this->keys, $this->fields, $this->aggregation);
            $worker = new Worker($this, $parser, $group['device_ip'], $group['objects']);
            $worker->collect();
        }

        return true;
    }

    public function groupObjects($objects)
    {
        foreach ($objects as $row) {
            $group = $row['group'];
            if (empty($res[$group]['device_ip'])) {
                $res[$group] = $row;
            }
            $res[$group]['objects'][$row['object']] = $row;
        }

        return $res;
    }

    abstract public function findObjects();

    public function buildConfig($ip)
    {
        return null;
    }

    public function getFiles()
    {
        $dir = $this->dataDir . '/' . strtoupper($this->type);
        $curr = new DateTime();
        $prev = new DateTime('midnight first day of previous month');

        return [$dir ."/" . $prev->format('Y-m') . "*", $dir . "/" . $curr->format('Y-m') . "*"];
    }

    public function usesSet(array $uses)
    {
        if ($uses) {
            $this->tool->base->usesSet($uses);
        }
    }

    protected function renderConfig($rows, $format)
    {
        $res = '';
        foreach ($rows as $row) {
            $res .= vsprintf($format, $row) . "\n";
        }

        return $res;
    }
}
