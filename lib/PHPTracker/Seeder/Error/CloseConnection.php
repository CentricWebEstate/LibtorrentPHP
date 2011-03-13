<?php

namespace PHPTracker\Seeder\Error;

use PHPTracker\Seeder\Error;

/**
 * Exception thrown to close the connection to a connected client.
 * Used to control flow, has to be caught.
 *
 * @package PHPTracker
 * @subpackage Seeder
 */
class CloseConnection extends Error
{
}
