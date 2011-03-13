<?php

namespace PHPTracker\Concurrency;

/**
 * Interface to be implemented by classes who want their code to
 * run as multiple forks of the original process.
 *
 * A good example if a server application running several processes
 * accepting incoming connections.
 *
 *
 * @package PHPTracker
 * @subpackage Concurrency
 */
interface ConcurrentInterface
{
    /**
     * Returns the number of the desired child processes to be forked.
     *
     * @return integer
     */
    public function getNumberOfForks();

    /**
     * Tells if processes are guarded, that is, should be restarted
     * after they fail.
     *
     * In case of a server application it makes sense to restart failing forks.
     *
     * @return boolean
     */
    public function isGuarded();

    /**
     * Method to be called after the fork is done.
     *
     * If your class has guarded forking, you should implement blocking
     * code here.
     *
     * @param integer $slot 0-based index of the created child depending
     *                      on the number specified at getNumberOfForks.
     */
    public function afterFork( $slot );
}
