<?php

namespace CWE\Libraries\LibtorrentPHP;

use CWE\Libraries\LibtorrentPHP\File\File;
use CWE\Libraries\LibtorrentPHP\Error\EmptyAnnounceListError;
use CWE\Libraries\LibtorrentPHP\PErsistence\PersistenceInterface;
use CWE\Libraries\Bencode;

/**
 * Public interface to access some Bittorrent actions like creating torrent file and announcing peer.
 *
 * @package PHPTracker
 */
class Core
{
    /**
     * Persistence class to save/retrieve data.
     *
     * @var PersistenceInterface
     */
    private $persistence;

    /**
     * Setting up instance.
     *
     * @param PersistenceInterface $persistence Persitence implementation.
     *                             Has to be shared with torrent creator and
     *                             seed server.
     */
    public function  __construct( PersistenceInterface $persistence )
    {
        $this->persistence  = $persistence;
    }

    /**
     * Creates a string representing a .torrent file.
     *
     * @param string $file_path Full path of the file to use to generate torrent file (will be opeend and hashed).
     * @param integer $size_piece Size of one piece in bytes. Normally a power of 2, defaults to 256KB.
     * @throws PHPTracker_Error When the announce-list is empty.
     * @return string
     */
    public function createTorrent( $announce_urls, $file_path, $size_piece = 262144, $basename = null )
    {
        $torrent = new Torrent( new File( $file_path ), $size_piece, $file_path, $basename );

        $announce_urls = (array) $announce_urls;
        if ( empty( $announce_urls ) )
        {
            throw new EmptyAnnounceListError( 'Empty announce list!' );
        }

        $this->persistence->saveTorrent( $torrent );

        return $torrent->createTorrentFile( $announce_urls );
    }

    /**
     * Announce a peer to be tracked and return message to the client.
     *
     * @param array $get Parameters from the query string sent from the peer.
     * @param string $ip Annotated IP address of the peer.
     * @param integer $ip Desired interval between future announcings.
     * @return string
     */
    public function announce( array $get, $ip, $interval )
    {
        try
        {
            $mandatory_keys = array(
                'info_hash',
                'peer_id',
                'port',
                'uploaded',
                'downloaded',
                'left',
            );
            $missing_keys = array_diff( $mandatory_keys, array_keys( $get ) );
            if ( !empty( $missing_keys ) )
            {
                return $this->announceFailure( "Invalid get parameters; Missing: " . implode( ', ', $missing_keys ) );
            }

            // IP address might come from $_GET.
            $ip         = isset( $get['ip'] )           ? $get['ip']            : $ip;
            $event      = isset( $get['event'] )        ? $get['event']         : '';
            $compact    = isset( $get['compact'] )      ? $get['compact']       : 0;
            $no_peer_id = isset( $get['no_peer_id'] )   ? $get['no_peer_id']    : 0;

            if ( 20 != strlen( $get['info_hash'] ) )
            {
                return $this->announceFailure( "Invalid length of info_hash." );
            }
            if ( 20 != strlen( $get['peer_id'] ) )
            {
                return $this->announceFailure( "Invalid length of peer_id." );
            }
            if ( !self::isNonNegativeInteger( $get['port'] ) )
            {
                return $this->announceFailure( "Invalid port value." );
            }
            if ( !self::isNonNegativeInteger( $get['uploaded'] ) )
            {
                return $this->announceFailure( "Invalid uploaded value." );
            }
            if ( !self::isNonNegativeInteger( $get['downloaded'] ) )
            {
                return $this->announceFailure( "Invalid downloaded value." );
            }
            if ( !self::isNonNegativeInteger( $get['left'] ) )
            {
                return $this->announceFailure( "Invalid left value." );
            }

            $this->persistence->saveAnnounce(
                $get['info_hash'],
                $get['peer_id'],
                $ip,
                $get['port'],
                $get['downloaded'],
                $get['uploaded'],
                $get['left'],
                ( 'completed' == $event ) ? 'complete' : null, // Only set to complete if client said so.
                ( 'stopped' == $event ) ? 0 : $interval * 2 // If the client gracefully exists, we set its ttl to 0, double-interval otherwise.
            );

            $peers = $this->persistence->getPeers( $get['info_hash'], $get['peer_id'] );

            if ( $compact )
            {
                $peers = $this->compactPeers( $peers );
            }
            elseif ( $no_peer_id )
            {
                $peers = $this->removePeerId( $peers );
            }

            $peer_stats     = $this->persistence->getPeerStats( $get['info_hash'], $get['peer_id'] );

            $announce_response = array(
                'interval'      => $interval,
                'complete'      => intval( $peer_stats['complete'] ),
                'incomplete'    => intval( $peer_stats['incomplete'] ),
                'peers'         => $peers,
            );

            return Bencode::encode( $announce_response );
        }
        catch ( Exception $e )
        {
            trigger_error( 'Failure while announcing: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), E_USER_WARNING );
            return $this->announceFailure( "Failed to announce because of internal server error." );
        }
    }

    /**
     * As per request of the announcing client we might need to compact peers.
     *
     * Compacting means representing the IP in a big-endian long and the port
     * as a big-endian short and concatenating all of them in a string.
     *
     * @see http://wiki.theory.org/BitTorrentSpecification#Tracker_Response
     * @param array $peers List of peers with their IP address and port.
     * @return string
     */
    private function compactPeers( array $peers )
    {
        $compact_peers = "";
        foreach ( $peers as $peer )
        {
            $compact_peers .=
                pack( 'N', ip2long( $peer['ip'] ) ) .
                pack( 'n', $peer['port'] );
        }
        return $compact_peers;
    }

    /**
     * As per request of the announcing client we might need to remove peer IDs.
     *
     * @see http://wiki.theory.org/BitTorrentSpecification#Tracker_Response
     * @param array $peers List of peers with their IP address and port.
     * @return string
     */
    private function removePeerId( array $peers )
    {
        foreach ( $peers as $peer_index => $peer )
        {
            unset( $peers[$peer_index]['peer id'] );
        }
        return $peers;
    }

    /**
     * Creates a bencoded announce failure message.
     *
     * @param string $message Public description of the failure.
     * @return string
     */
    private function announceFailure( $message )
    {
        return Bencode::encode( array(
            'failure reason' => $message
        ) );
    }

    /**
     * Tells if a passed value (user input) is a non-negative integer.
     *
     * @return true
     */
    private static function isNonNegativeInteger( $value )
    {
        return
            is_numeric( $value )
            &&
            is_int( $value = $value + 0 )
            &&
            0 <= $value;
    }
}
