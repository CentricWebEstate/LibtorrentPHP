<?php

declare( ticks = 1 );

namespace PHPTracker\Test\Blackbox;

use PHPTracker\Persistence\SqlPersistence;
use PHPTracker\Core;

class SeedServerTest extends \PHPUnit_Framework_TestCase
{
    private $persistence;

    const SEED_SERVER_IP        = '0.0.0.0';
    const SEED_SERVER_PORT      = 1988;
    const ANNOUNCE_SERVER_IP    = '0.0.0.0';
    const ANNOUNCE_SERVER_PORT  = 1989;
    const FILE_TO_DOWNLOAD      = 'cookie_monster.gif';
    const PIECE_LENGTH          = 524288;
    const TEST_TIMEOUT          = 120;

    public function setUp()
    {
        $this->parent_pid = posix_getpid();
        $this->setupDatabaseFixture();
    }

    public function tearDown()
    {
        if ( $this->parent_pid != posix_getpid() )
        {
            // We are in a child so no teardown is needed.
            return;
        }

        if ( file_exists( $this->torrent_file ) )
        {
            unlink( $this->torrent_file );
        }

        if ( file_exists( $this->download_destination ) )
        {
            shell_exec( 'rm -rf ' . escapeshellarg( $this->download_destination ) );
        }

        if ( isset( $this->announce_server_pid ) )
        {
            posix_kill( $this->announce_server_pid, SIGTERM );
        }

        if ( isset( $this->seed_server_pid ) )
        {
            posix_kill( $this->seed_server_pid, SIGTERM );
        }

        if ( isset( $this->torrent_client_pid ) )
        {
            posix_kill( $this->torrent_client_pid, SIGTERM );
        }
    }

    /**
     * @group slow
     * @requires pdo
     * @requires posix
     * @requires pcntl_fork
     */
    public function testSeeding()
    {
        $this->torrent_file           = $this->createTorrentFile();
        $this->download_destination   = $this->createDownloadDestination();
        $this->announce_server_pid    = $this->startAnnounceServer();
        $this->seed_server_pid        = $this->startSeedServer();
        $this->torrent_client_pid     = $this->startTorrentClient();

        // We don't want to wait forever.
        $self = $this;
        pcntl_signal( SIGALRM, function() use ( $self )
        {
            $self->fail( 'Test timed out.' );
        } );
        pcntl_alarm( self::TEST_TIMEOUT );

        $pid_exit = pcntl_wait( $status );

        switch( $pid_exit )
        {
            case -1:
                $this->fail( 'Error in child processes.' );
                break;
            case $this->announce_server_pid:
                unset( $this->announce_server_pid );
                $this->fail( 'Announce server exited.' );
                break;
            case $this->seed_server_pid:
                unset( $this->seed_server_pid );
                $this->fail( 'Seed server exited.' );
                break;
            case $this->torrent_client_pid:
                unset( $this->torrent_client_pid );
                break;
        }

        $download_path =
            $this->download_destination .
            '/' . self::FILE_TO_DOWNLOAD;

        $this->assertFileExists( $download_path );

        $downloaded_hash    = sha1_file( $download_path );
        $expected_hash      = sha1_file(
            __DIR__ . '/../Fixtures/' . self::FILE_TO_DOWNLOAD );

        $this->assertEquals( $expected_hash, $downloaded_hash );
    }

    /**
     * Starts seed server to seed the file to the torrent client.
     * Needs PHP in your path.
     */
    private function startSeedServer()
    {
        return $this->spawn(
            self::findExecutable( 'php' ),
            array(
                __DIR__ . "/../Fixtures/seed_server.php",
                self::SEED_SERVER_IP,
                self::SEED_SERVER_PORT
            )
        );
    }

    /**
     * Starts tracker server.
     *
     * It uses PHP's built-in web server avaibale from 5.4.
     * Needs PHP in your path.
     */
    private function startAnnounceServer()
    {
        return $this->spawn(
            self::findExecutable( 'php' ),
            array(
                "-S" . self::ANNOUNCE_SERVER_IP . ":" . self::ANNOUNCE_SERVER_PORT,
                "-t" . __DIR__ . "/../Fixtures/",
            )
        );
    }

    /**
     * Starts torrent client to download the file we generated torrent
     * file for.
     *
     * Needs ctorrent to be installed and in your path.
     */
    private function startTorrentClient()
    {
        return $this->spawn(
            self::findExecutable( 'ctorrent' ),
            array(
                "-E0",
                "-e0", // Exiting without seeding.
                "-s".
                            $this->download_destination .
                            '/' .
                            self::FILE_TO_DOWNLOAD,
                $this->torrent_file,
            )
        );
    }

    private function spawn( $program, array $arguments )
    {
        // PHP-style spawn...
        $pid = pcntl_fork();

        if( $pid < 0 )
        {
            $this->fail( "Couldn't not spawn: $pid" );
        }

        if ( $pid > 0 )
        {
            return $pid;
        }

        pcntl_exec( $program, $arguments );

        // We are in the child, we can finish here.
        // The program only gets here in case of an error.
        die();
    }

    private function createTorrentFile()
    {
        $announce_url =
            'http://' . self::ANNOUNCE_SERVER_IP .
            ':' . self::ANNOUNCE_SERVER_PORT .
            '/announce_front_controller.php';

        $core = new Core( $this->persistence );

        $contents = $core->createTorrent(
            $announce_url,
            __DIR__ . '/../Fixtures/' . self::FILE_TO_DOWNLOAD,
            self::PIECE_LENGTH
        );

        $file_name = sys_get_temp_dir() . "/phptracker_torrent" . uniqid() . '.torrent';
        file_put_contents( $file_name, $contents );

        return $file_name;
    }

    private function setupDatabaseFixture()
    {
        $table_definitions = file_get_contents(
            __DIR__ . '/../Fixtures/sqlite_tables.sql'
        );

        $driver = new \PDO( 'sqlite:' . __DIR__ . '/../Fixtures/sqlite_test.db' );
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

    private function generateTempDatabasePath()
    {
        return sys_get_temp_dir() . "/phptracker_db" . uniqid() . '.db';
    }

    private function createDownloadDestination()
    {
        $path = sys_get_temp_dir() . "/phptracker_downloads" . uniqid();
        mkdir( $path, 0777, true );

        return realpath($path);
    }

    private static function findExecutable( $command_in_path )
    {
        return rtrim( shell_exec( 'which ' . $command_in_path ), "\n" );
    }
}