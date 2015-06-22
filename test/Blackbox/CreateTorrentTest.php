<?php

namespace CWE\Libraries\LibtorrentPHP\Test\Blackbox;

use CWE\Libraries\LibtorrentPHP\Persistence\SqlPersistence;
use CWE\Libraries\LibtorrentPHP\Core;
use CWE\Libraries\Bencode;

class CreateTorrentTest extends \PHPUnit_Framework_TestCase
{
    private $persistence;

    const ANNOUNCE_URL      = 'http://php-tracker.dev/example_announce.php';
    const FILE_TO_DOWNLOAD  = 'cookie_monster.gif';
    const PIECE_LENGTH      = 524288;

    public function setUp()
    {
        $this->setupDatabaseFixture();
    }

    public function testTorrentFileContents()
    {
        $torrent_file   = $this->createTorrent();
        $parsed_torrent = $this->parseTorrent( $torrent_file );

        $this->assertEquals( self::ANNOUNCE_URL, $parsed_torrent['announce'] );
        $this->assertEquals(
            array( array( self::ANNOUNCE_URL ) ),
            $parsed_torrent['announce-list']
        );
        $this->assertEquals(
            self::FILE_TO_DOWNLOAD,
            $parsed_torrent['info']['name']
        );
        $this->assertEquals(
            self::PIECE_LENGTH,
            $parsed_torrent['info']['piece length']
        );
        $this->assertEquals(
            filesize( __DIR__ . '/../Fixtures/' . self::FILE_TO_DOWNLOAD ),
            $parsed_torrent['info']['length']
        );

        // We don't verify pieces here, because setting up the fixture
        // is difficult and prone to creating test for the output and not
        // the other way around. However, we test pieces with
        // another system test with real download.
        $this->assertArrayHasKey( 'pieces', $parsed_torrent['info'] );
    }

    public function testPersistence()
    {
        $torrent_file   = $this->createTorrent();
        $info_hash      = $this->getInfoHash( $torrent_file );
        $saved_torrent  = $this->persistence->getTorrent( $info_hash );

        $this->assertEquals(
            (string) $torrent_file,
            (string) $saved_torrent->createTorrentFile( array(
                self::ANNOUNCE_URL,
            ) )
        );
    }

    private function setupDatabaseFixture()
    {
        // @todo: change to sqlite
        $table_definitions = file_get_contents(
            __DIR__ . '/../Fixtures/sqlite_tables.sql'
        );

        $driver = new \PDO( 'sqlite::memory:' );
        $statements = preg_split( '/;[ \t]*\n/', $table_definitions, -1, PREG_SPLIT_NO_EMPTY );

        foreach ( $statements as $statement )
        {
            if ( !$driver->query( $statement ) )
            {
                $this->fail(
                    'Could not set up database fixture: ' .
                    var_export( $driver->errorInfo(), true )
                );
            }
        }

        $this->persistence = new SqlPersistence( $driver );
    }

    private function createTorrent()
    {
        $core = new Core( $this->persistence );

        return $core->createTorrent(
            self::ANNOUNCE_URL,
            __DIR__ . '/../Fixtures/' . self::FILE_TO_DOWNLOAD,
            self::PIECE_LENGTH
        );
    }

    private function getInfoHash( $torrent )
    {
        $parsed_torrent = $this->parseTorrent( $torrent );
        return sha1( Bencode::encode( array(
            'piece length'  => $parsed_torrent['info']['piece length'],
            'pieces'        => $parsed_torrent['info']['pieces'],
            'name'          => $parsed_torrent['info']['name'],
            'length'        => $parsed_torrent['info']['length'],
            'private'       => $parsed_torrent['info']['private']
        ) ), true );
    }

    private function parseTorrent( $torrent )
    {
        return Bencode::decode($torrent);
    }
}
