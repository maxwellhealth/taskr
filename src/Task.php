<?php

namespace Taskr;

class Task
{
    private $handler;
    private $task;
    private $args;
    private $sid;
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
        //
        $pid = pcntl_fork();

        if ($pid === -1) {
            // @TODO add more context to this exception. Perhaps the task name and some other arguments about that task
            throw new \RuntimeException('Unable to fork process');
        }

        if ($pid) {
            /**
             * Parent
             */
            if (php_sapi_name() !== 'cli') {
                register_shutdown_function(function () use ($pid) {
                    /**
                     * If fastcgi_finish_request() exists, call it to flush
                     * all data to the client and finish the request.
                     */
                    if (function_exists('fastcgi_finish_request')) {
                        fastcgi_finish_request();
                    }

                    pcntl_waitpid($pid, $status, WNOHANG);

                    $this->kill();
                });
            }
        } else {
            /**
             * Child
             * We're detaching the child process from the parent so that if the main fpm restarts or is killed our process can continue. 
             */
            
            $sid = posix_setsid();
            
            if ($sid < 0) {
                // if we're not able to detach from the parent then we should exit
                exit();
            }
            $this->setSid($sid);
            register_shutdown_function(function () {
                $error = error_get_last();
                // @TODO why are we only logging this set of exceptions? do we ever not have non 1/256/4096 error codes
                if ($error && ($error['type'] === 1 || $error['type'] === 256 || $error['type'] === 4096)) {
                    $this->handler->writeLog('Error: (' . $error['type'] . ') ' . $error['message'] . ' on line ' . $error['line'] . ' in file ' . $error['file']);
                    $this->handler->error();
                }

                $this->kill();
            });

            $this->handler->afterFork();

            /**
             * Grab the callable task from $this->task.
             */
            $task = $this->task;

            if (is_string($task)) {
                $this->handler->set('callableName', $task);
            } else if (is_array($task)) {
                $class = $task[0];
                $method = $task[1];

                if (is_object($class)) {
                    $class = get_class($class);
                }

                $this->handler->set('callableName', $class . '::' . $method);
            }

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
            // @TODO we probably do care about the output. we should have a way to log it. 
            ob_end_clean();

            /**
             * Kill the child.
             */
            $this->kill();
        }
        // @codeCoverageIgnoreEnd
    }
    private function setSID($sid) 
    {
        $this->sid = $sid;
    }

    public function getSID() 
    {
        return $this->sid;
    }
    // @codeCoverageIgnoreStart
    private function kill()
    {
        posix_kill(posix_getpid(), SIGTERM);
    }
    // @codeCoverageIgnoreEnd
}
