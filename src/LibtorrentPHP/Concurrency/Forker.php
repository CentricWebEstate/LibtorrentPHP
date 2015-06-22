<?php

declare( ticks = 1 );

namespace CWE\Libraries\LibtorrentPHP\Concurrency;

/**
 * Class to fork its process to N childprocesses executing the same code.
 *
 * Ideal for maintaining one listening socket and accept connections in multiple
 * processes.
 *
 * @package PHPTracker
 * @subpackage Concurrency
 */
class Forker
{
    /**
     * Array of active child processes' PIDs. Keys represent "slot" indexes.
     *
     * @var array
     */
    private $children = array();

    /**
     * Object which contains the code to be executed after forking.
     * Also defines how to do the forking.
     *
     * @see ConcurrentInterface
     * @var ConcurrentInterface
     */
    private $concurrent_object;

    /**
     * Constructs forker with the "forkable" object implmeneting ConcurrentInterface.
     *
     * @param ConcurrentInterface $concurrent_object Object to be "forked", that
     *                            is, execute its code in parallel processes.
     */
    public function __construct( ConcurrentInterface $concurrent_object )
    {
        $this->concurrent_object = $concurrent_object;
    }

    /**
     * Executing setup method of the inheriting class, then fork child processes.
     *
     * The number of children forked is a number returned by the constructorParentProcess
     * method of the inheriting class. If it's negative, processea re automatically recreated.
     * The method passes all its parameters to the setup method of the inheriting
     * class.
     */
    public function fork()
    {
        $this->installSignalHandler();

        $n_forks    = $this->concurrent_object->getNumberOfForks();
        $is_guarded = $this->concurrent_object->isGuarded();

        if ( 0 >= $n_forks ) return;

        do
        {
            for ( $slot = 0; $slot < $n_forks; ++$slot )
            {
                if ( isset( $this->children[$slot] ) )
                {
                    // Process already running in this slot.
                    continue;
                }

                $pid = $this->forkProcess();

                if ( $pid )
                {
                    $this->children[$slot] = $pid;
                }
                else
                {
                    return $this->concurrent_object->afterFork( $slot );
                }
            }

            while ( !$is_guarded && pcntl_wait( $status ) )
            {
                // If we don't need to recreate child processes on exit
                // we just wait for them to die to aviod zombies.
            }

            $pid_exit = pcntl_wait( $status );

            echo "Process exited: $pid_exit\n";

            if ( false !== ( $slot = array_search( $pid_exit, $this->children ) ) )
            {
                unset( $this->children[$slot] );
            }
        } while ( true );
    }

    /**
     * Makes sure we don't leave any zombies behind.
     * We don't like the zombie kind around here.
     */
    private function installSignalHandler()
    {
        $self = $this;
        pcntl_signal( SIGTERM, function( $signal ) use ( $self )
        {
            foreach ( $self->getChildProcesses() as $child_process )
            {
                posix_kill( $child_process, $signal );
            }
        } );
    }

    /**
     * Returns an array of the active child processes.
     *
     * @return array With slot id as key and PID as value.
     */
    public function getChildProcesses()
    {
        return $this->children;
    }

    /**
     * Forks the currently running process.
     *
     * @throws Error if the forking is unsuccessful.
     * @return integer Forked process ID or 0 if you are in the child already.
     */
    private function forkProcess()
    {
        $pid = pcntl_fork();

        if( -1 == $pid )
        {
            throw new Error( 'Unable to fork.' );
        }

        return $pid;
    }
}
