<?php

namespace Drupal\extended_logger\Form;

use Drupal\Core\Config\Schema\Undefined;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\extended_logger\Logger\ExtendedLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Extended Logger settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The typed Extended Logger settings.
   *
   * @var \Drupal\Core\Config\Schema\Mapping|\Drupal\Core\Config\Schema\Undefined
   */
  private $settingsTyped;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected TypedConfigManagerInterface $configTyped,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.typed'),
    );
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

    $form['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->getSettingLabel('fields'),
      '#description' => $this->t('Enable fields which should be present in the log entry.'),
      '#options' => [],
      '#default_value' => $config->get('fields') ?? [],
    ];
    foreach (ExtendedLogger::LOGGER_FIELDS as $field => $description) {
      $form['fields']['#options'][$field] = "<code>$field</code> - " . $this->t($description);
    }

    $form['fieldsCustom'] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel('fields'),
      '#description' => 'A comma separated list of additional fields from context array to include.',
      '#default_value' => implode(',', $config->get('fieldsCustom') ?? []),
    ];

    $form['target'] = [
      '#type' => 'radios',
      '#title' => $this->getSettingLabel('target'),
      '#options' => [
        'syslog' => $this->t('Syslog'),
        'file' => $this->t('File'),
        'output' => $this->t('Output'),
        'null' => $this->t('Null (no logging)'),
      ],
      '#default_value' => $config->get('target') ?? 'syslog',
    ];

    $form['targetSyslogIdentity'] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel('targetSyslogIdentity'),
      '#description' => $this->t(' A string that will be prepended to every message logged to Syslog. If you have multiple sites logging to the same Syslog log file, a unique identity per site makes it easy to tell the log entries apart.'),
      '#default_value' => $config->get('targetSyslogIdentity') ?? 'drupal',
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'syslog'],
        ],
      ],
    ];
    $form['targetSyslogFacility'] = [
      '#type' => 'select',
      '#title' => $this->getSettingLabel('targetSyslogIdentity'),
      '#options' => $this->syslogFacilityList(),
      '#description' => $this->t('Depending on the system configuration, Syslog and other logging tools use this code to identify or filter messages from within the entire system log.'),
      '#default_value' => $config->get('targetSyslogFacility') ?? LOG_LOCAL0,
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'syslog'],
        ],
      ],
    ];

    $form['targetFilePath'] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel('targetFilePath'),
      '#default_value' => $config->get('targetFilePath') ?? '/tmp/drupal.log',
      '#states' => [
        'visible' => [
          ':input[name="target"]' => ['value' => 'file'],
        ],
      ],
    ];

    $form['targetOutputStream'] = [
      '#type' => 'radios',
      '#title' => $this->getSettingLabel('targetFilePath'),
      '#options' => [
        'stdout' => $this->t('stdout'),
        'stederr' => $this->t('stederr'),
      ],
      '#default_value' => $config->get('targetOutputStream') ?? 'stdout',
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
    $fieldsCustom = [];
    $fieldsCustomString = $form_state->getValue('fieldsCustom');
    if (!empty($fieldsCustomString)) {
      $fieldsCustom = array_map('trim', explode(',', $fieldsCustomString));
    }

    $this->config(ExtendedLogger::CONFIG_KEY)
      ->set('fields', $form_state->getValue('fields'))
      ->set('fieldsCustom', $fieldsCustom)
      ->set('target', $form_state->getValue('target'))
      ->set('targetSyslogIdentity', $form_state->getValue('targetSyslogIdentity'))
      ->set('targetSyslogFacility', $form_state->getValue('targetSyslogFacility'))
      ->set('targetFilePath', $form_state->getValue('targetFilePath'))
      ->set('targetOutputStream', $form_state->getValue('targetOutputStream'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Returns a list of available syslog faciliies.
   *
   * @return array<string>
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

  /**
   * Gets the label for a setting from typed settings object.
   */
  private function getSettingLabel(string $key, ?string $fallback = NULL): string {
    $setting = $this->settingsTyped->get($key);
    if ($setting instanceof Undefined) {
      $label = $fallback ?: "[$key]";
    }
    else {
      $label = $setting->getDataDefinition()->getLabel();
    }
    return $label;
  }

}
