<?php

namespace Drupal\extended_logger_db\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a field handler that renders values from the data array.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("extended_logger_data")
 */
class ExtendedLoggerData extends FieldPluginBase {

  /**
   * The 'extended_logger.logger' service.
   *
   * @var \Drupal\extended_logger\Logger\ExtendedLogger
   */

  protected $extendedLogger;

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

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $enabledFields = $this->extendedLogger->getFields();

    $form['fields'] = [
      '#title' => $this->t('Display fields'),
      '#description' => $this->t('Choose fields to display. If only one field is chosen, it will be displayed without the label.'),
      '#type' => 'checkboxes',
      '#multiple' => TRUE,
      '#options' => array_combine($enabledFields, $enabledFields),
      '#default_value' => $this->options['fields'],
    ];
    $form['max_length'] = [
      '#title' => $this->t('Value display maximum length'),
      '#description' => $this->t('Trims the value output to the maximum characters. Set 0 to unlimited.'),
      '#type' => 'number',
      '#default_value' => $this->options['max_length'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $data = json_decode($this->getValue($values));

    $fields = array_filter($this->options['fields'], function ($value, $key) {
      return $value != 0;
    }, ARRAY_FILTER_USE_BOTH);

    if (empty($fields)) {
      $fields = $this->extendedLogger->getFields();
    }

    $displayLabel = count($fields) > 1;

    $items = [];
    foreach ($fields as $field) {
      $value = $data->$field ?? NULL;
      if (empty($value)) {
        continue;
      }
      if ($this->options['max_length'] > 0 && mb_strlen($value) > $this->options['max_length']) {
        $value = mb_substr($value, 0, $this->options['max_length']) . '...';
      }
      $item = is_scalar($value)
        ? $this->sanitizeValue($value)
        : json_encode($value);
      if ($displayLabel) {
        $item = "<strong>$field</strong>: " . $item;
      }

      $items[] = $item;
    }

    $output = Markup::create(implode('<br/>', $items));
    return $output;
  }

}
