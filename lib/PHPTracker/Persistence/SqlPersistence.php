<?php

namespace PHPTracker\Persistence;

use PHPTracker\File\File;
use PHPTracker\Torrent;

/**
 * Persitence class implementaiton using PDO and so supporting many database drivers.
 *
 * @package PHPTracker
 * @subpackage Persistence
 */
class SqlPersistence implements PersistenceInterface, ResetWhenForking
{
    private $driver;

    /**
     * The constructor accepting an intialized PDO driver.
     *
     * @param \PDO $driver PDO instance to use for connecting to the database.
     */
    public function __construct( \PDO $driver )
    {
        $this->driver = $driver;
        $this->driver->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
    }

    /**
     * Save all available info of a Torrent object to be able to recreate it.
     *
     * Uses info_hash property as primary key and overwrite attributes when saved
     * multiple times with the same info hash.
     *
     * @param Torrent $torrent
     */
    public function saveTorrent( Torrent $torrent )
    {
        // We cannot use driver-specific SQl (e.g. "upsert").
        $statement = $this->driver->prepare( <<<SQL
SELECT
    1
FROM
    `phptracker_torrents`
WHERE
    `info_hash` = :info_hash
SQL
        );

        $statement->execute( array( ':info_hash' => $torrent->info_hash ) );

        if ( $statement->fetchColumn( 0 ) )
        {
            $sql = <<<SQL
UPDATE
    `phptracker_torrents`
SET
    `info_hash`     = :info_hash,
    `length`        = :length,
    `pieces_length` = :pieces_length,
    `pieces`        = :pieces,
    `name`          = :name,
    `path`          = :path
WHERE
    `info_hash` = :info_hash
SQL;
        }
        else
        {
            $sql = <<<SQL
INSERT INTO
    `phptracker_torrents`
(
    `info_hash`,
    `length`,
    `pieces_length`,
    `pieces`,
    `name`,
    `path`
)
VALUES
(
    :info_hash,
    :length,
    :pieces_length,
    :pieces,
    :name,
    :path
)
SQL;
        }

        $statement = $this->driver->prepare( $sql );
        $statement->execute( array(
            ':info_hash'         => $torrent->info_hash,
            ':length'            => $torrent->length,
            ':pieces_length'     => $torrent->size_piece,
            ':pieces'            => $torrent->pieces,
            ':name'              => $torrent->name,
            ':path'              => $torrent->file_path,
        ) );
    }

    /**
     * Given a 20 bytes info hash, returns an intialized Torrent object.
     *
     * Must return null if the info hash is not found.
     *
     * @param string $info_hash
     * @return Torrent
     */
    public function getTorrent( $info_hash )
    {
        $sql = <<<SQL
SELECT
    `info_hash`,
    `length`,
    `pieces_length`,
    `pieces`,
    `name`,
    `path`
FROM
    `phptracker_torrents`
WHERE
    `info_hash` = :info_hash
    AND
    `status` = 'active'
SQL;

        $statement = $this->driver->prepare( $sql );
        $statement->execute( array(
            ':info_hash' => $info_hash,
        ) );

        $row = $statement->fetch();

        if ( $row )
        {
            return new Torrent(
                new File( $row['path'] ),
                $row['pieces_length'],
                $row['path'],
                $row['name'],
                $row['length'],
                $row['pieces'],
                $row['info_hash']
            );
        }
        return null;
    }

    /**
     * Saves peer announcement from a client.
     *
     * Majority of the parameters of this method come from GET.
     *
     * @param string $info_hash 20 bytes info hash of the announced torrent.
     * @param string $peer_id 20 bytes peer ID of the announcing peer.
     * @param string $ip Dotted IP address of the client.
     * @param integer $port Port number of the client.
     * @param integer $downloaded Already downloaded bytes.
     * @param integer $uploaded Already uploaded bytes.
     * @param integer $left Bytes left to download.
     * @param string $status Can be complete, incomplete or NULL. Incomplete is default for new rows. If once set to complete, NULL does not set it back on update.
     * @param integer $ttl Time to live in seconds meaning the time after we should consider peer offline (if no more updates come).
     */
    public function saveAnnounce( $info_hash, $peer_id, $ip, $port, $downloaded, $uploaded, $left, $status, $ttl )
    {
        // We cannot use driver-specific SQl (e.g. "upsert").
        $statement = $this->driver->prepare( <<<SQL
SELECT
    1
FROM
    `phptracker_peers`
WHERE
    `peer_id`   = :peer_id
    AND
    `info_hash` = :info_hash
SQL
        );

        $statement->execute( array(
            ':info_hash'    => $info_hash,
            ':peer_id'      => $peer_id,
        ) );

        if ( $statement->fetchColumn( 0 ) )
        {
            $sql = <<<SQL
UPDATE
    `phptracker_peers`
SET
    `ip_address`          = :ip,
    `port`                = :port,
    `bytes_downloaded`    = :downloaded,
    `bytes_uploaded`      = :uploaded,
    `bytes_left`          = :left,
    `status`              = COALESCE( :status, `status` ),
    `expires`             = :expires
WHERE
    `peer_id` = :peer_id
    AND
    `info_hash` = :info_hash
SQL;
        }
        else
        {
            $sql = <<<SQL
INSERT INTO
    `phptracker_peers`
(
    `info_hash`,
    `peer_id`,
    `ip_address`,
    `port`,
    `bytes_downloaded`,
    `bytes_uploaded`,
    `bytes_left`,
    `status`,
    `expires`
)
VALUES
(
    :info_hash,
    :peer_id,
    :ip,
    :port,
    :downloaded,
    :uploaded,
    :left,
    COALESCE( :status, 'incomplete' ),
    :expires
)
SQL;
        }

        if ( is_null( $ttl ) )
        {
            $ttl = 31536000; // One year.
        }
        $expires = new \DateTime();
        $expires->add( new \DateInterval( 'PT' . $ttl . 'S' ) );

        $statement = $this->driver->prepare( $sql );
        $statement->execute( array(
            ':info_hash'    => $info_hash,
            ':peer_id'      => $peer_id,
            ':ip'           => sprintf( "%u", ip2long( $ip ) ),
            ':port'         => $port,
            ':downloaded'   => $uploaded,
            ':uploaded'     => $downloaded,
            ':left'         => $left,
            ':status'       => $status,
            ':expires'      => $expires->format('Y-m-d H:i:s'),
        ) );
    }

