<?php

namespace Drupal\extended_logger\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when a user logs in.
 */
class ExtendedLoggerLogEvent extends Event {

  /**
   * The event unique name.
   * @var string
   */
  const EVENT_NAME = 'extended_logger_log';

  /**
   * The log record.
   *
   * @var array
   */
  public $record;

  /**
   * Constructs the object.
   *
   * @param array $record
   *   An array with the log record data.
   */
  public function __construct(array $record) {
    $this->record = $record;
  }

}
