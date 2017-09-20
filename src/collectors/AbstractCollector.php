<?php

namespace hiapi\rcptraf\collectors;

use DateTime;
use DateInterval;

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
            $res[$group]['elements'][$row['element']] = $row;
            if (empty($res[$group]['device_ip'])) {
                $res[$group] = $row;
            }
        }

        return $res;
    }

    abstract public function findObjects();

    public function collect(array $group)
    {
        $this->group = $group;
        $elements = $group['elements'];
        $files = $this->getFiles(reset($elements));
        $dest = $this->copyData($files);
        $data = new FileParser($this->keys, $this->fields, $this->aggregation);
        $data->parse($dest);
        foreach ($elements as $element => $row) {
            $this->saveElement($row, $data->getValues($element));
        }
    }

    protected function saveElement($row, $values)
    {
        if (!$values) {
            return;
        }
        $last_date = new DateTime($row['last_date']);
        $curr_date = new DateTime();
        if ($last_date->getTimestamp() > $curr_date->getTimestamp()) {
            $last_date = $curr_date;
        }
        $last_date->sub(new DateInterval('P1D'));
        foreach ($values as $date => $fields) {
            $z_date = new DateTime($date);
            if (    $z_date->getTimestamp() < $last_date->getTimestamp()
                ||  $z_date->getTimestamp() > $curr_date->getTimestamp())
            {
                fwrite(STDERR, "SKIPPPPPPED $row[element] $date $field $value $row[last_date]\n");
                continue;
            }
            foreach ($fields as $field => $value) {
                print "$row[element] $date $field $value\n";
            }
        }
    }

    protected function copyData($files)
    {
        $dir = $this->logsDir . '/' . strtoupper($this->type);
        $dest = '/tmp/' . $this->type . '.' . getmypid();
        file_put_contents($dest, '');
        foreach ($files as $file) {
            $ip = $this->group['device_ip'];
            $command = "ssh {$this->sshOptions} root@$ip '/bin/cat $dir/$file*' >> $dest";
            var_dump($command);
            exec($command);
        }

        return $dest;
    }

    protected function getFiles(array $element)
    {
        $from = new DateTime($element['last_date']);
        $curr = new DateTime();
        list($fy, $fm) = explode('-', $from->format('Y-m'));
        list($cy, $cm) = explode('-', $curr->format('Y-m'));
        $fn = $fy*12 + $fm - 1;
        $cn = $cy*12 + $cm - 1;
        if ($fn<$cn-1) {
            $fn = $cn-1;
        }

        $files = [];
        for ($i=$fn; $i<=$cn; $i++) {
            $files[] = sprintf("%04d-%02d", floor($i/12), $i%12+1);
        }

        return $files;
    }

}
