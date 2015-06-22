<?php

namespace CWE\Libraries\LibtorrentPHP\Error;

/**
 * Exception thrown when trying to create a Torrent with no announceURL list.
 *
 * @package PHPTracker
 */
class EmptyAnnounceListError extends \RuntimeException
{
}
