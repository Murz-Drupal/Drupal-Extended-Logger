<?php

namespace Drupal\extended_logger_db;

use Drupal\Core\Database\Connection;
use Drupal\extended_logger\ExtendedLoggerEntry;

/**
 * Persist a log entry to the database.
 */
class ExtendedLoggerDbPersister {

  /**
   * The database table name to store logs.
   *
   * @var string
   */
  const DB_TABLE = 'extended_logger_logs';

  /**
   * Constructs the ExtendedLoggerDbPersister object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A database client.
   */
  public function __construct(
    protected Connection $connection,
  ) {
  }

  /**
   * Saves a log entry to the database.
   */
  public function persist(int $severity, ExtendedLoggerEntry $entry) {
    $dateTime = \DateTime::createFromFormat('U.u', microtime(TRUE));
    $data = $entry->getData();
    $channel = $data['channel'];
    $message = $data['message'] ?? $data['message_raw'] ?? '';
    $this->connection->insert(self::DB_TABLE)
      ->fields([
        'time' => $dateTime->format("Y-m-d H:i:s.u"),
        'severity' => $severity,
        'channel' => $channel,
        'message' => $message,
        'data' => json_encode($data),
      ])
      ->execute();
  }

}
