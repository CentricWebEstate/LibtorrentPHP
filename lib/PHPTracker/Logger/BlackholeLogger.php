<?php

namespace PHPTracker\Logger;

/**
 * Logger class to serve as Null object of logging - no data is saved.
 *
 * @package PHPTracker
 * @subpackage Logger
 */
class BlackholeLogger implements LoggerInterface
{
    /**
     * Implementing message logging, doing nothing.
     *
     * @param type $message
     */
    public function logMessage( $message )
    {
    }

    /**
     * Implementing message logging, doing nothing.
     *
     * @param type $message
     */
    public function logError( $message )
    {
    }
}
