<?php

namespace Taskr;

abstract class HandlerAbstract implements HandlerInterface
{
    private $taskId;

    public function setTaskId(TaskId $taskId)
    {
        $this->taskId = $taskId;
    }

    public function getTaskId($toString = false)
    {
        if ($toString) {
            return (string) $this->taskId;
        }

        return $this->taskId;
    }

    public function afterFork()
    {
        $this->setValues([
            'timestampStart' => time(),
            'status' => 'running',
            'percent' => 0,
            'host' => trim(shell_exec('hostname')),
            'pid' => posix_getpid(),
            'sid' => posix_getsid(posix_getpid()),
        ]);
    }

    public function setStatus($status)
    {
        if (!is_string($status)) {
            throw new \InvalidArgumentException('$status must be a string');
        }

        $this->set('status', $status);
    }

    public function getStatus()
    {
        return $this->get('status');
    }

    public function setPercent($percent)
    {
        if (!is_int($percent)) {
            throw new \InvalidArgumentException('$percent must be an integer');
        }

        $this->set('percent', $percent);
    }

    public function getPercent()
    {
        return $this->get('percent');
    }

    private function endTask()
    {
        $timestampStart = $this->get('timestampStart');
        $timestampComplete = time();
        $duration = $timestampComplete - $timestampStart;

        $this->setValues([
            'timestampComplete' => $timestampComplete,
            'duration' => $duration,
            'host' => null,
            'pid' => null
        ]);
    }

    public function complete()
    {
        $this->endTask();

        $this->setValues([
            'status' => 'complete',
            'percent' => 100
        ]);

        $this->writeLog('Task finished');
    }

    public function error()
    {
        $this->endTask();
        $this->set('status', 'error');
    }
}
