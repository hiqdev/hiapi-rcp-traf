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
use hiapi\rcptraf\RcpTrafTool;
use hiapi\rcptraf\utils\FileParser;

abstract class AbstractCollector
{
    protected RcpTrafTool $tool;

    protected string $type = '';

    protected array $params = [];

    protected ?DateTimeImmutable $minTime = null;

    public array $keys = ['name'];

    public array $fields = [];

    public string $aggregation = FileParser::AGGREGATION_LAST;

    public string $dataDir = '/home/LOGS';

    public string $configPath = '';

    public function __construct(RcpTrafTool $tool, string $type, array $params)
    {
        $this->tool = $tool;
        $this->type = $type;
        $this->params = $params;
    }

    public function getType() : string
    {
        return $this->type;
    }

    public function collectAll() : bool
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

    public function groupObjects($objects) : array
    {
        $res = [];
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

    /**
     * Build config for ip
     *
     * @param string $ip
     * @return array
     */
    public function buildConfig($ip)
    {
        return null;
    }

    public function getFiles() : array
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

    /**
     * Return max time of syncronization
     *
     * @param void
     * @return DateTimeImmutable
     */
    public function getMaxTime() : DateTimeImmutable
    {
        if (null === $this->maxTime) {
            $this->maxTime = $this->buildTime($this->params['max_time'], 'now');
        }

        return $this->maxTime;
    }

    /**
     * Return time of syncronization
     *
     * @param string $last
     * @return DateTimeImmutable
     */
    public function getMinTime(string $last = null) : DateTimeImmutable
    {
        if ($last) {
            return $this->findMinTime($last);
        }
        if ($this->minTime === null) {
            $this->minTime = $this->buildTime($this->params['min_time'], 'midnight first day of previous month');
        }

        return $this->minTime;
    }

    /**
     * Calculate time
     *
     * @param string $last
     * @return DateTimeImmutable
     */
    protected function findMinTime(string $last) : DateTimeImmutable
    {
        $laststamp = strtotime($last);
        $minTime = $this->getMinTime();

        if ($laststamp && $laststamp < $minTime->getTimestamp()) {
            return DateTimeImmutable($last);
        }

        return $this->getMinTime();
    }

    /**
     * Create timestamp
     *
     * @param string $time
     * @param string $default
     * @return DateTimeImmutable
     */
    protected function buildTime(?string $time = '', string $default) : DateTimeImmutable
    {
        if (isset($time)) {
            return new DateTimeImmutable($time);
        }

        return new DateTimeImmutable($default);
    }

    public function usesSet(array $uses) : void
    {
        if ($uses) {
            $this->tool->base->usesSet($uses);
        }
    }

    protected function renderConfig(array $rows, string $format) : string
    {
        $res = '';
        foreach ($rows as $row) {
            $res .= vsprintf($format, $row) . "\n";
        }

        return $res;
    }

    protected function buildLastTimeJoin() : string
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

    protected function buildLastTimeSelect() : string
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

    protected function queryObjects(array $vars = []) : array
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
