<?php

namespace Drupal\extended_logger\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\extended_logger\ExtendedLoggerEntry;

/**
 * Event that is fired before a new log entry is being persisted.
 */
class ExtendedLoggerLogEvent extends Event {

  /**
   * The log entry item - a stuctured associative array with values.
   *
   * @var array
   */
  public ExtendedLoggerEntry $entry;

  /**
   * Constructs the object.
   *
   * @param \Drupal\extended_logger\ExtendedLoggerEntry $entry
   *   An ExtendedLoggerEntry object with the log entry data.
   */
  public function __construct(ExtendedLoggerEntry $entry) {
    $this->entry = $entry;
  }

}
