<?php

namespace PHPTracker\Test\Whitebox\Logger;

use PHPTracker\Logger\FileLogger;

/**
 * Test class for PHPTracker\Logger\FileLogger.
 */
class FileLoggerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PHPTracker\Logger\FileLogger
     */
    private $object;
    private $log_path;

    public function setUp()
    {
        $this->log_path    = sys_get_temp_dir() . '/test_' . md5( uniqid() );
        $this->object      = new FileLogger( $this->log_path );
    }

    public function tearDown()
    {
        // Delete the test file.
        if ( file_exists( $this->log_path ) )
        {
            unlink( $this->log_path );
        }
    }

    public function testLogMessage()
    {
        $this->object->logMessage( "I'm a message with a tricky\nnew line." );
        $log_file_contents = file_get_contents( $this->log_path );

        $this->assertLogFormat( $log_file_contents );
        // Checking that message is entirely saved and new lines are escaped.
        $this->assertContains( "I'm a message with a tricky\\nnew line.", $log_file_contents );
    }

    private function assertLogFormat( $log_message )
    {
        // If we have a timestamp.
        $this->assertRegexp( '/
            ^\[         # Opening square bracket for timestamp, in the beginning.
            \d{4}       # Year.
            \-          # Dash after year.
            \d{2}       # Month.
            \-          # Dash after month.
            \d{2}       # Day.
            \x20        # Space after date.
            [0-2]?\d    # Hour.
            \:          # Colon after hour.
            [0-5]?\d    # Minute.
            \:          # Colon after hour.
            [0-5]?\d    # Seconds.
            \]          # Closing bracket.
        /x', $log_message );

        // Should end with new line.
        $this->assertRegexp( '/\n$/', $log_message );
    }
}
