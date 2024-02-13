<?php

namespace Drupal\extended_logger_db\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\extended_logger_db\ExtendedLoggerDbPersister;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes log channels to views module.
 *
 * @ViewsFilter("extended_logger_db_channels")
 */
class ExtendedLoggerDbChannels extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * A database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = $this->getChannels();
    }
    return $this->valueOptions;
  }

  /**
   * Gathers a list of channels present in the logs table.
   *
   * @return array
   *   List of uniquely defined log channels.
   */
  public function getChannels(): array {
    return $this->connection->select(ExtendedLoggerDbPersister::DB_TABLE)
      ->fields(ExtendedLoggerDbPersister::DB_TABLE, ['channel'])
      ->distinct()
      ->orderBy('channel')
      ->execute()
      ->fetchAllKeyed(0, 0);
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value']['#access'] = !empty($form['value']['#options']);
  }

}