    /**
     * Returns all the info_hashes and lengths of the active torrents.
     *
     * @return array An array of arrays having keys 'info_hash' and 'length' accordingly.
     */
    public function getAllInfoHash()
    {
        $sql = <<<SQL
SELECT
    `info_hash`,
    `length`
FROM
    `phptracker_torrents`
WHERE
    `status` = 'active'
SQL;

        $statement = $this->driver->prepare( $sql );
        $statement->execute();

        $data = array();
        while ( $row = $statement->fetch() )
        {
            $data[] = $row;
        }
        return $data;
    }

    /**
     * Gets all the active peers for a torrent.
     *
     * Only considers peers which are not expired (see TTL).
     * Returns:
     *
     * array(
     *  array(
     *      'peer_id'   => ... // ID of the peer, if $no_peer_id is false.
     *      'ip'        => ... // Dotted IP address of the peer.
     *      'port'      => ... // Port number of the peer.
     *  )
     * )
     *
     * @param string $info_hash Info hash of the torrent.
     * @param string $peer_id Peer ID to exclude (peer ID of the client announcing).
     * @return array
     */
    public function getPeers( $info_hash, $peer_id )
    {
        $sql = <<<SQL
SELECT
    `peer_id`,
    `ip_address`,
    `port`
FROM
    `phptracker_peers`
WHERE
    `info_hash`           = :info_hash
    AND
    `peer_id`             != :peer_id
    AND
    (
        `expires` IS NULL
        OR
        `expires` > :now
    )
SQL;

        $now = new \DateTime();

        $statement = $this->driver->prepare( $sql );
        $statement->execute( array(
            ':info_hash'    => $info_hash,
            ':peer_id'      => $peer_id,
            ':now'          => $now->format('Y-m-d H:i:s'),
        ) );

        $peers = array();
        while ( $row = $statement->fetch() )
        {
            $peer = array(
                'peer id'   => $row['peer_id'],
                'ip'        => long2ip( $row['ip_address'] ),
                'port'      => $row['port'],
            );
            $peers[] = $peer;
        }

        return $peers;
    }

    /**
     * Returns statistics of seeders and leechers of a torrent.
     *
     * Only considers peers which are not expired (see TTL).
     *
     * @param string $info_hash Info hash of the torrent.
     * @param string $peer_id Peer ID to exclude (peer ID of the client announcing).
     * @return array With keys 'complete' and 'incomplete' having counters for each group.
     */
    public function getPeerStats( $info_hash, $peer_id )
    {
        $sql = <<<SQL
SELECT
    COALESCE( SUM( `status` = 'complete' ), 0 ) AS 'complete',
    COALESCE( SUM( `status` != 'complete' ), 0 ) AS 'incomplete'
FROM
    `phptracker_peers`
WHERE
    `info_hash`           = :info_hash
    AND
    `peer_id`             != :peer_id
    AND
    (
        `expires` IS NULL
        OR
        `expires` > :now
    )
SQL;

        $now = new \DateTime();

        $statement = $this->driver->prepare( $sql );
        $statement->execute( array(
            ':info_hash'    => $info_hash,
            ':peer_id'      => $peer_id,
            ':now'          => $now->format('Y-m-d H:i:s'),
        ) );

        $row = $statement->fetch();

        return $row;
    }

    /**
     * If the object is used in a forked child process, this method is called after forking.
     *
     * Re-establishes the connection for the fork.
     *
     * @see PHPTracker\Persistence\ResetWhenForking
     */
    public function resetAfterForking()
    {
        // @todo: implement pinging with PDO!
    }
}
