<?php

declare( ticks = 1 );

namespace PHPTracker\Concurrency;

/**
 *
 *
 * @package PHPTracker
 * @subpackage Concurrency
 */
class Detacher
{
    /**
     * Detaches process from console.
     *
     * Requires php-posix!
     *
     * @see self::start()
     */
    public function detach()
    {
        // Forking one child process and closing the parent,
        // because if the parent is already a session leader, it cannot leave it.
        // It is because session group an dprocess group has the same ID as their
        // leader process. Now if you assign the leader process to another
        // session/process group, the IDs will collide.
        $pid = $this->forkProcess();
        if ( $pid > 0 )
        {
             // We are in the parent, so we terminate.
            exit( 0 );
        }

        // Becoming leader of a new session/process group - detaching from shell.
        $sid = posix_setsid();
        if ( false === $sid )
        {
            throw new Error( 'Unable to become session leader (detaching).' );
        }

        // We have to handle hangup signals (send when session leader terminates),
        // otherwise it makes child process to stop.
        pcntl_signal( SIGHUP, SIG_IGN );

        // Forking again for not being session/process group leaders will disallow the process
        // to "accidentally" open a controlling terminal for itself (System V OSs).
        $pid = $this->forkProcess();
        if ( $pid > 0 )
        {
             // We are in the parent, so we terminate.
            exit( 0 );
        }

        // Releasing current directory and closing open file descriptors (standard IO).
        chdir( '/' );
        fclose( STDIN );
        fclose( STDOUT );

        // PHP still thinks we are a webpage, and since we closed standard output,
        // whenever we echo, it will assume that the client abandoned the connection,
        // so it silently stops running.
        // We can tell it here not to do it.
        ignore_user_abort( true );
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