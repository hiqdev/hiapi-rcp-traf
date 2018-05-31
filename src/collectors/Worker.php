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
use hiapi\rcptraf\utils\Ssh;

class Worker
{
    public $tmpDir = '/tmp';

    protected $collector;
    protected $parser;
    protected $ip;
    protected $objects;

    public function __construct(AbstractCollector $collector, FileParser $parser, $ip, $objects)
    {
        $this->collector = $collector;
        $this->parser = $parser;
        $this->ip = $ip;
        $this->objects = $objects;
    }

    public function collect()
    {
        $this->ssh = new Ssh($this->ip);
        if (!$this->ssh->canConnect()) {
            return false;
        }

        $this->putConfig();

        return $this->saveData();
    }

    protected function saveData()
    {
        $path = $this->copyData();
        if (!$path) {
            return false;
        }
        $this->parser->parse($path);
        unlink($path);

        foreach ($this->objects as $object => $row) {
            $this->saveElement($row, $this->parser->getValues($object));
        }

        return true;
    }

    protected function saveElement($row, $values)
    {
        if (!$values) {
            return;
        }
        $min = $this->collector->getMinTime($row['last_time'])->getTimestamp();
        $max = $this->collector->getMaxTime()->getTimestamp();
        $uses = [];
        foreach ($values as $date => $fields) {
            if (!preg_match('/[0-9]{4}\-[0-9]{1,2}\-[0-9]{1,2}/', $date, $matches)) {
                continue;
            }

            $date = $matches[0];
            $cur = strtotime($date);
            if ($cur > $max || $cur < $min) {
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
        $this->collector->usesSet($uses);
    }

    protected function putConfig()
    {
        $config = $this->collector->buildConfig($this->ip);
        if (null === $config) {
            return null;
        }

        $src = $this->getTmpFile('config', $config);
        $this->ssh->put($src, $this->collector->getConfigPath());
        unlink($src);

        return true;
    }

    protected function getTmpFile($postfix = '', $content = '')
    {
        $parts = array_filter([$this->collector->getType(), getmypid(), $this->ip, $postfix]);
        $dest = $this->tmpDir . '/' . implode('.', $parts);
        if (false === file_put_contents($dest, $content)) {
            throw new \Exception("failed write tmp file $dest");
        }

        return $dest;
    }

    protected function copyData()
    {
        $dest = $this->getTmpFile('data');
        $files = implode(' ', $this->collector->getFiles());
        $this->ssh->run("/bin/cat $files", "> $dest 2> /dev/null");

        return $dest;
    }
}
