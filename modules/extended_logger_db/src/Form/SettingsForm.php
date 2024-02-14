<?php

namespace Drupal\extended_logger_db\Form;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\extended_logger\Trait\SettingLabelTrait;
use Drupal\extended_logger_db\ExtendedLoggerDbManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Extended Logger settings for this site.
 */
class SettingsForm extends ConfigFormBase {
  use SettingLabelTrait;

  /**
   * A TypedConfigManager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected TypedConfigManagerInterface $configTyped;

  /**
   * An ExtendedLoggerDbManager.
   *
   * @var \Drupal\extended_logger_db\ExtendedLoggerDbManager
   */
  protected ExtendedLoggerDbManager $extendedLoggerDbManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->configTyped = $container->get('config.typed');
    $instance->extendedLoggerDbManager = $container->get('extended_logger_db.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'extended_logger_db_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [ExtendedLoggerDbManager::CONFIG_KEY];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(ExtendedLoggerDbManager::CONFIG_KEY);
    $this->settingsTyped = $this->configTyped->get(ExtendedLoggerDbManager::CONFIG_KEY);

    $form['cleanup_by_time_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel('cleanup_by_time_enabled'),
      '#description' => $this->t('Enables deleting old log records by time.'),
      '#default_value' => $config->get('cleanup_by_time_enabled'),
    ];

    $form['cleanup_by_time_seconds'] = [
      '#type' => 'select',
      '#title' => $this->getSettingLabel('cleanup_by_time_seconds'),
      '#description' => $this->t('Time range to store.'),
      '#options' => [
        60 * 60 * 24 * 7 => $this->t('1 week'),
        60 * 60 * 24 * 14 => $this->t('2 weeks'),
        60 * 60 * 24 * 28 => $this->t('4 weeks'),
        60 * 60 * 24 * 31 => $this->t('1 month'),
        60 * 60 * 24 * 31 * 3 => $this->t('3 months'),
        60 * 60 * 24 * 31 * 6 => $this->t('6 months'),
        60 * 60 * 24 * 365 => $this->t('1 year'),
      ],
      '#default_value' => $config->get('cleanup_by_time_seconds'),
      '#states' => [
        'visible' => [
          ':input[name="cleanup_by_time_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['cleanup_by_rows_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel('cleanup_by_rows_enabled'),
      '#description' => $this->t('Enables deleting old log records by the total amount of records.'),
      '#default_value' => $config->get('cleanup_by_rows_enabled'),
    ];

    $form['cleanup_by_rows_limit'] = [
      '#type' => 'select',
      '#title' => $this->getSettingLabel('cleanup_by_rows_limit'),
      '#description' => $this->t('Amount of records to store.'),
      '#options' => [
        1_000 => $this->t('@count rows', ['@count' => '1 000']),
        10_000 => $this->t('@count rows', ['@count' => '10 000']),
        100_000 => $this->t('@count rows', ['@count' => '100 000']),
        1_000_000 => $this->t('@count rows', ['@count' => '1 000 000']),
      ],
      '#default_value' => $config->get('cleanup_by_rows_limit'),
      '#states' => [
        'visible' => [
          ':input[name="cleanup_by_rows_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['actions']['cleanup_now'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cleanup now'),
      '#weight' => 10,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(ExtendedLoggerDbManager::CONFIG_KEY)
      ->set('cleanup_by_time_enabled', $form_state->getValue('cleanup_by_time_enabled'))
      ->set('cleanup_by_time_seconds', $form_state->getValue('cleanup_by_time_seconds'))
      ->set('cleanup_by_rows_enabled', $form_state->getValue('cleanup_by_rows_enabled'))
      ->set('cleanup_by_rows_limit', $form_state->getValue('cleanup_by_rows_limit'))
      ->save();
    parent::submitForm($form, $form_state);
    if ($form_state->getTriggeringElement()['#parents'][0] == 'cleanup_now') {
      $this->extendedLoggerDbManager->cleanupDatabase();
      $this->messenger->addMessage('Database logs cleaned up.');
    }

  }

}
