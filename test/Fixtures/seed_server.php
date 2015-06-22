<?php

/**
 * Simple seed server implementation for system test.
 * @see SeedServerTest
 */

// No tolerance for errors - it's a test.
set_error_handler( function ( $errno, $errstr, $errfile = null, $errline = null )
{
    throw new Exception( "Error $errno: $errstr in $errfile:$errline" );
} );

require( __DIR__ . '/../../vendor/autoload.php' );

use CWE\Libraries\LibtorrentPHP\Persistence\SqlPersistence;
use CWE\Libraries\LibtorrentPHP\Logger\StdErrLogger;
use CWE\Libraries\LibtorrentPHP\Seeder\Peer;
use CWE\Libraries\LibtorrentPHP\Seeder\Server;

$ip     = $argv[1];
$port   = $argv[2];

fwrite( STDERR, "Starting seed server at $ip:$port" );

$persistence = new SqlPersistence(
    new PDO( 'sqlite:' . __DIR__ . '/sqlite_test.db' )
);

$peer = new Peer( $persistence );
$peer
    ->setExternalAddress( $ip )
    ->setInternalAddress( $ip )
    ->setPort( $port )
    ->setPeerForks( 5 )
    ->setSeedersStopSeeding( 5 )
    ->setLogger( new StdErrLogger() )
;

$server = new Server( $peer, $persistence );
$server
    ->setLogger( new StdErrLogger() )
;

$server->start();
