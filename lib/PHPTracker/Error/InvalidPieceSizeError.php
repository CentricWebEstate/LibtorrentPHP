<?php

namespace PHPTracker\Error;

/**
 * Exception thrown when Torrent object is created with invalid piece size.
 *
 * @package PHPTracker
 */
class InvalidPieceSizeError extends \LogicException
{
}