<?php

namespace Drupal\extended_logger_db\Plugin\views\field;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\extended_logger\Logger\ExtendedLogger;
use Drupal\extended_logger\Util\ExtendedLoggerUtils;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Flow\JSONPath\JSONPath;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a field handler that renders values from the data array.
 */
#[ViewsField("extended_logger_db_data")]
class ExtendedLoggerDbData extends FieldPluginBase {

  const RENDER_STYLE_DEFAULT = 'comma_separated';
  const RENDER_STYLE_COMMA_SEPARATED = 'comma_separated';
  const RENDER_STYLE_YAML = 'yaml';
  const RENDER_STYLE_JSON = 'json';
  const RENDER_STYLE_JSON_PRETTY_PRINT = 'json_pretty_print';

  /**
   * The 'extended_logger.logger' service.
   *
   * @var \Drupal\extended_logger\Logger\ExtendedLogger
   */
  protected $extendedLogger;

  /**
   * The type of JSON library being used.
   *
   * @var string|null
   */
  protected $jsonLibraryType = NULL;

  /**
   * The JSON library instance.
   *
   * @var object|null
   */
  protected $jsonLib = NULL;

  /**
   * The JSON data instance.
   *
   * @var object|null
   */
  protected $jsonData = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->extendedLogger = $container->get('extended_logger.logger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['fields'] = ['default' => []];
    $options['max_length'] = ['default' => 128];
    $options['hide_empty_values'] = ['default' => TRUE];
    $options['empty_value_text'] = ['default' => 'Empty'];
    $options['missing_value_text'] = ['default' => 'N/A'];
    $options['complex_value_render_style'] = ['default' => self::RENDER_STYLE_DEFAULT];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $fieldsPredefined = $this->extendedLogger->getEnabledFields();
    $fieldsSelected = $this->options['fields'];
    $fieldsSelectedFromAvailable = array_intersect($fieldsSelected, $fieldsPredefined);
    $fieldsCustom = array_diff($fieldsSelected, $fieldsPredefined);

    $form['fields_display_predefined'] = [
      '#title' => $this->t('Display fields'),
      '#description' => $this->t('Choose fields to display. If only one field is chosen, it will be displayed without the label.'),
      '#type' => 'checkboxes',
      '#multiple' => TRUE,
      '#options' => array_combine($fieldsPredefined, $fieldsPredefined),
      '#default_value' => $fieldsSelectedFromAvailable,
    ];

    // @todo Make a better widget to manage multiple custom fields.
    $form['fields_display_custom'] = [
      '#title' => $this->t('Display custom fields'),
      '#description' => $this->t("Custom fields to display: field name or JSONPath, one field per line. Can contain a label separated by <code>|</code>. For example: <code>Field one:field_one\nNested field|$.metadata.custom_value_2</code>."),
      '#type' => 'textarea',
      '#default_value' => implode("\n", $fieldsCustom),
    ];
    if (!class_exists(JSONPath::class)) {
      $form['fields_display_custom']['#description'] .= ' ' . $this->t('JSONPath is not available because of the missing <code>softcreatr/jsonpath</code> library.');
    }
    $form['hide_empty_values'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide empty values'),
      '#description' => $this->t('If checked, fields with empty values will not be displayed.'),
      '#default_value' => $this->options['hide_empty_values'],
    ];
    $form['empty_value_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty value text'),
      '#description' => $this->t('Text to display when the value is empty. If not set, the field will not be displayed.'),
      '#default_value' => $this->options['empty_value_text'],
      '#states' => [
        'visible' => [
          ':input[name="options[hide_empty_values]"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['missing_value_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Missing value text'),
      '#description' => $this->t('Text to display when the value is missing in the data.'),
      '#default_value' => $this->options['missing_value_text'],
      '#states' => [
        'visible' => [
          ':input[name="options[hide_empty_values]"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['max_length'] = [
      '#title' => $this->t('Value display maximum length'),
      '#description' => $this->t('Trims the value output to the maximum characters. Set 0 to unlimited.'),
      '#type' => 'number',
      '#default_value' => $this->options['max_length'],
    ];
    $form['complex_value_render_style'] = [
      '#title' => $this->t('Complex value render style'),
      '#description' => $this->t('Configures the rendering style for non-scalar values: lists, nested arrays and objects.'),
      '#type' => 'radios',
      '#options' => [
        self::RENDER_STYLE_COMMA_SEPARATED => $this->t('Comma separated list'),
        self::RENDER_STYLE_YAML => $this->t('YAML'),
        self::RENDER_STYLE_JSON => $this->t('JSON'),
        self::RENDER_STYLE_JSON_PRETTY_PRINT => $this->t('JSON pretty print'),
      ],
      '#default_value' => $this->options['complex_value_render_style'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $options = $form_state->getValue('options');
    $fieldsDisplayPredefined = ExtendedLoggerUtils::formOptionsToList($options['fields_display_predefined']);
    $fieldsDisplayCustom = array_map('trim', explode("\n", $options['fields_display_custom']));
    $fieldsDisplay = array_merge($fieldsDisplayPredefined, $fieldsDisplayCustom);
    $options['fields'] = array_filter($fieldsDisplay);
    unset($options['fields_display_predefined'], $options['fields_display_custom']);
    $form_state->setValue('options', $options);
  }

  /**
   * Checks if the array is associative.
   *
   * @param array $array
   *   The array to check.
   *
   * @return bool
   *   TRUE if the array is associative, FALSE otherwise.
   */
  private static function isAssoc(array $array) {
    return array_keys($array) !== range(0, count($array) - 1);
  }

  /**
   * Trims the value following the max length configuration.
   *
   * @param string $string
   *   The value to sanitize.
   *
   * @return string
   *   The trimmed value.
   */
  private function trimString(string $string) {
    if ($this->options['max_length'] > 0 && mb_strlen($string) > $this->options['max_length']) {
      $string = mb_substr($string, 0, max(1, $this->options['max_length'] - 1)) . '&hellip;';
    }
    return $string;
  }

  /**
   * Renders a data value based on the configured style.
   *
   * @param mixed $value
   *   The value to render.
   *
   * @return string
   *   The rendered value.
   */
  protected function renderDataValue($value): string {
    if (is_scalar($value)) {
      $output = $this->trimString($value);
    }
    else {
      switch ($this->options['complex_value_render_style'] ?? self::RENDER_STYLE_DEFAULT) {
        default:
        case self::RENDER_STYLE_COMMA_SEPARATED:
          if (!is_scalar($value) && !is_array($value)) {
            $value = (array) $value;
          }
          $items = [];
          if ($this->isAssoc($value)) {
            foreach ($value as $key => $val) {
              $items[] = $key . ': ' . $this->renderDataValue($val);
            }
          }
          else {
            foreach ($value as $val) {
              $items[] = $this->renderDataValue($val);
            }
          }
          $output = implode(', ', $items);
          break;

        case self::RENDER_STYLE_YAML:
          $output = Yaml::encode(json_decode(json_encode($value), associative: TRUE));
          break;

        case self::RENDER_STYLE_JSON_PRETTY_PRINT:
          $output = json_encode($value, JSON_PRETTY_PRINT);
          break;

        case self::RENDER_STYLE_JSON:
          $output = json_encode($value);
          break;
      }
    }
    if ($output === NULL) {
      $output = '';
    }
    $output = $this->trimString($output);
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $this->view->element['#attached']['library'][] = 'extended_logger_db/fields';

    $data = json_decode($this->getValue($values), associative: TRUE);

    $fields = array_filter($this->options['fields'], function ($value, $key) {
      return $value != 0;
    }, ARRAY_FILTER_USE_BOTH);

    // If fields not configured, get all enabled fields from the logger
    // settings.
    if (empty($fields)) {
      $fields = $this->extendedLogger->getEnabledFields();
    }

    $displayLabel = count($fields) > 1;

    $items = [];
    foreach ($fields as $field) {
      if (!str_starts_with($field, '$') && str_contains($field, '|')) {
        [$label, $field] = explode('|', $field);
      }
      else {
        $label = $field;
      }
      if (str_starts_with($field, '$')) {
        $value = ExtendedLogger::getJsonPathValue($data, $field);
      }
      else {
        $value = $data[$field] ?? NULL;
      }

      if ($value === NULL) {
        if ($this->options['hide_empty_values']) {
          continue;
        }
        $value = $this->options['missing_value_text'];
      }
      if (empty($value) && $value !== 0 && $value !== '0') {
        if ($this->options['hide_empty_values']) {
          continue;
        }
        $value = $this->options['empty_value_text'];
      }
      $value = $this->renderDataValue($value);
      if (

        in_array($this->options['complex_value_render_style'] ?? self::RENDER_STYLE_DEFAULT, [
          self::RENDER_STYLE_YAML,
          self::RENDER_STYLE_JSON_PRETTY_PRINT,
        ])) {
        $value = Markup::create("<div class='extended-logger-db-pre'>$value</div>");
      }
      if ($displayLabel) {
        $value = Markup::create("<div class='extended-logger-db-item'><dd>$label:</dd><dt>$value</dt></div>");
      }
      $items[] = $value;
    }

    $output = Markup::create(implode("\n", $items));
    return $output;
  }

}
