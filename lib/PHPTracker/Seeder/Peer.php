<?php

namespace PHPTracker\Seeder;

use PHPTracker\Concurrency\ConcurrentInterface;
use PHPTracker\Concurrency\Forker;
use PHPTracker\Seeder\Error\CloseConnection;
use PHPTracker\Seeder\Error\SocketError;
use PHPTracker\Persistence\PersistenceInterface;
use PHPTracker\Logger\LoggerInterface;
use PHPTracker\Logger\BlackholeLogger;

/**
 * Daemon seeding all active torrent files on this server.
 *
 * @package PHPTracker
 * @subpackage Seeder
 */
class Peer implements ConcurrentInterface
{
    /**
     * Annotated IP address for external peers to connect to
     *
     * Defaults to 127.0.0.1.
     * Used for announcing, should be public.
     *
     * @var string
     */
    private $external_address = '127.0.0.1';

    /**
     * Annotated IP address to bind the socket to.
     *
     * Defaults to 127.0.0.1.
     *
     * @var string
     */
    private $internal_address;

    /**
     * Port number to bind the socket to. Defaults to 6881.
     *
     * @var integer
     */
    public $port = 6881;

    /**
     * Number of connection accepting processes to fork to encure concurrent downloads.
     *
     * Default: 5
     *
     * @var integer
     */
    private $peer_forks = 5;

    /**
     * Number of active external seeders (fully downloaded files) after
     * which the seed server stops seeding. This is to save bandwidth costs.
     *
     * Default: 0 - don't stop.
     *
     * @var integer
     */
    private $seeders_stop_seeding = 0;

    /**
     * Azureus-style peer ID generated from the address and port.
     *
     * @var string
     */
    public $peer_id;

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
     * Open socket that accepts incoming connections. Child processes share this.
     *
     * @var resource
     */
    private $listening_socket;

    /**
     * One and only supported protocol name, Bittorrent 1.0.
     */
    const PROTOCOL_STRING = 'BitTorrent protocol';

    /**
     * Prefix for the generated peer id.
     */
    const PEER_ID_PREFIX = '-PT0001-';

    /**
     * To prevent possible memory leaks, every fork terminates after X iterations.
     *
     * The fork is automatically recreated by the parent process, so nothing changes.
     * In our case one iterations means one client connection session.
     */
    const STOP_AFTER_ITERATIONS = 20;

    /**
     * Setting up instance.
     *
     * @param PersistenceInterface $persistence Persitence implementation.
     *                                          Has to be shared with announcer
     *                                          and torrent creator.
     */
    public function  __construct( PersistenceInterface $persistence )
    {
        $this->persistence           = $persistence;
        $this->logger                = new BlackholeLogger();
        $this->internal_address      = $this->external_address;

        $this->peer_id               = $this->generatePeerId();
    }

    /**
     * Sets "public" IP address of the sverver that is sent to peers from the tracker.
     *
     * Default: 127.0.0.1
     *
     * @param string $external_address Annotated IP address.
     * @return self For fluent interface.
     */
    public function setExternalAddress( $external_address )
    {
        $this->external_address = $external_address;
        return $this;
    }

    /**
     * Gets "public" IP address of the sverver that is sent to peers from the tracker.
     *
     * Default: 127.0.0.1
     *
     * @return string
     */
    public function getExternalAddress()
    {
        return $this->external_address;
    }

    /**
     * Sets "listen" IP address of the server, the one to bind socket to.
     *
     * Default: 127.0.0.1
     *
     * @param string $internal_address Annotated IP address.
     * @return self For fluent interface.
     */
    public function setInternalAddress( $internal_address )
    {
        $this->internal_address = $internal_address;
        return $this;
    }

    /**
     * Sets port number to bind listening socket to.
     *
     * Default: 6881
     *
     * @param integer $port
     * @return self For fluent interface.
     */
    public function setPort( $port )
    {
        $this->port = $port;
        return $this;
    }

    /**
     * Gets port number to bind listening socket to.
     *
     * Default: 6881
     *
     * @return integer
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Sets number of connection accepting processes to fork to encure concurrent downloads.
     *
     * Default: 5
     *
     * @param integer $peer_forks
     * @return self For fluent interface.
     */
    public function setPeerForks( $peer_forks )
    {
        $this->peer_forks = $peer_forks;
        return $this;
    }

