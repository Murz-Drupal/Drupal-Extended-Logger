<?php

namespace Drupal\extended_logger\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\extended_logger\ExtendedLoggerEntry;

/**
 * Event that is fired before a new log entry is being persisted.
 */
class ExtendedLoggerLogEvent extends Event {

  /**
   * Constructs the object.
   *
   * @param \Drupal\extended_logger\ExtendedLoggerEntry $entry
   *   The ExtendedLoggerEntry object with the log entry data.
   * @param mixed $level
   *   The log level.
   * @param mixed $message
   *   The log message.
   * @param array $context
   *   The log context array.
   */
  public function __construct(
    public ExtendedLoggerEntry $entry,
    public $level,
    public $message,
    public array $context,
  ) {
  }

}
