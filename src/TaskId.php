<?php

namespace Taskr;

class TaskId
{
    private $id;

    public function __construct($id = null)
    {
        if (!is_null($id) && !is_string($id)) {
            throw new \InvalidArgumentException('$id must be a string');
        }

        if (is_null($id)) {
            $id = uniqid();
        }

        $this->id = $id;
    }

    public function __toString()
    {
        return $this->id;
    }
}
