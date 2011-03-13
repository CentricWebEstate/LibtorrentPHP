<?php

namespace PHPTracker\Seeder;

use PHPTracker\Seeder\Error\SocketError;
use PHPTracker\Seeder\Error\CloseConnection;

/**
 * Object holding state of a client connecting to the seeder server.
 *
 * @package PHPTracker
 * @subpackage Seeder
 */
class Client
{
    /**
     * 20 bytes peer ID of the client.
     *
     * @var string
     */
    public $peer_id;

    /**
     * Address of the connecting client.
     *
     * @var string
     */
    public $address;

    /**
     * Port where client listens.
     *
     * @var integer
     */
    public $port;

    /**
     * The torrent the the client intends to download.
     *
     * @var PHPTracker\Torrent
     */
    public $torrent;

    /**
     * Socket established for the incoming connection (not the one listening!).
     *
     * @var resource
     */
    private $communication_socket;

    /**
     * Flag to tell if the client is 'choked' by the seed server.
     *
     * If true, no requests will be answered until the client is unchoked.
     *
     * @var boolean
     */
    private $choked = true;

    /**
     * Stat counter of bytes sent in total to the client (including protocol messages).
     *
     * @var boolean
     */
    private $bytes_sent = 0;

    /**
     * Stat counter of bytes received in total from the client (including protocol messages).
     *
     * @var boolean
     */
    private $bytes_received = 0;

    /**
     * Stat counter of data bytes sent in total to the client (excluding protocol messages).
     *
     * @var boolean
     */
    private $data_sent = 0;

    /**
     * Used in self::addStatBytes $type argument, tells which counter to increment.
     */
    const STAT_BYTES_SENT       = 1;

    /**
     * Used in self::addStatBytes $type argument, tells which counter to increment.
     */
    const STAT_BYTES_RECEIVED   = 2;

    /**
     * Used in self::addStatBytes $type argument, tells which counter to increment.
     */
    const STAT_DATA_SENT        = 3;

    /**
     * Start accepting incoming connections on the listening socket.
     *
     * @param resource $listening_scket
     */
    public function __construct( $listening_scket )
    {
        $this->socketAccept( $listening_scket );
    }

    /**
     * Closing open communication socket if the object is destructed.
     */
    public function __destruct()
    {
        if ( isset( $this->communication_socket ) )
        {
            socket_close( $this->communication_socket );
        }
    }

    /**
     * Blocks execution until incoming connection comes.
     *
     * @throws SocketError If the accepting is unsuccessful.
     * @param resource $listening_socket
     */
    public function socketAccept( $listening_scket )
    {
        if ( false === ( $this->communication_socket = socket_accept( $listening_scket ) ) )
        {
            $this->communication_socket = null;
            throw new SocketError( 'Socket accept failed: ' . socket_strerror( socket_last_error( $this->listening_socket ) ) );
        }
        // After successfully accepting connection, we obtain IP address and port of the client for logging.
        if ( false === socket_getpeername( $this->communication_socket, $this->address, $this->port ) )
        {
            $this->address = $this->port = null;
        }
    }

    /**
     * Reads the expected length of bytes from the client.
     *
     * Blocks execution until the wanted number of bytes arrives.
     *
     * @param integer $expected_length Expected message length in bytes.
     * @throws SocketError If reading fails.
     * @throws CloseConnection If client closes the connection.
     * @return string
     */
    public function socketRead( $expected_length )
    {
        $message = '';

        while ( strlen( $message ) < $expected_length )
        {
            // We read max 2kB at a time.
            $bytes_to_read = min( $expected_length - strlen( $message ), 2048 );
            if ( false === ( $buffer = socket_read( $this->communication_socket, $bytes_to_read, PHP_BINARY_READ ) ) )
            {
                throw new SocketError( 'Socket reading failed: ' . socket_strerror( $err_no = socket_last_error( $this->communication_socket ) ) . " ($err_no)" );
            }
            if ( '' == $buffer )
            {
                throw new CloseConnection( 'Client closed the connection.' );
            }
            $message .= $buffer;
        }

        $this->addStatBytes( $expected_length, self::STAT_BYTES_RECEIVED );

        return $message;
    }

    /**
     * Sends a message to the client.
     *
     * @param string $message
     */
    public function socketWrite( $message )
    {
        socket_write( $this->communication_socket, $message, $len = strlen( $message ) );
        $this->addStatBytes( $len, self::STAT_BYTES_SENT );
    }

    /**
     * Unchokes the client so that it is allowed to send requests.
     * @see http://wiki.theory.org/BitTorrentSpecification#unchoke:_.3Clen.3D0001.3E.3Cid.3D1.3E
     */
    public function unchoke()
    {
        $this->socketWrite( pack( 'NC', 1, 1 ) );
        $this->choked = false;
    }

    /**
     * Chokes the client so that it is not allowed to send requests.
     * @see http://wiki.theory.org/BitTorrentSpecification#choke:_.3Clen.3D0001.3E.3Cid.3D0.3E
     */
    public function choke()
    {
        $this->socketWrite( pack( 'NC', 1, 0 ) );
        $this->choked = true;
    }

    /**
     * Increments data transfer statistics for this client.
     *
     * @param integer $bytes Number of bytes to increment statistics with.
     * @param integer $type Telling the type of the stat, see self::STAT_*.
     */
    public function addStatBytes( $bytes, $type )
    {
        switch ( $type )
        {
            case self::STAT_BYTES_SENT:
                $this->bytes_sent += $bytes;
                break;
            case self::STAT_BYTES_RECEIVED:
                $this->bytes_received += $bytes;
                break;
            case self::STAT_DATA_SENT:
                $this->data_sent += $bytes;
                break;
        }
    }

    /**
     * Gives string representation of data transfer statistics.
     *
     * @return string
     */
    public function getStats()
    {
        return <<<STATS
Bytes sent: $this->bytes_sent,
Bytes received: $this->bytes_received,
Data sent: $this->data_sent
STATS;
    }
}
