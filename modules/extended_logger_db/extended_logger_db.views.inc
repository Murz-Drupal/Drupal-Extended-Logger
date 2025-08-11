<?php

/**
 * @file
 * Provide views data for the Extended Logger module.
 */

use Drupal\extended_logger_db\ExtendedLoggerDbPersister;

/**
 * Implements hook_views_data().
 */
function extended_logger_db_views_data() {
  $data[ExtendedLoggerDbPersister::DB_TABLE] = [
    'table' => [
      'group' => 'Extended logger',
      'base' => [
        'field' => 'id',
        'title' => t('Extended Logger logs'),
        'help' => t('Stores log entries from the Extended Logger module.'),
      ],
    ],
    'id' => [
      'title' => t('ID'),
      'help' => t('The internal entry id in the database.'),
      'field' => ['id' => 'numeric'],
      'filter' => ['id' => 'numeric'],
      'sort' => ['id' => 'standard'],
    ],
    'severity' => [
      'title' => t('Severity'),
      'help' => t('The severity level of the event; ranges from 0 (Emergency) to 7 (Debug).'),
      'field' => [
        'id' => 'extended_logger_db_severity',
      ],
      'filter' => [
        'id' => 'in_operator',
        'options callback' => 'Drupal\Core\Logger\RfcLogLevel::getLevels',
      ],
      'sort' => ['id' => 'standard'],
    ],
    'channel' => [
      'title' => t('Channel'),
      'help' => t('The channel of the log entry, which can be used to categorize logs.'),
      'argument' => ['id' => 'string'],
      'field' => ['id' => 'standard'],
      'filter' => ['id' => 'extended_logger_db_channels'],
      'sort' => ['id' => 'standard'],
    ],
    'time' => [
      'title' => t('Time'),
      'help' => t('The log entry time, with microsecond precision.'),
      'field' => ['id' => 'extended_logger_db_time'],
      'filter' => ['id' => 'extended_logger_db_time_range'],
      'sort' => ['id' => 'numeric'],
    ],
    'message' => [
      'title' => t('Message'),
      'help' => t('The log message associated with the event. Tokens will be replaced to values from the context.'),
      'argument' => ['id' => 'string'],
      'field' => ['id' => 'standard'],
      'filter' => ['id' => 'string'],
      'sort' => ['id' => 'standard'],
    ],
    'data' => [
      'title' => t('Data'),
      'help' => t('The log data (context) associated with the entry. This fields stores all the context data, passed to the log entry, and can display the whole data or individual fields.'),
      'argument' => ['id' => 'string'],
      'field' => ['id' => 'extended_logger_db_data'],
      'filter' => ['id' => 'string'],
      'sort' => ['id' => 'standard'],
    ],
  ];
  return $data;
}
