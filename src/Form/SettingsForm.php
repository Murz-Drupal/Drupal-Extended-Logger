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
      '#config_target' => ExtendedLogger::CONFIG_KEY . ':fields',
      // Use the `#default_value` because need a custom preparation of the
      // values from the configuration.
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
      '#config_target' => ExtendedLogger::CONFIG_KEY . ':fields_all',
    ];

    $form['fields_custom'] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel('fields_custom'),
      '#description' => $this->t('A comma separated list of additional fields from the context array to include.'),
      '#config_target' => ExtendedLogger::CONFIG_KEY . ':fields_custom',
      // Use the `#default_value` because need a custom preparation of the
      // values from the configuration.
      '#default_value' => implode(', ', $config->get('fields_custom') ?? []),
      '#states' => [
        'visible' => [
          ':input[name="fields_all"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['service_name'] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel('service_name'),
      '#description' => $this->t('The name of the service to identify the log source.'),
      '#config_target' => ExtendedLogger::CONFIG_KEY . ':service_name',
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
      '#config_target' => ExtendedLogger::CONFIG_KEY . ':target',
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
      '#config_target' => ExtendedLogger::CONFIG_KEY . ':target_syslog_identity',
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
      '#config_target' => ExtendedLogger::CONFIG_KEY . ':target_syslog_facility',
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'syslog'],
        ],
      ],
    ];

    $form['target_file_path'] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel('target_file_path'),
      '#config_target' => ExtendedLogger::CONFIG_KEY . ':target_file_path',
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
      '#config_target' => ExtendedLogger::CONFIG_KEY . ':target_output_stream',
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
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Apply form state values transformation on the validation step, instead of
    // the submit, because ConfigFormBase::validateForm() requires the values to
    // be valid to store in the configuration.
    $fieldSelected = array_values(array_filter($form_state->getValue('fields'), function ($value, $key) {
      return $value != 0;
    }, ARRAY_FILTER_USE_BOTH));
    $form_state->setValue('fields', $fieldSelected);

    $fields_custom = [];
    $fields_customString = $form_state->getValue('fields_custom');
    if (!empty($fields_customString)) {
      $fields_custom = array_map('trim', explode(',', $fields_customString));
    }
    $form_state->setValue('fields_custom', $fields_custom);

    parent::validateForm($form, $form_state);
  }

  /**
   * Returns a list of available syslog facilities.
   *
   * @return array
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
