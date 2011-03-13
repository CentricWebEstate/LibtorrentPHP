<?php

namespace PHPTracker\Error;

/**
 * Exception thrown when a Torrent object can't read the underlying file(s).
 *
 * @package PHPTracker
 */
class BlockReadError extends \RuntimeException
{
}