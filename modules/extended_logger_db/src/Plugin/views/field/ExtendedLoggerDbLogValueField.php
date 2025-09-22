<?php

namespace Drupal\extended_logger_db\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Component\Serialization\Yaml;
use Drupal\extended_logger_db\Plugin\views\JsonValuePathFunctionsTrait;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\Standard;
use Drupal\views\ResultRow;

/**
 * Provides a field handler that renders a log severity.
 */
#[ViewsField("extended_logger_db_log_value")]
class ExtendedLoggerDbLogValueField extends Standard {


  use JsonValuePathFunctionsTrait {
    buildOptionsForm as protected traitBuildOptionsForm;
    defineOptions as protected traitDefineOptions;
  }

  // Display format constants.
  private const DISPLAY_FORMAT_YAML = 'YAML';
  private const DISPLAY_FORMAT_JSON_PRETTY = 'JSON_PRETTY';
  private const DISPLAY_FORMAT_JSON = 'JSON';
  private const DISPLAY_FORMAT_LIST = 'LIST';

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = self::traitDefineOptions();
    $options['value_format'] = [
      'default' => '',
    ];
    $options['display_format'] = [
      'default' => self::DISPLAY_FORMAT_YAML,
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form = self::traitBuildOptionsForm($form, $form_state);
    $form['value_format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Value Format'),
      '#description' => $this->t('Allows casting the value to a specific format.'),
      '#options' => [
        self::$valueFormatRaw => $this->t('Raw'),
        self::$valueFormatString => $this->t('String'),
        self::$valueFormatNumeric => $this->t('Numeric'),
      ],
      '#default_value' => $this->options['value_format'],
      '#weight' => -1,
    ];
    $form['display_format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Object Display Format'),
      '#description' => $this->t('Choose how the object value will be displayed.'),
      '#options' => [
        self::DISPLAY_FORMAT_YAML => $this->t('YAML'),
        self::DISPLAY_FORMAT_JSON_PRETTY => $this->t('JSON pretty-printed'),
        self::DISPLAY_FORMAT_JSON => $this->t('JSON'),
        self::DISPLAY_FORMAT_LIST => $this->t('List of values'),
      ],
      '#default_value' => $this->options['display_format'],
      '#states' => [
        'visible' => [
          ':input[name="options[value_format]"]' => ['value' => self::$valueFormatRaw],
        ],
      ],
      '#required' => TRUE,
      '#weight' => -1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    if (
      $this->options['value_path'] === self::$valueFormatNumeric
      || $this->options['value_path'] === self::$valueFormatString
    ) {
      $expression = $this->getJsonExpression($this->tableAlias . '.data', $this->options['value_path'], $this->options['value_format']);
    }
    else {
      $expression = $this->getJsonExpression($this->tableAlias . '.data', $this->options['value_path']);
    }
    $params = [];
    if (
      $this->options['group_type']
      && $this->options['group_type'] !== 'group'
    ) {
      $params['function'] = $this->options['group_type'];
    }
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $this->field_alias = $query->addField(NULL, $expression, $this->field, $params);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $this->view->element['#attached']['library'][] = 'extended_logger_db/fields';
    $value = $this->getValue($values);
    if (!empty($value)) {
      switch ($this->options['value_format']) {
        case self::$valueFormatRaw:
          switch ($this->options['display_format']) {
            case self::DISPLAY_FORMAT_YAML:
              $data = json_decode($value, associative: TRUE);
              $value = Yaml::encode($data);
              break;

            case self::DISPLAY_FORMAT_LIST:
              $data = json_decode($value, associative: TRUE);
              $value = implode(', ', $data);
              break;

            case self::DISPLAY_FORMAT_JSON_PRETTY:
              $data = json_decode($value);
              $value = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
              break;

            case self::DISPLAY_FORMAT_JSON:
            default:
              break;
          }
          break;

        default:
          // No casting.
          break;
      }
    }
    $output = Markup::create("<div class='extended-logger-db-pre'>$value</div>");
    return $output;
  }

}
