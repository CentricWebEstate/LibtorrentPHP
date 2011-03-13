<?php

namespace PHPTracker;

/**
 * Lazy-loading class for the PHPTracker library.
 *
 * Uses PSR-0-ish autoloading for the PHPTracker namespace.
 *
 * @package PHPTracker
 */
class Autoloader
{
    /**
     * Registers PHPTracker\Autoloader as an SPL autoloader.
     *
     * Should be called before starting to use the library.
     */
    static public function register()
    {
        // We autoload while unserializing an unknown object too.
        ini_set( 'unserialize_callback_func', 'spl_autoload_call' );
        spl_autoload_register( array( new self, 'autoload' ) );
    }

    /**
     * Handles autoloading of classes.
     *
     * Only loads classes from the 'PHPTracker' namespace.
     *
     * @param string $class A class name inside the PHPTracker package.
     */
    static public function autoload( $class )
    {
        if ( 0 !== strpos( $class, 'PHPTracker' ) )
        {
            return;
        }

        $file = __DIR__ . '/../' . str_replace( array( '\\' ), '/', $class ) . '.php';

        if ( file_exists( $file ) )
        {
            require $file;
        }
    }
}
