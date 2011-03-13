<?php

namespace PHPTracker\Seeder;

use PHPTracker\Concurrency\ConcurrentInterface;
use PHPTracker\Concurrency\Forker;
use PHPTracker\Persistence\PersistenceInterface;
use PHPTracker\Logger\LoggerInterface;
use PHPTracker\Logger\BlackholeLogger;

/**
 * Starts seeding server.
 *
 * Creates 2 different forks from itself. The first starts the peer server
 * (creating its own forks), the second will make anounce the peer regularly.
 *
 * @package PHPTracker
 * @subpackage Seeder
 */
class Server implements ConcurrentInterface
{
    /**
     * Peer object instance to use in this server.
     *
     * @var Peer
     */
    private $peer;

    /**
     * Persistence class to save/retrieve data.
     *
     * @var PHPTracker\Persistence\PersistenceInterface
     */
    private $persistence;

    /**
     * Logger object used to log messages and errors in this class.
     *
     * @var PHPTracker\Logger\LoggerInterface
     */
    private $logger;

    /**
     * Interval for doing announcements to the database.
     *
     * Be careful with the timeout of DB connections!
     */
    const ANNOUNCE_INTERVAL     = 30;

    /**
     * To prevent possible memory leaks, every fork terminates after X iterations.
     *
     * The fork is automatically recreated by the parent process, so nothing changes.
     * In our case one iterations means one announcement to the database.
     * Peer object forks its own processes and has its own memory leaking prevention.
     */
    const STOP_AFTER_ITERATIONS = 20;

    /**
     * Initializes the object with the config class.
     */
    public function  __construct( Peer $peer, PersistenceInterface $persistence )
    {
        // It's a daemon.
        set_time_limit( 0 );

        $this->peer         = $peer;
        $this->persistence  = $persistence;
        $this->logger       = new BlackholeLogger();
    }

    /**
     * Sets logger object.
     *
     * Default: BlackholeLogger
     *
     * @param LoggerInterface $logger
     * @return self For fluent interface.
     */
    public function setLogger( LoggerInterface $logger )
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Returns the number of the desired child processes to be forked.
     *
     * @return integer
     */
    public function getNumberOfForks()
    {
        return 2;
    }

    /**
     * Tells if processes are guarded, that is, should be restarted
     * after they fail.
     *
     * In case of a server application it makes sense to restart failing forks.
     *
     * @return boolean
     */
    public function isGuarded()
    {
        return true;
    }

    /**
     * Starts te server by working itself to 2 chid processes.
     *
     * The first child takes care of announcing itself to the database,
     * while the 2nd starts the peer answering incoming download requests.
     */
    public function start()
    {
        $forker = new Forker( $this );
        $forker->fork();
    }

    /**
     * Called on child processes after forking.
     *
     * For slot 0: Starts seeding peer.
     * For slot 1: Starts announcing loop.
     *
     * @param integer $slot The slot (numbered index) of the fork. Reused when recreating process.
     */
    public function afterFork( $slot )
    {
        if ( $this->persistence instanceof PHPTracker\Persistence\ResetWhenForking )
        {
            $this->persistence->resetAfterForking();
        }

        switch( $slot )
        {
            case 0:
                $this->peer->start();
                break;
            case 1:
                $this->announce();
                break;
            default:
                throw new \LogicException( 'Invalid process slot while running seeder server.' );
        }
    }

    /**
     * Save announce for all the torrents in the database so clients know where to connect.
     *
     * This method runs in infinite loop repeating announcing every self::ANNOUNCE_INTERVAL seconds.
     */
    private function announce()
    {
        $iterations     = 0;

        do
        {
            $all_torrents = $this->persistence->getAllInfoHash();

            foreach ( $all_torrents as $torrent_info )
            {
                $this->persistence->saveAnnounce(
                    $torrent_info['info_hash'],
                    $this->peer->getPeerId(),
                    $this->peer->getExternalAddress(),
                    $this->peer->getPort(),
                    $torrent_info['length'],
                    0, // Uploaded.
                    0, // Left.
                    'complete',
                    self::ANNOUNCE_INTERVAL
                );
            }

            $this->logger->logMessage( 'Seeder server announced itself for ' . count( $all_torrents ) . " torrents at address {$this->peer->getExternalAddress()}:{$this->peer->getPort()} (announces every " . self::ANNOUNCE_INTERVAL . 's).' );

            sleep( self::ANNOUNCE_INTERVAL );
        } while ( ++$iterations < self::STOP_AFTER_ITERATIONS ); // Memory leak prevention, see self::STOP_AFTER_ITERATIONS.

        $this->logger->logMessage( 'Announce process restarts to prevent memory leaks.' );
        exit( 0 );
    }
}
