<?php

namespace Drupal\extended_logger\Form;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\extended_logger\Logger\ExtendedLogger;
use Drupal\extended_logger\Trait\SettingLabelTrait;
use Drupal\extended_logger_db\ExtendedLoggerDbPersister;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->configTyped = $container->get('config.typed');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'extended_logger_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [ExtendedLogger::CONFIG_KEY];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(ExtendedLogger::CONFIG_KEY);
    $this->settingsTyped = $this->configTyped->get(ExtendedLogger::CONFIG_KEY);

    $enabledFields = $config->get('fields') ?? [];

    $form['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->getSettingLabel('fields'),
      '#description' => $this->t('Enable fields which should be present in the log entry.'),
      '#options' => [],
      '#default_value' => array_merge($enabledFields, $enabledFields),
    ];
    foreach (ExtendedLogger::LOGGER_FIELDS as $field => $description) {
      // Use ignore till the https://www.drupal.org/project/coder/issues/3326197
      // is fixed.
      // @codingStandardsIgnoreStart
      $form['fields']['#options'][$field] = "<code>$field</code> - " . $this->t($description);
      // @codingStandardsIgnoreEnd
    }

    $form['fields_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel('fields_all'),
      '#description' => $this->t('Enables adding all fields from the context array to the log entries.'),
      '#default_value' => $config->get('fields_all') ?? FALSE,
    ];

    $form['fields_custom'] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel('fields_custom'),
      '#description' => $this->t('A comma separated list of additional fields from the context array to include.'),
      '#default_value' => implode(', ', $config->get('fields_custom') ?? []),
      '#states' => [
        'visible' => [
          ':input[name="fields_all"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['target'] = [
      '#type' => 'radios',
      '#title' => $this->getSettingLabel('target'),
      '#options' => [
        'syslog' => $this->t('Syslog'),
        'file' => $this->t('File'),
        'output' => $this->t('Output'),
        'database' => $this->t('Database'),
        'none' => $this->t('None'),
      ],
      '#default_value' => $config->get('target') ?? 'syslog',
    ];
    $form['target']['syslog']['#description'] = $this->t('Persists to a syslog daemon. Requires syslog daemon to be available.');
    $form['target']['file']['#description'] = $this->t('Writes log to a file. Not recommended for production.');
    $form['target']['database']['#description'] = $this->t('Persists into the database. Not recommended for production.');
    $form['target']['output']['#description'] = $this->t('Outputs to stdout or stderr.');
    $form['target']['none']['#description'] = $this->t('Disables internal persisting of logs. Useful with modules that stores log entries by their own.');

    if (!class_exists(ExtendedLoggerDbPersister::class)) {
      $form['target']['database']['#disabled'] = TRUE;
      $form['target']['database']['#description'] .=
        ' ' . $this->t('Requires Extended Logger DB module to be enabled.');
    }

    $form['target_syslog_identity'] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel('target_syslog_identity'),
      '#description' => $this->t('A string that will be prepended to every message logged to Syslog. If you have multiple sites logging to the same Syslog log file, a unique identity per site makes it easy to tell the log entries apart.'),
      '#default_value' => $config->get('target_syslog_identity') ?? 'drupal',
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'syslog'],
        ],
      ],
    ];
    $form['target_syslog_facility'] = [
      '#type' => 'select',
      '#title' => $this->getSettingLabel('target_syslog_identity'),
      '#options' => $this->syslogFacilityList(),
      '#description' => $this->t('Depending on the system configuration, Syslog and other logging tools use this code to identify or filter messages from within the entire system log.'),
      '#default_value' => $config->get('target_syslog_facility') ?? LOG_LOCAL0,
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'syslog'],
        ],
      ],
    ];

    $form['target_file_path'] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel('target_file_path'),
      '#default_value' => $config->get('target_file_path'),
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'file'],
        ],
      ],
    ];

    $form['target_output_stream'] = [
      '#type' => 'radios',
      '#title' => $this->getSettingLabel('target_output_stream'),
      '#options' => [
        'stdout' => $this->t('stdout'),
        'stderr' => $this->t('stderr'),
      ],
      '#default_value' => $config->get('target_output_stream') ?? 'stdout',
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'output'],
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fields_custom = [];
    $fields_customString = $form_state->getValue('fields_custom');
    if (!empty($fields_customString)) {
      $fields_custom = array_map('trim', explode(',', $fields_customString));
    }

    $fieldSelected = array_values(array_filter($form_state->getValue('fields'), function ($value, $key) {
      return $value != 0;
    }, ARRAY_FILTER_USE_BOTH));

    $this->config(ExtendedLogger::CONFIG_KEY)
      ->set('fields', $fieldSelected)
      ->set('fields_all', $form_state->getValue('fields_all'))
      ->set('fields_custom', $fields_custom)
      ->set('target', $form_state->getValue('target'))
      ->set('target_syslog_identity', $form_state->getValue('target_syslog_identity'))
      ->set('target_syslog_facility', $form_state->getValue('target_syslog_facility'))
      ->set('target_file_path', $form_state->getValue('target_file_path'))
      ->set('target_output_stream', $form_state->getValue('target_output_stream'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Returns a list of available syslog faciliies.
   *
   * @return arraystring
   *   A list with a numeric key and a string value of the each facility.
   */
  protected function syslogFacilityList() {
    return [
      LOG_USER => 'LOG_USER',
      LOG_LOCAL0 => 'LOG_LOCAL0',
      LOG_LOCAL1 => 'LOG_LOCAL1',
      LOG_LOCAL2 => 'LOG_LOCAL2',
      LOG_LOCAL3 => 'LOG_LOCAL3',
      LOG_LOCAL4 => 'LOG_LOCAL4',
      LOG_LOCAL5 => 'LOG_LOCAL5',
      LOG_LOCAL6 => 'LOG_LOCAL6',
      LOG_LOCAL7 => 'LOG_LOCAL7',
    ];
  }

}
