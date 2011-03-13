<?php

namespace PHPTracker\Test\Blackbox;

use PHPTracker\Persistence\SqlPersistence;
use PHPTracker\Core;
use CWE\Libraries\Bencode;

class AnnounceTest extends \PHPUnit_Framework_TestCase
{
    private $persistence;

    const CLIENT_IP             = '123.123.123.123';
    const CLIENT_PORT           = '555';
    const CLIENT_PORT_COMPACT   = "\x02\x2B";
    const ANNOUNCE_INTERVAL     = 60;
    const INFO_HASH             = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
    const PEER_ID               = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\1";
    const SEED_PEER_ID          = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\2";
    const SEED_IP               = '124.124.124.124';
    const SEED_IP_COMPACT       = "\x7c\x7c\x7c\x7c";
    const LEECH_PEER_ID         = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\3";
    const LEECH_IP              = '125.125.125.125';
    const LEECH_IP_COMPACT      = "\x7d\x7d\x7d\x7d";

    public function setUp()
    {
        $this->setupDatabaseFixture();
    }

    /**
     * @requires pdo
     */
    public function testFirstAnnounce()
    {
        $core = new Core( $this->persistence );
        $get = array(
            'info_hash'     => self::INFO_HASH,
            'peer_id'       => self::PEER_ID,
            'port'          => self::CLIENT_PORT,
            'uploaded'      => 1024,
            'downloaded'    => 2048,
            'left'          => 4096,
        );

        $response = $core->announce( $get, self::CLIENT_IP, self::ANNOUNCE_INTERVAL );
        $parsed_response = $this->parseResponse( $response );

        $this->assertEquals( 0, $parsed_response['complete'] );
        $this->assertEquals( 0, $parsed_response['incomplete'] );
        $this->assertEquals( array(), $parsed_response['peers'] );
        $this->assertEquals(
            self::ANNOUNCE_INTERVAL,
            $parsed_response['interval']
        );
    }

    /**
     * @requires pdo
     */
    public function testAnnounceWithPeers()
    {
        $core = new Core( $this->persistence );

        $this->announceOtherPeers( $core );

        $get = array(
            'info_hash'     => self::INFO_HASH,
            'peer_id'       => self::PEER_ID,
            'port'          => self::CLIENT_PORT,
            'uploaded'      => 1024,
            'downloaded'    => 2048,
            'left'          => 4096,
        );

        $response = $core->announce( $get, self::CLIENT_IP, self::ANNOUNCE_INTERVAL );
        $parsed_response = $this->parseResponse( $response );

        $this->assertEquals( 1, $parsed_response['complete'] );
        $this->assertEquals( 1, $parsed_response['incomplete'] );
        $this->assertContains( array(
            // Using the same port for the other peers.
            'ip'        => self::SEED_IP,
            'port'      => self::CLIENT_PORT,
            'peer id'   => self::SEED_PEER_ID,
        ), $parsed_response['peers'] );
        $this->assertContains( array(
            // Using the same port for the other peers.
            'ip'        => self::LEECH_IP,
            'port'      => self::CLIENT_PORT,
            'peer id'   => self::LEECH_PEER_ID,
        ), $parsed_response['peers'] );
        $this->assertEquals(
            self::ANNOUNCE_INTERVAL,
            $parsed_response['interval']
        );
    }

    /**
     * @requires pdo
     */
    public function testAnnounceWithCompactPeers()
    {
        $core = new Core( $this->persistence );

        $this->announceOtherPeers( $core );

        $get = array(
            'info_hash'     => self::INFO_HASH,
            'peer_id'       => self::PEER_ID,
            'port'          => self::CLIENT_PORT,
            'uploaded'      => 1024,
            'downloaded'    => 2048,
            'left'          => 4096,
            'compact'       => 1,
        );

        $response = $core->announce( $get, self::CLIENT_IP, self::ANNOUNCE_INTERVAL );
        $parsed_response = $this->parseResponse( $response );

        $this->assertEquals( 1, $parsed_response['complete'] );
        $this->assertEquals( 1, $parsed_response['incomplete'] );
        $this->assertContains(
            self::SEED_IP_COMPACT . self::CLIENT_PORT_COMPACT,
            $parsed_response['peers']
        );
        $this->assertContains(
            self::LEECH_IP_COMPACT . self::CLIENT_PORT_COMPACT,
            $parsed_response['peers']
        );
        $this->assertEquals(
            self::ANNOUNCE_INTERVAL,
            $parsed_response['interval']
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

    private function parseResponse( $response )
    {
        return Bencode::decode($response);
    }

    private function announceOtherPeers( $core )
    {
        // Announcing a seeder (testing update of peer as well).
        $core->announce( array(
            'info_hash'     => self::INFO_HASH,
            'peer_id'       => self::SEED_PEER_ID,
            'port'          => self::CLIENT_PORT,
            'uploaded'      => 0,
            'downloaded'    => 1024,
            'left'          => 0,
        ), self::SEED_IP, self::ANNOUNCE_INTERVAL );

        $core->announce( array(
            'info_hash'     => self::INFO_HASH,
            'peer_id'       => self::SEED_PEER_ID,
            'port'          => self::CLIENT_PORT,
            'uploaded'      => 0,
            'downloaded'    => 7168,
            'left'          => 6144,
            'event'         => 'completed'
        ), self::SEED_IP, self::ANNOUNCE_INTERVAL );

        // Announcing a leecher.
        $core->announce( array(
            'info_hash'     => self::INFO_HASH,
            'peer_id'       => self::LEECH_PEER_ID,
            'port'          => self::CLIENT_PORT,
            'uploaded'      => 1024,
            'downloaded'    => 2048,
            'left'          => 4096,
        ), self::LEECH_IP, self::ANNOUNCE_INTERVAL );
    }
}
