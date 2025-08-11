<?php

namespace Drupal\extended_logger_db\Plugin\views\filter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Element\Datetime;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Filters log entries by timestamp range.
 */
#[ViewsFilter('extended_logger_db_time_range')]
class ExtendedLoggerDbTimeRange extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $alwaysMultiple = TRUE;

  /**
   * The date format arguments for render time in queries and summary.
   *
   * @var string
   */
  const DRUPAL_DATE_FORMAT = 'Y-m-d H:i:s';

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    // The default value comes as string, but we expect array.
    if ($this->value === "") {
      $this->value = [
        'min' => NULL,
        'max' => NULL,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  public function defaultExposeOptions() {
    parent::defaultExposeOptions();
    $this->options['expose']['identifier'] = 'time';
    $this->options['value'] = [
      'min' => NULL,
      'max' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['expose']['contains']['identifier']['default'] = 'timestamp_range';
    $options['expose']['contains']['label']['default'] = $this->t('Time range');
    return $options;
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  public function operators() {
    return [
      'between' => [
        'title' => $this->t('Is between'),
        'short' => $this->t('between'),
        'method' => 'opBetween',
        'values' => 2,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value']['#tree'] = TRUE;
    $form['#attached']['library'][] = 'extended_logger_db/views';
    $form['value']['#attributes']['class'][] = 'extended-logger-db-views-filter-time-form';

    $form['value']['min'] = [
      '#type' => 'datetime',
      '#title' => $this->t('From date'),
      '#description' => $this->t('Choose the start date, time is not required.'),
      '#default_value' => $this->value['min'] ? new DrupalDateTime($this->value['min']) : NULL,
      '#element_validate' => [
        [self::class, 'validateMinTimeValue'],
        [Datetime::class, 'validateDatetime'],
      ],
    ];

    $form['value']['max'] = [
      '#type' => 'datetime',
      '#title' => $this->t('To date'),
      '#description' => $this->t('Choose the end date, time is not required.'),
      '#default_value' => $this->value['max'] ? new DrupalDateTime($this->value['max']) : NULL,
      '#element_validate' => [
        [self::class, 'validateMaxTimeValue'],
        [Datetime::class, 'validateDatetime'],
      ],
    ];
  }

  /**
   * Validate the minimum time value for the filter form element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateMinTimeValue(&$element, FormStateInterface $form_state, &$complete_form) {
    self::validateTimeValue('00:00:00', $element, $form_state, $complete_form);
  }

  /**
   * Validate the maximum time value for the filter form element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateMaxTimeValue(&$element, FormStateInterface $form_state, &$complete_form) {
    self::validateTimeValue('23:59:59', $element, $form_state, $complete_form);
  }

  /**
   * Validates the time value for the filter form element.
   *
   * @param string $defaultTime
   *   The default time to use if only date is provided.
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateTimeValue($defaultTime, &$element, FormStateInterface $form_state, &$complete_form) {
    if (
      $element['#value']['date'] !== ''
      && $element['#value']['time'] === ''
    ) {
      $element['#value']['time'] = $defaultTime;
      $value = Datetime::valueCallback($element, $element['#value'], $form_state);
      $form_state->setValueForElement($element, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildValueWrapper(&$form, $wrapper_identifier) {
    parent::buildValueWrapper($form, $wrapper_identifier);
    $form[$wrapper_identifier]['#type'] = 'container';
    $form[$wrapper_identifier]['#attributes']['class'][] = 'views-exposed-form__item';
    $form[$wrapper_identifier]['#attributes']['class'][] = 'extended-logger-db-views-filter-time-wrapper';
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  protected function valueSubmit($form, FormStateInterface $form_state) {
    $options = $form_state->getValue('options');
    if ($options['value']['min'] instanceof DrupalDateTime) {
      $options['value']['min'] = $options['value']['min']->format('c');
    }
    if ($options['value']['max'] instanceof DrupalDateTime) {
      $options['value']['max'] = $options['value']['max']->format('c');
    }
    $form_state->setValue('options', $options);
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $min = $this->t('any');
    $max = $this->t('any');

    if (!empty($this->value['min'])) {
      try {
        $minValue = new DrupalDateTime($this->value['min']);
        /**
         * @var \Drupal\Core\Datetime\DrupalDateTime $minValue
         */
        $min = $minValue->format(self::DRUPAL_DATE_FORMAT);
      }
      catch (\Exception) {
        // Leave as 'any' if invalid.
      }
    }
    if (!empty($this->value['max'])) {
      try {
        $maxValue = new DrupalDateTime($this->value['max']);
        /**
         * @var \Drupal\Core\Datetime\DrupalDateTime $maxValue
         */
        $max = $maxValue->format(self::DRUPAL_DATE_FORMAT);
      }
      catch (\Exception) {
        // Leave as 'any' if invalid.
      }
    }

    $value = $this->t('@min - @max', ['@min' => $min, '@max' => $max]);

    if ($this->isAGroup()) {
      $value .= ', grouped';
    }
    if (!empty($this->options['exposed'])) {
      $value .= ', exposed';
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    if (empty($this->value['min']) && empty($this->value['max'])) {
      return;
    }

    $field = $this->ensureMyTable() . '.' . $this->realField;

    $drupalDateTimeFormatArgs = [
      'Y-m-d H:i:s.u',
      ['timezone' => 'UTC'],
    ];

    // Ensure we have a SQL query plugin that supports addWhere().
    if (!$this->query instanceof Sql) {
      return;
    }

    /**
     * @var \Drupal\views\Plugin\views\query\Sql $query
     */
    $query = $this->query;

    if (!empty($this->value['min']) && !empty($this->value['max'])) {
      $minValue = new DrupalDateTime($this->value['min']);
      $maxValue = new DrupalDateTime($this->value['max']);
      $query->addWhere(
        $this->options['group'],
        $field,
        [
          $minValue->format(...$drupalDateTimeFormatArgs),
          $maxValue->format(...$drupalDateTimeFormatArgs),
        ],
        'BETWEEN'
      );
    }
    elseif (!empty($this->value['min'])) {
      $minValue = new DrupalDateTime($this->value['min']);
      /**
       * @var \Drupal\Core\Datetime\DrupalDateTime $minValue
       */
      $query->addWhere(
        $this->options['group'],
        $field,
        $minValue->format(...$drupalDateTimeFormatArgs),
        '>='
      );
    }
    elseif (!empty($this->value['max'])) {
      $maxValue = new DrupalDateTime($this->value['max']);
      /**
       * @var \Drupal\Core\Datetime\DrupalDateTime $maxValue
       */
      $query->addWhere(
        $this->options['group'],
        $field,
        $maxValue->format(...$drupalDateTimeFormatArgs),
        '<='
      );
    }
  }

}
