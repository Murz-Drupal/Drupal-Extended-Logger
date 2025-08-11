<?php

namespace Drupal\extended_logger_db\Plugin\views\field;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\TimeZoneFormHelper;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a field handler that renders timestamp values with date formatting.
 *
 * This field handler formats timestamp values (including microseconds) using
 * Drupal's date formatter service with configurable date formats.
 */
#[ViewsField("extended_logger_db_time")]
class ExtendedLoggerDbTime extends FieldPluginBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The date format storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $dateFormatStorage;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs a new ExtendedLoggerTime object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $date_format_storage
   *   The date format storage.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    DateFormatterInterface $date_formatter,
    EntityStorageInterface $date_format_storage,
    TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
    $this->dateFormatStorage = $date_format_storage;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter'),
      $container->get('entity_type.manager')->getStorage('date_format'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['date_format'] = ['default' => 'Y-m-d H:i:s'];
    $options['timezone'] = ['default' => ''];
    $options['link_to_page'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Output format'),
      '#description' => $this->t('The date format with millisecond precision. See <a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters" target="_blank">the PHP docs</a> for date formats for example, <code>Y-m-d H:i:s</code>.'),
      '#default_value' => $this->options['date_format'] ?? '',
    ];

    $form['timezone'] = [
      '#type' => 'select',
      '#title' => $this->t('Timezone'),
      '#description' => $this->t('Timezone to be used for date output.'),
      '#options' => ['' => $this->t('- Default site/user timezone -')] + TimeZoneFormHelper::getOptionsListByRegion(),
      '#default_value' => $this->options['timezone'] ?? '',
    ];

    $form['link_to_page'] = [
      '#title' => $this->t('Link to entry'),
      '#description' => $this->t('Make a link to the entry view page.'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->options['link_to_page']),
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    if (!$value) {
      return '';
    }

    // The database returns time as a string with UTC timezone and milliseconds.
    $date = new \DateTime($value, new \DateTimeZone('UTC'));
    $date->setTimezone(new \DateTimeZone($this->options['timezone'] ?: date_default_timezone_get()));

    $dateFormatted = $date->format($this->options['date_format'] ?: 'Y-m-d H:i:s');
    if ($this->options['link_to_page']) {
      // If the link to entity option is enabled, wrap the date in a link.
      $entryId = $values->extended_logger_logs_id ?? $values->{$this->view->storage->get('base_field')};
      if ($entryId) {
        $url = Url::fromRoute('extended_logger_db.entry', ['entry_id' => $entryId]);
        return [
          '#type' => 'link',
          '#title' => $dateFormatted,
          '#url' => $url,
          '#attributes' => ['class' => ['extended-logger-time-link']],
        ];
      }
    }
    return $dateFormatted;
  }

}
