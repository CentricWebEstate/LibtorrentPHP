<?php

namespace PHPTracker\File;

use PHPTracker\File\Error\UnreadableError;
use PHPTracker\File\Error\NotExistsError;

/**
 * Object to process to a physical file in the file system in regards og
 * torrent file generation and serving.
 *
 * @package PHPTracker
 * @subpackage File
 */
class File
{
    /**
     * Full path of the file on the disk.
     *
     * @var string
     */
    private $path;

    /**
     * If the file os opened for reading, this contains its read handle.
     *
     * @var resource
     */
    private $read_handle;

    /**
     * Initializing the object with the file full path.
     *
     * @throws NotExitsError If the file does not exists.
     * @param string $path
     */
    public function  __construct( $path )
    {
        $this->path = (string) $path;
        $this->shouldExist();

        $this->path = realpath( $this->path );
    }

    /**
     * Return the file full path is the object is used as string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->path;
    }

    /**
     * If the file is open for reading, it's properly closed while destructing.
     */
    public function  __destruct()
    {
        if ( isset( $this->read_handle ) )
        {
            fclose( $this->read_handle );
        }
    }

    /**
     * Tells if the file exists.
     *
     * @return boolean
     */
    private function exists()
    {
        return file_exists( $this->path );
    }

    /**
     * Tells the size of the file in bytes.
     *
     * @return integer
     */
    public function size()
    {
        if ( false === ( $size = @filesize( $this->path ) ) )
        {
            throw new UnreadableError( "File $this is unreadable." );
        }
        return $size;
    }

    /**
     * Returns the basename of the file.
     *
     * @return string
     */
    public function basename()
    {
        return basename( $this->path );
    }

    /**
     * Generates SHA1 hashes of each piece of the file.
     *
     * @param integer $size_piece Size of one piece of a file on bytes.
     * @return string Byte string of the concatenated SHA1 hashes of each pieces.
     */
    public function getHashesForPieces( $size_piece )
    {
        $size_piece = intval( $size_piece );
        if ( $size_piece <= 0 )
        {
            // TODO: Throwing exception?
            return null;
        }

        $c_pieces = ceil( $this->size() / $size_piece );
        $hashes = '';

        for ( $n_piece = 0; $n_piece < $c_pieces; ++$n_piece )
        {
            $hashes .= $this->hashPiece( $n_piece, $size_piece );
        }

        return $hashes;
    }

    /**
     * Reads one arbitrary length chunk of a file beginning from a byte index.
     *
     * @param integer $begin Where to start reading (bytes).
     * @param integer $length How many bytes to read.
     * @return string Binary string with the read data.
     */
    public function readBlock( $begin, $length )
    {
        $file_handle = $this->getReadHandle();

        fseek( $file_handle, $begin );
        if ( false === $buffer = @fread( $file_handle, $length ) )
        {
            throw new UnreadableError( "File $this is unreadable." );
        }

        // TODO: Check if we could read enough data.

        return $buffer;
    }

    /**
     * Lazy-opens a file for reading and returns its resource.
     *
     * @throws UnreadableError If the file can't be read.
     * @return resource
     */
    private function getReadHandle()
    {
        if ( !isset( $this->read_handle ) )
        {
            $this->read_handle = @fopen( $this->path, 'rb' );

            if ( false === $this->read_handle )
            {
                unset( $this->read_handle );
                throw new UnreadableError( "File $this is unreadable." );
            }

        }
        return $this->read_handle;
    }

    /**
     * Gets SHA1 hash of a piece of a file in raw format.
     *
     * @param integer $n_piece 0 bases index of the current peice.
     * @param integer $size_piece Generic piece size of the file in bytes.
     * @return string Byte string of the SHA1 hash of this piece.
     */
    private function hashPiece( $n_piece, $size_piece )
    {
        $file_handle = $this->getReadHandle();
        $hash_handle = hash_init( 'sha1' );

        fseek( $file_handle, $n_piece * $size_piece );
        hash_update_stream( $hash_handle, $file_handle, $size_piece );

        // Getting hash of the piece as raw binary.
        return hash_final( $hash_handle, true );
    }

    /**
     * Throws exception if the file does not exist.
     *
     * @throws NotExitsError
     */
    private function shouldExist()
    {
        if ( !$this->exists() )
        {
            throw new NotExistsError( "File $this does not exist." );
        }
    }
}
