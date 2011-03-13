<?php

namespace PHPTracker\Logger;

/**
 * Interface used to log events in different classes of the library.
 *
 * Feel free to implement your own logger with PHPTracker\Logger\LoggerInterface.
 *
 * @package PHPTracker
 * @subpackage Logger
 */
interface LoggerInterface
{
    /**
     * Method to save non-error text message.
     *
     * @param string $message
     */
    public function logMessage( $message );

    /**
     * Method to save text message represening error.
     *
     * @param string $message
     */
    public function logError( $message );
}
