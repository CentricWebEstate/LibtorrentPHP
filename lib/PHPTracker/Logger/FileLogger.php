<?php

namespace PHPTracker\Logger;

/**
 * Logger class appending messages to a file or files.
 *
 * @package PHPTracker
 * @subpackage Logger
 */
class FileLogger implements LoggerInterface
{
    /**
     * Path of the log file for messages.
     *
     * @var string
     */
    private $log_file_path;

    /**
     * Default log file path. If not specified, the same is used for messages and errors.
     */
    const DEFAULT_LOG_PATH = '/var/log/phptracker.log';

    /**
     * Initializes the object with the config class.
     *
     * File logging can use 'file_path_messages' and file_path_errors params,
     * or logs to self::DEFAULT_LOG_PATH by default (both errors and messages).
     *
     * @param string $log_file_path Path to write log file to.
     */
    public function  __construct( $log_file_path = self::DEFAULT_LOG_PATH )
    {
        $this->log_file_path = $log_file_path;
    }

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
     * Writes message to the log file.
     *
     * @param string $message Log message to write.
     * @param boolean $error If true, we are using the error log, if not, the normal.
     */
    private function write( $message, $error = false )
    {
        file_put_contents( $this->log_file_path, $this->formatMessage( $message, $error ), FILE_APPEND );
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
