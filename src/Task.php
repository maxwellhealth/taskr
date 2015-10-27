<?php

namespace Taskr;

class Task
{
    private $handler;
    private $task;
    private $args;

    private $started = false;

    public function __construct(HandlerInterface $handler, callable $task, array $args = [])
    {
        if ($handler->getTaskId()) {
            throw new \InvalidArgumentException('$handler already has a TaskId set');
        }

        array_unshift($args, $handler);

        $this->handler = $handler;
        $this->task = $task;
        $this->args = $args;
    }

    public function run()
    {
        if ($this->started) {
            throw new \RuntimeException('This task has already been started');
        }

        $this->started = true;

        while (true) {
            $taskId = new TaskId();
            $this->handler->setTaskId($taskId);

            $taskData = $this->handler->get();

            if (is_null($taskData)) {
                break;
            }
        } // @codeCoverageIgnore

        // @codeCoverageIgnoreStart
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new \RuntimeException('Unable to fork process');
        }

        /**
         * If $pid is 0, we're inside the child.
         */

        if ($pid === 0) {
            register_shutdown_function(function () {
                $error = error_get_last();

                if ($error && ($error['type'] === 1 || $error['type'] === 256)) {
                    $this->handler->error();
                }

                $this->kill();
            });

            $this->handler->afterFork();

            /**
             * Grab the callable task from $this->task.
             */
            $task = $this->task;

            /**
             * Start capturing all output from the task.
             */
            ob_start();

            /**
             * Call the task callable.
             */
            call_user_func_array($this->task, $this->args);

            /**
             * We don't care about the output.
             */
            ob_end_clean();

            /**
             * Kill the child.
             */
            $this->kill();
        }
        // @codeCoverageIgnoreEnd
    }

    // @codeCoverageIgnoreStart
    private function kill()
    {
        posix_kill(posix_getpid(), SIGINT);
    }
    // @codeCoverageIgnoreEnd
}
