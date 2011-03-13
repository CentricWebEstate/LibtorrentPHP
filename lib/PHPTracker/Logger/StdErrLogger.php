<?php

namespace PHPTracker\Logger;

/**
 * Logger class appending messages to a file or files.
 *
 * @package PHPTracker
 * @subpackage Logger
 */
class StdErrLogger implements LoggerInterface
{
    /**
     * Method to save non-error text message.
     *
     * @param string $message
     */
    public function logMessage( $message )
    {
        $this->write( $message );
    }

    /**
     * Method to save text message represening error.
     *
     * @param string $message
     */
    public function logError( $message )
    {
        $this->write( $message, true );
    }

    /**
     * Writing the message to sdterr.
     *
     * @param string $message Log message to write.
     * @param boolean $error If true, we are using the error log, if not, the normal.
     */
    private function write( $message, $error = false )
    {
        fwrite( STDERR, $this->formatMessage( $message, $error ) );
    }

    /**
     * Formats log message adding timestamp and EOL, escaping new lines.
     *
     * @param string $message Log message to format.
     * @param boolean $error If true, [ERROR] prefix is added.
     * @return string
     */
    private function formatMessage( $message, $error )
    {
        return date( "[Y-m-d H:i:s] " ) . ( $error ? '[ERROR] ' : '' ) . addcslashes( $message, "\n\r" ) . PHP_EOL;
    }
}
