# taskr

[![Build Status](https://travis-ci.org/maxwellhealth/taskr.svg)](https://travis-ci.org/maxwellhealth/taskr)

taskr is a small PHP library that makes it easy to fork callbacks into child processes.

### Quick Start

Before running a task, we'll need to define a handler. The handler is what the task will use to things like writing a log or setting data about the task. In the example below, we've implemented HandlerInterface by extending HandlerAbstract with our own handler that will use a flat file for logging and MongoDB for task data.

```php
<?php

use Taskr\HandlerAbstract;
use Taskr\Task;

class MyHandler extends HandlerAbstract
{
    private $collection;
    private $logPath;

    public function __construct(\MongoCollection $collection)
    {
        $this->collection = $collection;
        $this->logPath = '/tmp/';
    }

    public function writeLog($message)
    {
        file_put_contents($this->logPath . $this->getTaskId(true) . '.log', $message . "\n", FILE_APPEND);
    }

    public function readLog($offset = 0)
    {
        $handle = fopen($this->logPath . $this->getTaskId(true) . '.log', 'r');

        $contents = '';

        fseek($handle, $offset);

        while (!feof($handle)) {
            $contents .= fgets($handle, 4096);
        }

        return $contents;
    }

    public function set($key, $value)
    {
        $this->collection->update([
            'taskId' => $this->getTaskId(true),
        ], [
            '$set' => ['taskId' => $this->getTaskId(true), $key => $value],
        ], ['upsert' => true]);
    }

    public function get($key = null, $default = null)
    {
        $fields = [];

        if (!is_null($key)) {
            $fields[$key] = true;
        }

        $doc = $this->collection->findOne(['taskId' => $this->getTaskId(true)], $fields);

        if (!$doc) {
            return $default;
        }

        if (is_null($key)) {
            return $doc;
        }

        if (!array_key_exists($key, $doc)) {
            return $default;
        }

        return $doc[$key];
    }

    public function setValues(array $values)
    {
        $values['taskId'] = $this->getTaskId(true);

        $this->collection->update([
            'taskId' => $this->getTaskId(true),
        ], [
            '$set' => $values,
        ], ['upsert' => true]);
    }
}
```

In the same file, let's create a new instance of our handler which we'll pass as the first argument to a new instance of Task. The second argument of our task object is a callable type which will be called by Task::run().

```php
$mongo = new \MongoClient();
$db = $mongo->taskr;
$collection = $db->tasks;

$handler = new MyHandler($collection);

$task = new Task($handler, function(MyHandler $handler) {
    $handler->writeLog('The task has started');
    sleep(2);
    $handler->complete();
});

$task->run();
```

Calling $task->run() will fork the current PHP process using [pcntl_fork()](http://php.net/manual/en/function.pcntl-fork.php) and run the task callable in a separate child process. In this example, the original process will end and the child process will live on writing to a log, sleeping for 2 seconds, and finally ending.
