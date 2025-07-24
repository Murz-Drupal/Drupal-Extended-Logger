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
    return [ExtendedLogger::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(ExtendedLogger::CONFIG_NAME);
    $this->settingsTyped = $this->configTyped->get(ExtendedLogger::CONFIG_NAME);

    $enabledFields = $config->get(ExtendedLogger::CONFIG_KEY_FIELDS) ?? [];

    $form[ExtendedLogger::CONFIG_KEY_FIELDS] = [
      '#type' => 'checkboxes',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_FIELDS),
      '#description' => $this->t('Enable fields which should be present in the log entry.'),
      '#options' => [],
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_FIELDS,
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

    $form[ExtendedLogger::CONFIG_KEY_FIELDS_ALL] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_FIELDS_ALL),
      '#description' => $this->t('Enables adding all fields from the context array to the log entries.'),
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_FIELDS_ALL,
    ];

    $form[ExtendedLogger::CONFIG_KEY_FIELDS_CUSTOM] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_FIELDS_CUSTOM),
      '#description' => $this->t('A comma separated list of additional fields from the context array to include.'),
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_FIELDS_CUSTOM,
      // Use the `#default_value` because need a custom preparation of the
      // values from the configuration.
      '#default_value' => implode(', ', $config->get(ExtendedLogger::CONFIG_KEY_FIELDS_CUSTOM) ?? []),
      '#states' => [
        'visible' => [
          ':input[name="fields_all"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form[ExtendedLogger::CONFIG_KEY_TARGET] = [
      '#type' => 'radios',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_TARGET),
      '#options' => [
        'output' => $this->t('Output'),
        'file' => $this->t('File'),
        'syslog' => $this->t('Syslog'),
        'database' => $this->t('Database'),
        'none' => $this->t('None'),
      ],
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_TARGET,
    ];
    $form[ExtendedLogger::CONFIG_KEY_TARGET]['syslog']['#description'] = $this->t('Persists to a syslog daemon. Requires syslog daemon to be available.');
    $form[ExtendedLogger::CONFIG_KEY_TARGET]['file']['#description'] = $this->t('Writes log to a file. Not recommended for production.');
    $form[ExtendedLogger::CONFIG_KEY_TARGET]['database']['#description'] = $this->t('Persists into the database. Not recommended for production.');
    $form[ExtendedLogger::CONFIG_KEY_TARGET]['output']['#description'] = $this->t('Outputs to stdout or stderr.');
    $form[ExtendedLogger::CONFIG_KEY_TARGET]['none']['#description'] = $this->t('Disables internal persisting of logs. Useful when log are processed by other modules.');

    if (!class_exists(ExtendedLoggerDbPersister::class)) {
      $form[ExtendedLogger::CONFIG_KEY_TARGET]['database']['#disabled'] = TRUE;
      $form[ExtendedLogger::CONFIG_KEY_TARGET]['database']['#description'] .=
        ' ' . $this->t('Requires "Extended Logger DB" module to be enabled.');
    }

    $form[ExtendedLogger::CONFIG_KEY_TARGET_SYSLOG_IDENTITY] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_TARGET_SYSLOG_IDENTITY),
      '#description' => $this->t('A string that will be prepended to every message logged to Syslog. If you have multiple sites logging to the same Syslog log file, a unique identity per site makes it easy to tell the log entries apart.'),
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_TARGET_SYSLOG_IDENTITY,
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'syslog'],
        ],
      ],
    ];
    $form[ExtendedLogger::CONFIG_KEY_TARGET_SYSLOG_FACILITY] = [
      '#type' => 'select',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_TARGET_SYSLOG_FACILITY),
      '#options' => $this->syslogFacilityList(),
      '#description' => $this->t('Depending on the system configuration, Syslog and other logging tools use this code to identify or filter messages from within the entire system log.'),
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_TARGET_SYSLOG_FACILITY,
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'syslog'],
        ],
      ],
    ];

    $form[ExtendedLogger::CONFIG_KEY_TARGET_FILE_PATH] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_TARGET_FILE_PATH),
      '#description' => $this->t('Put an absolute or relative path to the log file. Relative path will be resolved to the Drupal root. You can use Drupal stream wrappers, for example, <code>temporary://drupal.log</code> or <code>private://log.jsonl</code>.'),
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_TARGET_FILE_PATH,
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'file'],
        ],
      ],
    ];

    $form[ExtendedLogger::CONFIG_KEY_TARGET_OUTPUT_STREAM] = [
      '#type' => 'radios',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_TARGET_OUTPUT_STREAM),
      '#options' => [
        'stderr' => $this->t('stderr (recommended)'),
        'stdout' => $this->t('stdout'),
      ],
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_TARGET_OUTPUT_STREAM,
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'output'],
        ],
      ],
      '#description' => $this->t('Choose the output stream for the logs. The <code>stderr</code> is recommended to not mix the standard output with logs, for example, in drush commands.'),
    ];

    $form[ExtendedLogger::CONFIG_KEY_SERVICE_NAME] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_SERVICE_NAME),
      '#description' => $this->t('The name of the service to identify the log source.'),
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_SERVICE_NAME,
    ];

    $form[ExtendedLogger::CONFIG_KEY_LOG_LINE_MAX_LENGTH] = [
      '#type' => 'number',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_LOG_LINE_MAX_LENGTH),
      '#description' => $this->t('The maximum length of a log line. Put a value more than 255, or keep empty to allow any length. Syslog, Docker and some other log receivers pretty often have max line length limits and cut long JSON output producing invalid JSON lines. This usually can be resolved by configuring the receiver configuration. But the module provides a workaround from the Drupal side by cutting the exceeding part of JSON with fixing unclosed brackets and other broken parts. Common limits: Docker: 16384, Syslog: 2048.'),
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_LOG_LINE_MAX_LENGTH,
      '#states' => [
        'visible' => [
          [':input[name="target"]' => ['value' => 'output']],
          [':input[name="target"]' => ['value' => 'file']],
          [':input[name="target"]' => ['value' => 'syslog']],
        ],
      ],
    ];

    $form[ExtendedLogger::CONFIG_KEY_BACKLOG_ITEMS_LIMIT] = [
      '#type' => 'number',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_BACKLOG_ITEMS_LIMIT),
      '#description' => $this->t('The maximum number of backtrace items to log. Set to empty to disable the limit. Usually the backtrace contains a lot of items, and it is not useful to log all of them. The default value is 8.'),
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_BACKLOG_ITEMS_LIMIT,
    ];

    $form[ExtendedLogger::CONFIG_KEY_SKIP_EVENT_DISPATCH] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(ExtendedLogger::CONFIG_KEY_SKIP_EVENT_DISPATCH),
      '#description' => $this->t('If checked, the module will not dispatch events for log entries. This is useful for performance reasons if you do not have any subscribers for the log entry.'),
      '#config_target' => ExtendedLogger::CONFIG_NAME . ':' . ExtendedLogger::CONFIG_KEY_SKIP_EVENT_DISPATCH,
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
    $fieldSelected = array_values(array_filter($form_state->getValue(ExtendedLogger::CONFIG_KEY_FIELDS), function ($value, $key) {
      return $value != 0;
    }, ARRAY_FILTER_USE_BOTH));
    $form_state->setValue(ExtendedLogger::CONFIG_KEY_FIELDS, $fieldSelected);

    $fields_custom = [];
    $fields_customString = $form_state->getValue(ExtendedLogger::CONFIG_KEY_FIELDS_CUSTOM);
    if (!empty($fields_customString)) {
      $fields_custom = array_map('trim', explode(',', $fields_customString));
    }
    $form_state->setValue(ExtendedLogger::CONFIG_KEY_FIELDS_CUSTOM, $fields_custom);

    if ($form_state->getValue(ExtendedLogger::CONFIG_KEY_LOG_LINE_MAX_LENGTH) <= 0) {
      $form_state->setValue(ExtendedLogger::CONFIG_KEY_LOG_LINE_MAX_LENGTH, NULL);
    }

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
