<?php

namespace PHPTracker\Test\Whitebox;

use PHPTracker\Autoloader;

class AutoloaderTest extends \PHPUnit_Framework_TestCase
{
    public function testAutoload()
    {
        $this->assertFalse( class_exists('FooBarFoo'), '->autoload() does not try to load classes that does not begin with PHPTracker' );

        $autoloader = new Autoloader();
        $this->assertNull( $autoloader->autoload('Foo'), '->autoload() returns false if it is not able to load a class' );
    }
}
