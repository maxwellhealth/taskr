<?php

use Taskr\HandlerAbstract;
use Taskr\HandlerInterface;
use Taskr\Task;
use Taskr\TaskId;

class TaskTest extends PHPUnit_Framework_TestCase
{
    public function testRun()
    {
        $handler = new TaskTestHandler();

        $task = new Task($handler, function (HandlerInterface $handler) {
            sleep(1);
            $handler->writeLog('testRun');
            $handler->complete();
        });

        $task->run();

        usleep(200 * 1000);

        $pid = $handler->get('pid');
        $cmd = sprintf('kill -0 %d 2>&1', $pid);
        $this->assertSame(shell_exec($cmd), null);

        sleep(1);

        $this->assertSame($handler->readLog(), "testRun\nTask finished\n");
        $this->assertSame($handler->getStatus(), 'complete');

        pcntl_waitpid($pid, $status);

        $this->assertTrue(strlen(shell_exec($cmd)) > 0);

        $handler->cleanup();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testTaskIdAlreadySet()
    {
        $handler = new TaskTestHandler();
        $handler->setTaskId(new TaskId());
        $handler->cleanup();

        $task = new Task($handler, function (HandlerInterface $handler) {
        });
    }

    /**
     * @expectedException RuntimeException
     */
    public function testStarted()
    {
        $handler = new TaskTestHandler();

        $task = new Task($handler, function (HandlerInterface $handler) {
        });

        $task->run();

        usleep(200 * 1000);

        $handler->cleanup();
        $task->run();
    }
}

class TaskTestHandler extends HandlerAbstract
{
    private $dataPath;
    private $logPath;

    public function __construct()
    {
        $this->dataPath = dirname(__FILE__) . '/test-data-' . uniqid();
        $this->logPath = dirname(__FILE__) . '/test-log-' . uniqid();

        touch($this->dataPath);
        touch($this->logPath);
    }

    public function cleanup()
    {
        unlink($this->dataPath);
        unlink($this->logPath);
    }

    public function writeLog($message)
    {
        file_put_contents($this->logPath, $message . "\n", FILE_APPEND);
    }

    public function readLog($offset = 0)
    {
        $handle = fopen($this->logPath, 'r');

        $contents = '';

        fseek($handle, $offset);

        while (!feof($handle)) {
            $contents .= fgets($handle, 4096);
        }

        return $contents;
    }

    private function writeDataFile(array $data)
    {
        $data = serialize($data);
        file_put_contents($this->dataPath, $data);
    }

    private function readDataFile()
    {
        $data = file_get_contents($this->dataPath);
        $data = unserialize($data);

        if ($data === false) {
            return [];
        }

        return $data;
    }

    public function set($key, $value)
    {
        $taskId = $this->getTaskId(true);

        $data = $this->readDataFile();

        if (!array_key_exists($taskId, $data)) {
            $data[$taskId] = [];
        }

        $data[$taskId][$key] = $value;

        $this->writeDataFile($data);
    }

    public function get($key = null, $default = null)
    {
        $taskId = $this->getTaskId(true);

        $data = $this->readDataFile();

        if (!array_key_exists($taskId, $data)) {
            return $default;
        }

        if (is_null($key)) {
            return $data[$taskId];
        }

        if (!array_key_exists($key, $data[$taskId])) {
            return $default;
        }

        $this->writeDataFile($data);

        return $data[$taskId][$key];
    }

    public function setValues(array $values)
    {
        $taskId = $this->getTaskId(true);

        $data = $this->readDataFile();

        if (!array_key_exists($taskId, $data)) {
            $data[$taskId] = [];
        }

        foreach ($values as $key => $value) {
            $data[$taskId][$key] = $value;
        }

        $this->writeDataFile($data);
    }
}