    /**
     * Set number of active external seeders (fully downloaded files) after
     * which the seed server stops seeding. This is to save bandwidth costs.
     *
     * Default: 0 - don't stop.
     *
     * @param integer $seeders_stop_seeding
     * @return self For fluent interface.
     */
    public function setSeedersStopSeeding( $seeders_stop_seeding )
    {
        $this->seeders_stop_seeding = $seeders_stop_seeding;
        return $this;
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
     * Generates unique Azuerus style peer ID from the address and port.
     *
     * @return string
     */
    private function generatePeerId()
    {
        return self::PEER_ID_PREFIX . substr( sha1( $this->external_address . $this->port, true ), 0, 20 );
    }

    /**
     * Gets peer id.
     *
     * @return string
     */
    public function getPeerId()
    {
        return $this->peer_id;
    }

    /**
     * Called before forking children, intializes the object and sets up listening socket.
     *
     * @return Number of forks to create. If negative, forks are recreated when exiting and absolute values is used.
     */
    public function start()
    {
        // Opening socket - file dscriptor will be shared among the children.
        $this->startListening();

        $forker = new Forker( $this );
        $forker->fork();
    }

    /**
     * Returns the number of the desired child processes to be forked.
     *
     * @return integer
     */
    public function getNumberOfForks()
    {
        return $this->peer_forks;
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
     * Called on child processes after forking. Starts accepting incoming connections.
     *
     * @param integer $slot The slot (numbered index) of the fork. Reused when recreating process.
     */
    public function afterFork( $slot )
    {
        // Some persistence providers (eg. MySQL) should create a new connection when the process is forked.
        if ( $this->persistence instanceof PHPTracker\Persistence\ResetWhenForking )
        {
            $this->persistence->resetAfterForking();
        }

        $this->logger->logMessage( "Forked process on slot $slot starts accepting connections." );

        // Waiting for incoming connections.
        $this->communicationLoop();
    }

    /**
     * Setting up listening socket. Should be called before forking.
     *
     * @throws SocketError When error happens during creating, binding or listening.
     */
    private function startListening()
    {
        if ( false === ( $socket = socket_create( AF_INET, SOCK_STREAM, SOL_TCP ) ) )
        {
            throw new SocketError( 'Failed to create socket: ' . socket_strerror( $socket ) );
        }

        $this->listening_socket = $socket;

        if ( false === ( $result = socket_bind( $this->listening_socket, $this->internal_address, $this->port ) ) )
        {
            throw new SocketError( "Failed to bind socket to {$this->internal_address}:{$this->port}: " . socket_strerror( $result ) );
        }

        // We set backlog to 5 (ie. 5 connections can be queued) - to be adjusted.
        if ( false === ( $result = socket_listen( $this->listening_socket, 5 ) ) )
        {
            throw new SocketError( 'Failed to listen to socket: ' . socket_strerror( $result ) );
        }
    }

    /**
     * Loop constantly accepting incoming connections and starting to communicate with them.
     *
     * Every incoming connection initializes a Client object.
     */
    private function communicationLoop()
    {
        $iterations = 0;

        do
        {
            $client = new Client( $this->listening_socket );
            do
            {
                try
                {
                    if ( !isset( $client->peer_id ) )
                    {
                        $this->shakeHand( $client );

                        // Telling the client that we have all pieces.
                        $this->sendBitField( $client );

                        // We are unchoking the client letting it send requests.
                        $client->unchoke();
                    }
                    else
                    {
                        $this->answer( $client );
                    }
                }
                catch ( CloseConnection $e )
                {
                    $this->logger->logMessage( "Closing connection with peer {$client->peer_id} with address {$client->address}:{$client->port}, reason: \"{$e->getMessage()}\". Stats: " . $client->getStats() );
                    unset( $client );

                    // We might wait for another client.
                    break;
                }
            } while ( true );
        } while ( ++$iterations < self::STOP_AFTER_ITERATIONS ); // Memory leak prevention, see self::STOP_AFTER_ITERATIONS.

        $this->logger->logMessage( 'Seeder process fork restarts to prevent memory leaks.' );
        exit( 0 );
    }

    /**
     * Manages handshaking with the client.
     *
     * If seeders_stop_seeding config key is set to a number greater than 0,
     * we check if we have at least N seeders beyond ourselves for the requested
     * torrent and if so, stop seeding (to spare bandwith).
     *
     * @see http://wiki.theory.org/BitTorrentSpecification#Handshake
     * @throws CloseConnection In case when the reqeust is invalid or we don't want or cannot serve the requested torrent.
     * @param Client $client
     */
    private function shakeHand( Client $client )
    {
        $protocol_length = unpack( 'C', $client->socketRead( 1 ) );
        $protocol_length = current( $protocol_length );

        if ( ( $protocol = $client->socketRead( $protocol_length ) ) !== self::PROTOCOL_STRING )
        {
            $this->logger->logError( "Client tries to connect with unsupported protocol: " . substr( $protocol, 0, 100 ) . ". Closing connection." );
            throw new CloseConnection( 'Unsupported protocol.' );
        }

        // 8 reserved void bytes.
        $client->socketRead( 8 );

        $info_hash          = $client->socketRead( 20 );
        $client->peer_id    = $client->socketRead( 20 );

        $info_hash_readable = unpack( 'H*', $info_hash );
        $info_hash_readable = reset( $info_hash_readable );

        $torrent = $this->persistence->getTorrent( $info_hash );
        if ( !isset( $torrent ) )
        {
            throw new CloseConnection( 'Unknown info hash.' );
        }

        $client->torrent = $torrent;

        // If we have X other seeders already, we stop seeding on our own.
        if ( 0 < $this->seeders_stop_seeding )
        {
            $stats = $this->persistence->getPeerStats( $info_hash, $this->peer_id );
            if ( $stats['complete'] >= $this->seeders_stop_seeding )
            {
                $this->logger->logMessage( "External seeder limit ({$this->seeders_stop_seeding}) reached for info hash $info_hash_readable, stopping seeding." );
                throw new CloseConnection( 'Stop seeding, we have others to seed.' );
            }
        }

        // Our handshake signal.
        $client->socketWrite(
            pack( 'C', strlen( self::PROTOCOL_STRING ) ) .  // Length of protocol string.
            self::PROTOCOL_STRING .                         // Protocol string.
            pack( 'a8', '' ) .                              // 8 void bytes.
            $info_hash .                                    // Echoing the info hash that the client requested.
            pack( 'a20', $this->peer_id )                   // Our peer id.
         );

        $this->logger->logMessage( "Handshake completed with peer {$client->peer_id} with address {$client->address}:{$client->port}, info hash: $info_hash_readable." );
    }

    /**
     * Reading messages from the client and answering them.
     *
     * @throws CloseConnection In case of protocol violation.
     * @param Client $client
     */
    private function answer( Client $client )
    {
        $message_length = unpack( 'N', $client->socketRead( 4 ) );
        $message_length = current( $message_length );

        if ( 0 == $message_length )
        {
            // Keep-alive.
            return;
        }

        $message_type = unpack( 'C', $client->socketRead( 1 ) );
        $message_type = current( $message_type );

        --$message_length; // The length of the payload.

        switch ( $message_type )
        {
            case 0:
                // Choke.
                // We are only seeding, we can ignore this.
                break;
            case 1:
                // Unchoke.
                // We are only seeding, we can ignore this.
                break;
            case 2:
                // Interested.
                // We are only seeding, we can ignore this.
                break;
            case 3:
                // Not interested.
                // We are only seeding, we can ignore this.
                break;
            case 4:
                // Have.
                // We are only seeding, we can ignore this.
                $client->socketRead( $message_length );
                break;
            case 5:
                // Bitfield.
                // We are only seeding, we can ignore this.
                $client->socketRead( $message_length );
                break;
            case 6:
                // Requesting one block of the file.
                $payload = unpack( 'N*', $client->socketRead( $message_length ) );
                $this->sendBlock( $client, /* Piece index */ $payload[1], /* First byte from the piece */ $payload[2], /* Length of the block */ $payload[3] );
                break;
            case 7:
                // Piece.
                // We are only seeding, we can ignore this.
                $client->socketRead( $message_length );
                break;
            case 8:
                // Cancel.
                // We send blocks in one step, we can ignore this.
                $client->socketRead( $message_length );
                break;
            default:
                throw new CloseConnection( 'Protocol violation, unsupported message.' );
        }
    }

    /**
     * Sends one block of a file to the client.
     *
     * @see http://wiki.theory.org/BitTorrentSpecification#piece:_.3Clen.3D0009.2BX.3E.3Cid.3D7.3E.3Cindex.3E.3Cbegin.3E.3Cblock.3E
     * @param Client $client
     * @param integer $piece_index Index of the piece containing the block.
     * @param integer $block_begin Beginning of the block relative to the piece in bytes.
     * @param integer $length Length of the block in bytes.
     */
    private function sendBlock( Client $client, $piece_index, $block_begin, $length )
    {
        $message = pack( 'CNN', 7, $piece_index, $block_begin ) . $client->torrent->readBlock( $piece_index, $block_begin, $length );
        $client->socketWrite( pack( 'N', strlen( $message ) ) . $message );

        // Saving statistics.
        $client->addStatBytes( $length, Client::STAT_DATA_SENT );
    }

    /**
     * Sending intial bitfield to the client letting it know that we have to entire file.
     *
     * The bitfeild looks like:
     * [11111111-11111111-11100000]
     * Meaning that we have all the 19 pieces for example (padding bits must be 0).
     *
     * @see http://wiki.theory.org/BitTorrentSpecification#bitfield:_.3Clen.3D0001.2BX.3E.3Cid.3D5.3E.3Cbitfield.3E
     * @param Client $client
     */
    private function sendBitField( Client $client )
    {
        $n_pieces = ceil( $client->torrent->length / $client->torrent->size_piece );

        $message = pack( 'C', 5 );

        while ( $n_pieces > 0 )
        {
            if ( $n_pieces >= 8 )
            {
                $message .= pack( 'C', 255 );
                $n_pieces -= 8;
            }
            else
            {
                // Last byte of the bitfield, like 11100000.
                $message .= pack( 'C', 256 - pow( 2, 8 - $n_pieces ) );
                $n_pieces = 0;
            }
        }

        $client->socketWrite( pack( 'N', strlen( $message ) ) . $message );
    }
}
