<?php

/**
 * This is an example of a task "garbage collector" since taskr does not have
 * something similar built in.
 */

set_time_limit(0);

$config = [
    /**
     * In seconds, the maximum running time for tasks.
     */
    'maxRunningTime' => 3 * 60 * 60,

    /**
     * Tasks with the key "toBeKilled" set to true will be killed.
     */
    'killedKey' => 'toBeKilled',
];

$collection = $di['mongo']->taskr;

/**
 * Make sure we only find processes running on this host.
 */
$cursor = $collection->find([
    'host' => trim(shell_exec('hostname')),
    '$or' => [
        ['timestampStart' => ['$lte' => time() - $config['maxRunningTime']]],
        ['toBeKilled' => true],
    ],
]);

while ($cursor->hasNext()) {
    $doc = $cursor->getNext();

    /**
     * Kill the garbage process.
     */
    posix_kill($doc['pid'], SIGKILL);

    $duration = time() - $doc['timestampStart'];

    $collection->update([
        '_id' => $doc['_id'],
    ], [
        '$set' => [
            'timestampComplete' => time(),
            'duration' => $duration,
            'status' => 'error',
            'host' => null,
            'pid' => null,
        ],
    ]);
}
