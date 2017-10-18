<?php
/**
 * hiAPI RCP Traf Collector
 *
 * @link      https://github.com/hiqdev/hiapi-rcp-traf
 * @package   hiapi-rcp-traf
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017, HiQDev (http://hiqdev.com/)
 */

namespace hiapi\rcptraf\utils;

class Ssh
{
    public $user = 'root';

    public $ports = [222, 22];

    public $options = '-o BatchMode=yes -o LogLevel=error -o ConnectTimeout=29 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o VisualHostKey=no';

    public $testTimeout = 3;

    protected $ip;

    protected $port;

    public function __construct($ip)
    {
        $this->ip = $ip;
    }

    public function canConnect()
    {
        return $this->getPort() > 0;
    }

    public function getPort()
    {
        if (null === $this->port) {
            $this->port = $this->detectPort();
        }

        return $this->port;
    }

    protected function detectPort()
    {
        foreach ($this->ports as $port) {
            if ($socket = @fsockopen($this->ip, $port, $errno, $errstr, $this->testTimeout)) {
                fclose($socket);

                return $port;
            }
        }

        return false;
    }

    public function run($remoteCommand, $more = '')
    {
        if (!$this->canConnect()) {
            return;
        }
        $quoted = escapeshellarg($remoteCommand);
        $command = "ssh {$this->options} -p{$this->port} {$this->user}@{$this->ip} $quoted $more";
        exec($command);
    }

    public function get($remoteSrc, $dst)
    {
        if (!$this->canConnect()) {
            return;
        }
    }

    public function put($src, $remoteDst)
    {
        if (!$this->canConnect() || !$src || !$remoteDst) {
            return;
        }
        $src = escapeshellarg($src);
        $command = "scp {$this->options} -P{$this->port} $src {$this->user}@{$this->ip}:$remoteDst";
        exec($command);
    }
}
