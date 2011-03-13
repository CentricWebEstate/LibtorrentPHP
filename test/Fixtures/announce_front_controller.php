<?php

/**
 * Simple tracker server implementation for system test.
 * Being run with PHP's built-in web server .
 * @see SeedServerTest
 */

// No tolerance for errors - it's a test.
set_error_handler( function ( $errno, $errstr, $errfile = null, $errline = null )
{
    throw new Exception( "Error $errno: $errstr in $errfile:$errline" );
} );

require( __DIR__ . '/../../vendor/autoload.php' );
use PHPTracker\Persistence\SqlPersistence;
use PHPTracker\Core;

$persistence = new SqlPersistence(
    new PDO( 'sqlite:' . __DIR__ . '/sqlite_test.db' )
);

$core = new Core( $persistence );

echo $core->announce( $_GET, $_SERVER['REMOTE_ADDR'], 60 );
