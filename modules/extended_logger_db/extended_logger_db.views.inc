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
        'help' => t('Stores log entries from the Extened Logger module.'),
      ],
    ],
    'id' => [
      'title' => t('Id'),
      'field' => ['id' => 'numeric'],
      'filter' => ['id' => 'numeric'],
      'sort' => ['id' => 'standard'],
    ],
    'severity' => [
      'title' => t('Severity'),
      'help' => t('The severity level of the event; ranges from 0 (Emergency) to 7 (Debug).'),
      'field' => [
        'id' => 'extended_logger_severity',
      ],
      'filter' => [
        'id' => 'in_operator',
        'options callback' => 'Drupal\Core\Logger\RfcLogLevel::getLevels',
      ],
      'sort' => ['id' => 'standard'],
    ],
    'channel' => [
      'title' => t('Channel'),
      'argument' => ['id' => 'string'],
      'field' => ['id' => 'standard'],
      'filter' => ['id' => 'extended_logger_db_channels'],
      'sort' => ['id' => 'standard'],
    ],
    'time' => [
      'title' => t('Time'),
      'field' => ['id' => 'extended_logger_time'],
      'filter' => ['id' => 'standard'],
      'sort' => ['id' => 'numeric'],
    ],
    'message' => [
      'title' => t('Message'),
      'argument' => ['id' => 'string'],
      'field' => ['id' => 'standard'],
      'filter' => ['id' => 'string'],
      'sort' => ['id' => 'standard'],
    ],
    'data' => [
      'title' => t('Data'),
      'argument' => ['id' => 'string'],
      'field' => ['id' => 'extended_logger_data'],
      'filter' => ['id' => 'string'],
      'sort' => ['id' => 'standard'],
    ],
  ];
  return $data;
}
