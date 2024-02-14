<?php

namespace Drupal\extended_logger_db;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Persist a log entry to the database.
 */
class ExtendedLoggerDbManager {

  /**
   * The module main configuration key.
   *
   * @var string
   */
  const CONFIG_KEY = 'extended_logger_db.settings';

  /**
   * Constructs the ExtendedLoggerDbPersister object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A database client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   A config factory.
   */
  public function __construct(
    protected Connection $connection,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Cleanups expired entries from the logs database table.
   */
  public function cleanupDatabase() {
    $config = $this->configFactory->get(self::CONFIG_KEY);
    if ($config->get('cleanup_by_time_enabled')) {
      $seconds = $config->get('cleanup_by_time_seconds');
      if ($seconds > 0) {
        $this->connection->delete(ExtendedLoggerDbPersister::DB_TABLE)
          ->where("time < DATE_SUB(NOW(6), INTERVAL $seconds SECOND)")
          ->execute();
      }
    }
    if ($config->get('cleanup_by_rows_enabled')) {
      $rows = $config->get('cleanup_by_rows_limit');
      if ($rows > 0) {
        $minRowId = $this->connection->select(ExtendedLoggerDbPersister::DB_TABLE, 'l')
          ->fields('l', ['id'])
          ->orderBy('id', 'DESC')
          ->range($rows - 1, 1)
          ->execute()
          ->fetchField();

        // Delete table entries older than the nth row, if nth row was found.
        if ($minRowId) {
          $this->connection->delete(ExtendedLoggerDbPersister::DB_TABLE)
            ->condition('id', $minRowId, '<')
            ->execute();
        }
      }
    }
  }

}
