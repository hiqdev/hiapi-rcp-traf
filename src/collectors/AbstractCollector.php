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

    public $sshPorts = [222, 22];

    public $sshOptions = '-o ConnectTimeout=29 -o BatchMode=yes -o StrictHostKeyChecking=no -o VisualHostKey=no';

    public $configPath = "/usr/local/rcp/etc";

    public $configName = null;

    public $configFormat = "";

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

    protected function findConfigs($group)
    {
        return null;
    }

    protected function createConfig($rows = false)
    {
        if ($rows === false) {
            return false;
        }

        foreach ($rows as $row) {
            $config[] = vsprintf($this->configFormat, $row);
        }

        return implode("\n", $config);
    }

    public function collect(array $group)
    {
        $port = $this->detectSshPort($group['device_ip']);
        if (!$port) {
            return ;
        }

        $path = $this->saveConfig($group, $port)->copyData($group, $port);
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

    protected function getTmpFileName($ip, $add = '')
    {
        $dest = '/tmp/' . $this->type . '.' . getmypid() . '.' . $ip . ($add ? ".{$add}" : '');
        if (false === file_put_contents($dest, '')) {
            throw new \Exception("failed copy traf data to $dest");
        }

        return $dest;
    }

    protected function copyFile($src, $dest, $port)
    {
        // FOR PRODUCTION USE UNCOMENT NEXT LINE
//        exec("/usr/bin/scp {$this->sshOptions} -P{$port} $src $dest", $output, $res);
        return $res == 0;
    }

    protected function saveConfig($group, $port = 222)
    {
        if ($this->configPath === null || $this->configName === null) {
            return $this;
        }

        $config = $this->createConfig($this->findConfigs($group));
        if ($config === false) {
            return $this;
        }

        $src = $this->getTmpFileName($group['device_ip'], $this->configName);
        if (false === file_put_contents($src, $config)) {
            throw new \Exception("failed copy traf config to $src");
        }

        $dst = "root@{$group['device_ip']}:{$this->configPath}/{$this->configName}";
        if ($this->copyFile($src, $dst, $port)=== false){
            throw new \Exception("failed copy traf config to $dst");
        }

        unlink($src);
        return $this;
    }

    protected function copyData($group, $port = 222)
    {
        $ip = $group['device_ip'];
        $dest = $this->getTmpFileName($ip);
        $files = implode(" ", $this->getFiles());
        $command = "ssh {$this->sshOptions} -p{$port} root@$ip '/bin/cat $files' > $dest 2>/dev/null";
        exec($command, $output, $result);

        return $dest;
    }

    protected function detectSshPort($ip)
    {
        foreach ($this->sshPorts as $port) {
            if ($socket = @fsockopen($ip, $port, $errno, $errstr, 3)) {
                fclose($socket);

                return $port;
            }
        }

        return false;
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
