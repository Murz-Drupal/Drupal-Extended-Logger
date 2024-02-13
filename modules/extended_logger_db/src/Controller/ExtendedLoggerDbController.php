<?php

namespace Drupal\extended_logger_db\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\extended_logger_db\ExtendedLoggerDbPersister;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for our pages.
 */
class ExtendedLoggerDbController extends ControllerBase {
  /**
   * A database client.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * Displays values of a specific log entry by the entry id.
   *
   * @param int $entry_id
   *   The entry id.
   *
   * @return array
   *   The render array.
   */
  public function entryPage(int $entry_id) {
    $build['entry'] = [
      '#type' => 'table',
      '#header' => [
        'field' => $this->t('Field'),
        'value' => $this->t('Value'),
      ],
    ];
    $entry = $this->getEntry($entry_id);
    foreach ($entry as $field => $value) {
      if ($field == 'data') {
        $value = json_encode(json_decode($value), JSON_PRETTY_PRINT);
        $value = ['data' => ['#markup' => '<pre>' . $value . '</pre>']];
      }
      $build['entry']['#rows'][] = [$field, $value];
    }
    return $build;
  }

  /**
   * Gets a log entry from the database by the entry id.
   *
   * @param int $entry_id
   *   The entry id.
   *
   * @return array|bool
   *   An array with the entry values.
   */
  public function getEntry(int $entry_id) {
    $entry = $this->connection->select(ExtendedLoggerDbPersister::DB_TABLE)
      ->fields(ExtendedLoggerDbPersister::DB_TABLE, [
        'id',
        'time',
        'severity',
        'channel',
        'message',
        'data',
      ])
      ->condition('id', $entry_id)
      ->execute()
      ->fetchAssoc();
    return $entry;
  }

  /**
   * Generates the entry title by the entry id.
   *
   * @param int $entry_id
   *   The entry id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The entry title markup.
   */
  public function getEntryTitle(int $entry_id) {
    return $this->t('Log entry @id', ['@id' => $entry_id]);
  }

}
