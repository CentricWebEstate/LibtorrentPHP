<?php

namespace PHPTracker\Persistence;

/**
 * When a persistence object is used in a forked process and implements this
 * interface, resetAfterForking will be called after the fork to, for example,
 * re-establish database connections.
 *
 * This is necessary in case of persistence implementations relying on
 * sockets because forking might mess up listeners.
 *
 * @package PHPTracker
 * @subpackage Persistence
 */
interface ResetWhenForking
{
    /**
     * To be called after the child-process is forked.
     */
    public function resetAfterForking();
}
