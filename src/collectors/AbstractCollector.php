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

use DateTimeImmutable;
use hiapi\rcptraf\utils\FileParser;

abstract class AbstractCollector
{
    protected $tool;

    protected $type;

    protected $params;

    protected $minTime;

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
        return $this->type;
    }

    public function collectAll()
    {
        $groups = $this->groupObjects($this->findObjects());
        if (empty($groups)) {
            return true;
        }

        $parser = new FileParser($this->keys, $this->fields, $this->aggregation);

        foreach ($groups as $group) {
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
        $min = $this->getMinTime();
        $max = new DateTimeImmutable();
        $cur = $min->modify('first day of this month');

        $files = [];
        while ($cur->getTimestamp() <= $max->getTimestamp()) {
            $files[] = implode('/', [$this->dataDir, strtoupper($this->type), $cur->format('Y-m') . '*']);
            $cur = $cur->modify('next month');
        }

        return $files;
    }

    public function getMaxTime()
    {
        if (null === $this->maxTime) {
            $this->maxTime = $this->buildTime($this->params['max_time'], 'now');
        }

        return $this->maxTime;
    }

    public function getMinTime($last = null)
    {
        if ($last) {
            return $this->findMinTime($last);
        }
        if ($this->minTime === null) {
            $this->minTime = $this->buildTime($this->params['min_time'], 'midnight first day of previous month');
        }

        return $this->minTime;
    }

    protected function findMinTime($last)
    {
        $laststamp = strtotime($last);
        if ($laststamp && $laststamp<$this->getMinTime()) {
            return DateTimeImmutable($last);
        }

        return $this->getMinTime();
    }

    protected function buildTime($time, $default)
    {
        if (isset($time)) {
            $time = new DateTimeImmutable($time);
        }
        if (empty($time)) {
            $time = new DateTimeImmutable($default);
        }

        return $time;
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

    protected function buildLastTimeJoin()
    {
        if (isset($this->params['min_time'])) {
            return '';
        }

        return "
            LEFT JOIN (
                SELECT      object_id, max(time) as last_time
                FROM        zuse
                WHERE       time >= date_trunc('month', now() - '1 month'::interval)
                    AND     time < date_trunc('day', now())
                    AND     type_id = ztype_id('bill,overuse,{$this->type}')
                GROUP BY    object_id
            )           AS      mt ON mt.object_id = o.obj_id
        ";
    }

    protected function buildLastTimeSelect()
    {
        if (isset($this->params['min_time'])) {
            return "'{$this->params['min_time']}'::date";
        }

        return "
            (CASE
                WHEN mt.last_time IS NOT NULL
                THEN mt.last_time
                ELSE date_trunc('month', now() - '1 month'::interval)
            END)::date
        ";
    }

    protected function queryObjects($vars)
    {
        return $this->tool->base->smartSearch($this->params, array_merge([
            'filters' => [
                'object_ids' => ['cond'=>'in', 'check'=>'ids', 'sql'=>'o.obj_id'],
            ],
            '$last_time_select' => $this->buildLastTimeSelect(),
            '$last_time_join' => $this->buildLastTimeJoin(),
        ], $vars));
    }
}
