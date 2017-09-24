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

    protected $type;

    protected $params;

    public $keys = ['name'];

    public $fields;

    public $aggregation = FileParser::AGGREGATION_LAST;

    public $logsDir = '/home/LOGS';

    public $sshOptions = '-o ConnectTimeout=29 -o BatchMode=yes -o StrictHostKeyChecking=no -o VisualHostKey=no';

    public $sshPort = 22;

    public function __construct($tool, $type, $params)
    {
        $this->tool = $tool;
        $this->type = $type;
        $this->params = $params;
    }

    public function collectAll()
    {
        $groups = $this->groupObjects($this->findObjects());
        if (empty($groups)) {
            return true;
        }
        foreach ($groups as $group) {
            $this->collect($group);
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

    public function collect(array $group)
    {
        $path = $this->copyData($group['device_ip']);
        if ($path === false) {
            return;
        }

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
            if (!preg_match('/[0-9]{4}\-[0-9]{1,2}\-[0-9]{1,2}/', $date, $matches)) {
                continue;
            }

            $date = $matches[0];
            try {
                $z_date = new DateTime($date);
            } catch (\Exception $e) {
                continue;
            }

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
        $dest = '/tmp/' . $this->type . '.' . getmypid() . '.' . $ip;
        if (false === file_put_contents($dest, '')) {
            throw new \Exception("failed copy traf data to $dest");
        }

        if (!$socket = @fsockopen($ip, $this->sshPort, $errno, $errstr, 3)) {
            return false;
        } else {
            fclose($socket);
        }

        $files = implode(" ", $this->getFiles());
        $command = "ssh {$this->sshOptions} -p{$this->sshPort} root@$ip '/bin/cat $files' > $dest 2>/dev/null";
        exec($command, $output, $result);

        return $dest;
    }

    protected function getFiles()
    {
        static $dir;
        if ($dir === null) {
            $dir = $this->logsDir . '/' . strtoupper($this->type);
        }

        $curr = new DateTime();
        $prev = new DateTime('midnight first day of previous month');

        return [$dir ."/" . $prev->format('Y-m') . "*", $dir . "/" . $curr->format('Y-m') . "*"];
    }
}
