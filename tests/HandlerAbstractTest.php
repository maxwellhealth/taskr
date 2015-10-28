<?php

use Taskr\HandlerAbstract;
use Taskr\TaskId;

class HandlerAbstractTest extends PHPUnit_Framework_TestCase
{
    private $handler;

    public function setUp()
    {
        $this->handler = new HandlerAbstractTestHandler();
    }

    public function testSetProcessId()
    {
        $taskId = new TaskId();

        $this->handler->setTaskId($taskId);
        $this->assertSame($this->handler->getTaskId(), $taskId);
    }

    public function testAfterFork()
    {
        $this->handler->afterFork();
        $taskData = $this->handler->get();

        $this->assertSame(array_keys($taskData), ['timestampStart', 'status', 'percent', 'host', 'pid', 'callableName']);

        $this->assertSame($taskData['status'], 'running');
        $this->assertSame($taskData['percent'], 0);
        $this->assertSame($taskData['host'], trim(shell_exec('hostname')));
        $this->assertSame($taskData['pid'], posix_getpid());
    }

    public function testSetStatus()
    {
        $this->handler->setStatus('test');
        $this->assertSame($this->handler->getStatus(), 'test');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetStatusNonString()
    {
        $this->handler->setStatus(1);
    }

    public function testTestPercent()
    {
        $this->handler->setPercent(50);
        $this->assertSame($this->handler->getPercent(), 50);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetPercentNonInt()
    {
        $this->handler->setPercent('test');
    }

    public function testComplete()
    {
        $this->handler->complete();
        $taskData = $this->handler->get();

        $this->assertSame(array_keys($taskData), ['timestampComplete', 'duration', 'host', 'pid', 'status', 'percent']);

        $this->assertSame($taskData['status'], 'complete');
        $this->assertSame($taskData['percent'], 100);
        $this->assertSame($taskData['host'], null);
        $this->assertSame($taskData['pid'], null);
    }

    public function testError()
    {
        $this->handler->setPercent(75);
        $this->handler->error();
        $taskData = $this->handler->get();

        $this->assertSame(array_keys($taskData), ['percent', 'timestampComplete', 'duration', 'host', 'pid', 'status']);

        $this->assertSame($taskData['status'], 'error');
        $this->assertSame($taskData['percent'], 75);
        $this->assertSame($taskData['host'], null);
        $this->assertSame($taskData['pid'], null);
    }
}

class HandlerAbstractTestHandler extends HandlerAbstract
{
    private $data = [];

    public function writeLog($message)
    {
    }

    public function readLog($offset = 0)
    {
    }

    public function set($key, $value)
    {
        $taskId = $this->getTaskId(true);

        if (!array_key_exists($taskId, $this->data)) {
            $this->data[$taskId] = [];
        }

        $this->data[$taskId][$key] = $value;
    }

    public function get($key = null, $default = null)
    {
        $taskId = $this->getTaskId(true);

        if (!array_key_exists($taskId, $this->data)) {
            return $default;
        }

        if (is_null($key)) {
            return $this->data[$taskId];
        }

        if (!array_key_exists($key, $this->data[$taskId])) {
            return $default;
        }

        return $this->data[$taskId][$key];
    }

    public function setValues(array $values)
    {
        $taskId = $this->getTaskId(true);

        if (!array_key_exists($taskId, $this->data)) {
            $this->data[$taskId] = [];
        }

        foreach ($values as $key => $value) {
            $this->data[$taskId][$key] = $value;
        }
    }
}
