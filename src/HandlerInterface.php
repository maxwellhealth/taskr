<?php

namespace Taskr;

interface HandlerInterface
{
    public function setTaskId(TaskId $id);
    public function getTaskId($toString = false);

    public function afterFork();

    public function setStatus($status);
    public function getStatus();

    public function setPercent($percent);
    public function getPercent();

    public function complete();
    public function error();

    public function writeLog($message);
    public function readLog($offset = 0);

    public function set($key, $value);
    public function get($key = null, $default = null);

    public function setValues(array $values);
}
