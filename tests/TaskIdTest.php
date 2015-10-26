<?php

use Taskr\TaskId;

class TaskIdTest extends PHPUnit_Framework_TestCase
{
    public function testToString()
    {
        $id = '123';

        $taskId = new TaskId($id);
        $this->assertEquals($id, (string) $taskId);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testErrorThrownWhenNotString()
    {
        $processId = new TaskId(123);
    }

    public function testNullIsOK()
    {
        $taskId = new TaskId();
    }
}
