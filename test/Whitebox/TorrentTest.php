<?php

namespace PHPTracker\Test\Whitebox;

use PHPTracker\File\File;
use PHPTracker\Torrent;
use CWE\Libraries\Bencode;

/**
 * Test class for PHPTracker\Torrent.
 */
class TorrentTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PHPTracker\Torrent
     */
    private $object;

    private $file_path;

    const TEST_DATA = 'abcdefghijklmnopqrstuvwxyz';

    public function setUp()
    {
        // File name must be fix for info hashing.
        $this->file_path = sys_get_temp_dir() . '/test_torrent';
        file_put_contents( $this->file_path, self::TEST_DATA );

        $file = new File( $this->file_path );

        $this->object = new Torrent( $file, 2 );
    }

    public function tearDown()
    {
        // Closing file handles.
        unset( $this->object );
        // Remove temporary file.
        if ( file_exists( $this->file_path ) )
        {
            unlink( $this->file_path );
        }
    }

    public function testProperties()
    {
        $this->assertTrue( isset( $this->object->pieces ) );
        $this->assertTrue( isset( $this->object->length ) );
        $this->assertTrue( isset( $this->object->name ) );
        $this->assertTrue( isset( $this->object->size_piece ) );
        $this->assertTrue( isset( $this->object->info_hash ) );
        $this->assertTrue( isset( $this->object->file_path ) );

        $this->assertEquals( $this->createPiecesHash( self::TEST_DATA, 2 ), $this->object->pieces );
        $this->assertEquals( strlen( self::TEST_DATA ), $this->object->length );
        $this->assertEquals( basename( $this->file_path ), $this->object->name );
        $this->assertEquals( 2, $this->object->size_piece );
        $this->assertEquals( realpath($this->file_path), $this->object->file_path );

        $info_hash_readable = unpack( 'H*', $this->object->info_hash );
        $info_hash_readable = current( $info_hash_readable );

        // Trust me on this.
        $this->assertEquals( '0a144b67246d58f90ab22c22646952dd1465bf2c', $info_hash_readable );
    }

    public function testCreateTorrentFile()
    {
        $bencoded_torrent = (string) $this->object->createTorrentFile( array( 'http://announce' ) );
        $decoded_torrent = Bencode::decode($bencoded_torrent);

        $this->assertEquals( $decoded_torrent['info']['piece length'], $this->object->size_piece );
        $this->assertEquals( $decoded_torrent['info']['name'], $this->object->name );
        $this->assertEquals( $decoded_torrent['info']['length'], $this->object->length );
        $this->assertEquals( $decoded_torrent['info']['pieces'], $this->object->pieces );

        $this->assertEquals( $decoded_torrent['announce'], 'http://announce' );
        $this->assertContains( array( 'http://announce' ), $decoded_torrent['announce-list'] );
    }

    public function testReadBlock()
    {
        $this->assertEquals( 'c', $this->object->readBlock( 1, 0, 1 ) );
        $this->assertEquals( 'd', $this->object->readBlock( 1, 1, 1 ) );
        $this->assertEquals( 'ef', $this->object->readBlock( 2, 0, 2 ) );
    }

    /**
     * @expectedException PHPTracker\Error\BlockReadError
     */
    public function testReadBlockNoPiece()
    {
        // Piece index too high.
        $this->object->readBlock( 80, 0, 1 );
    }

    /**
     * @expectedException PHPTracker\Error\BlockReadError
     */
    public function testReadBlockTooLargeBlock()
    {
        // Block size too high
        $this->object->readBlock( 0, 1, 2 );
    }

    private function createPiecesHash( $data, $piece_size )
    {
        $pieces = '';
        while ( '' != $data )
        {
            $pieces .= sha1( substr( $data, 0, min( $piece_size, strlen( $data ) ) ), true );
            $data = substr( $data, min( $piece_size, strlen( $data ) ) );
        }
        return $pieces;
    }

}
