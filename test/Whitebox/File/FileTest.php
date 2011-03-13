<?php

namespace PHPTracker\Test\Whitebox\File;

use PHPTracker\File\File;

class FileTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PHPTracker\File\File
     */
    private $object;

    private $original_path;

    const TEST_DATA = 'abcdefghijklmnopqrstuvwxyz';

    public function setUp()
    {
        $this->original_path = sys_get_temp_dir() . '/test_' . md5( uniqid() );
        file_put_contents( $this->original_path, self::TEST_DATA );

        $this->object = new File( $this->original_path );
    }

    public function tearDown()
    {
        // We have to desctory the object to close open handles.
        unset( $this->object );
        // Then we can delete the test file.
        if ( file_exists( $this->original_path ) )
        {
            unlink( $this->original_path );
        }
    }

    public function testToString()
    {
        $this->assertEquals( realpath($this->original_path), $this->object . '' );
    }

    public function testSize()
    {
        $this->assertEquals( strlen( self::TEST_DATA ), $this->object->size() );
    }

    /**
     * @expectedException PHPTracker\File\Error\NotExistsError
     */
    public function testNonExistent()
    {
        $non_existent = new File( '/no_way_this_exists' );
    }

    public function testGetHashesForPieces()
    {
        // Generating test hash for 1 byte length pieces.
        $expected_hash = '';
        for ( $i = 0; $i < strlen( self::TEST_DATA ); ++$i )
        {
            $expected_hash .= sha1( substr( self::TEST_DATA, $i, 1 ), true );
        }

        $this->assertSame( $expected_hash, $this->object->getHashesForPieces( 1 ) );
    }

    /**
     * @expectedException PHPTracker\File\Error\UnreadableError
     */
    public function testGetHashesForPiecesUnreadable()
    {
        unlink( $this->original_path );
        $this->object->getHashesForPieces( 10 );
    }

    public function testBasename()
    {
        $this->assertEquals( basename( $this->original_path ), $this->object->basename() );
    }

    public function testReadBlock()
    {
        $this->assertEquals( substr( self::TEST_DATA, 2, 2 ), $this->object->readBlock( 2, 2 ) );
    }
}
