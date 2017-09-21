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

use DateTime;

abstract class AbstractCollector
{
    protected $tool;

    public $base;

    public $dbc;

    public $type;

    public $keys = ['name'];

    public $fields;

    public $aggregation = FileParser::AGGREGATION_LAST;

    public $logsDir = '/home/LOGS';

    public $sshOptions = '-o ConnectTimeout=29 -o BatchMode=yes -o StrictHostKeyChecking=no -o VisualHostKey=no';

    public function __construct($tool, $type)
    {
        $this->tool = $tool;
        $this->type = $type;
    }

    public function collectAll($params)
    {
        $groups = $this->groupObjects($this->findObjects($params));
        foreach ($groups as $group) {
            $this->collect($group);
        }

        return true;
    }

    public function groupObjects($objects)
    {
        foreach ($objects as $row) {
            $group = $row['group'];
            $res[$group]['objects'][$row['object']] = $row;
            if (empty($res[$group]['device_ip'])) {
                $res[$group] = $row;
            }
        }

        return $res;
    }

    abstract public function findObjects();

    public function collect(array $group)
    {
        $path = $this->copyData($group['device_ip']);
        $data = new FileParser($this->keys, $this->fields, $this->aggregation);
        $data->parse($path);
        unlink($path);
        foreach ($group['objects'] as $object => $row) {
            $this->saveElement($row, $data->getValues($object));
        }
    }

    protected function saveElement($row, $values)
    {
        if (!$values) {
            return;
        }
        $curr_date = new DateTime();
        $uses = [];
        foreach ($values as $date => $fields) {
            $z_date = new DateTime($date);
            /// $z_date->getTimestamp() < $last_date->getTimestamp()
            if ($z_date->getTimestamp() > $curr_date->getTimestamp()) {
                continue;
            }
            foreach ($fields as $field => $value) {
                $uses[] = [
                    'object_id' => $row['object_id'],
                    'type'      => $field,
                    'time'      => $date,
                    'amount'    => $value,
                ];
            }
        }
        if ($uses) {
            $this->tool->base->usesSet($uses);
        }
    }

    protected function copyData($ip)
    {
        $dir = $this->logsDir . '/' . strtoupper($this->type);
        $dest = '/tmp/' . $this->type . '.' . getmypid() . '.' . $ip;
        if (false === file_put_contents($dest, '')) {
            throw new \Exception("failed copy traf data to $dest");
        }
        foreach ($this->getFiles() as $file) {
            $command = "ssh {$this->sshOptions} root@$ip '/bin/cat $dir/$file*' >> $dest";
            exec($command);
        }

        return $dest;
    }

    protected function getFiles()
    {
        $curr = new DateTime();
        $prev = new DateTime('midnight first day of previous month');

        return [$prev->format('Y-m'), $curr->format('Y-m')];
    }
}
