<?php

namespace Drupal\extended_logger_db\Form;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\extended_logger\Trait\SettingLabelTrait;
use Drupal\extended_logger_db\ExtendedLoggerDbManager;
use Drupal\extended_logger_db\ExtendedLoggerDbUtils;
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
   * An ExtendedLoggerDbUtils.
   *
   * @var \Drupal\extended_logger_db\ExtendedLoggerDbUtils
   */
  protected ExtendedLoggerDbUtils $extendedLoggerDbUtils;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->configTyped = $container->get('config.typed');
    $instance->extendedLoggerDbManager = $container->get('extended_logger_db.manager');
    $instance->extendedLoggerDbUtils = $container->get(ExtendedLoggerDbUtils::class);
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
    $this->settingsTyped = $this->configTyped->get(ExtendedLoggerDbManager::CONFIG_KEY);

    $form['logs_view_page'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Logs View Page'),
    ];

    $form['logs_view_page']['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('<a href="/admin/reports/extended-logs">Open the Logs View Page</a>. You can customize the columns and other settings on the views edit page <a href="/admin/structure/views/view/extended_logger_logs/edit">here</a>.'),
    ];
    $form['logs_view_page']['reset'] = [
      '#type' => 'details',
      '#title' => $this->t('Reset the Logs page configuration to defaults'),
      '#description' => $this->t('Use the button below to reset your changes back to defaults. Warning: it will remove all customizations of the log page and reset the page to the default settings.'),
      '#open' => FALSE,
    ];
    $form['logs_view_page']['reset']['reset_logs_view_page'] = [
      '#type' => 'submit',
      '#name' => 'reset_logs_view_page',
      '#value' => $this->t('Reset Logs View Page settings to defaults'),
      '#attributes' => [
        'class' => ['button--danger'],
      ],
    ];

    $form['cleanup'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cleanup rules'),
      '#description' => $this->t('Old log entries cleans up automatically by cron. Here you can configure cleanup rules.'),
      '#description_display' => 'before',
    ];
    $form['cleanup'][ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_TIME_ENABLED] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_TIME_ENABLED),
      '#description' => $this->t('Enables deleting old log records by time.'),
      '#config_target' => ExtendedLoggerDbManager::CONFIG_KEY . ':' . ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_TIME_ENABLED,
    ];

    $form['cleanup'][ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_TIME_SECONDS] = [
      '#type' => 'select',
      '#title' => $this->getSettingLabel(ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_TIME_SECONDS),
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
      '#config_target' => ExtendedLoggerDbManager::CONFIG_KEY . ':' . ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_TIME_SECONDS,
      '#states' => [
        'visible' => [
          ':input[name="' . ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_TIME_ENABLED . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['cleanup'][ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_ROWS_ENABLED] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_ROWS_ENABLED),
      '#description' => $this->t('Enables deleting old log records by the total amount of records.'),
      '#config_target' => ExtendedLoggerDbManager::CONFIG_KEY . ':' . ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_ROWS_ENABLED,
    ];

    $form['cleanup'][ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_ROWS_LIMIT] = [
      '#type' => 'select',
      '#title' => $this->getSettingLabel(ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_ROWS_LIMIT),
      '#description' => $this->t('Amount of records to store.'),
      '#options' => [
        1_000 => $this->t('@count rows', ['@count' => '1 000']),
        10_000 => $this->t('@count rows', ['@count' => '10 000']),
        100_000 => $this->t('@count rows', ['@count' => '100 000']),
        1_000_000 => $this->t('@count rows', ['@count' => '1 000 000']),
      ],
      '#config_target' => ExtendedLoggerDbManager::CONFIG_KEY . ':' . ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_ROWS_LIMIT,
      '#states' => [
        'visible' => [
          ':input[name="' . ExtendedLoggerDbManager::CONFIG_KEY_CLEANUP_BY_ROWS_ENABLED . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['cleanup']['cleanup_now'] = [
      '#type' => 'submit',
      '#name' => 'cleanup_now',
      '#value' => $this->t('Cleanup now'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $triggeringElement = $form_state->getTriggeringElement();
    switch ($triggeringElement['#name']) {
      case 'cleanup_now':
        $this->extendedLoggerDbManager->cleanupDatabase();
        $this->messenger()->addMessage($this->t('Database logs cleaned up.'));
        break;

      case 'reset_logs_view_page':
        $this->extendedLoggerDbUtils->resetView();
        $this->messenger()->addMessage($this->t('The <a href="/admin/reports/extended-logs">Logs view page</a> settings restored to defaults.'));
        break;
    }
  }

}
