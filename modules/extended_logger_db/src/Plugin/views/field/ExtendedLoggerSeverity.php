<?php

namespace Drupal\extended_logger_db\Plugin\views\field;

use Drupal\extended_logger\Logger\ExtendedLogger;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides a field handler that renders a log severity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("extended_logger_severity")
 */
class ExtendedLoggerSeverity extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $level = $this->getValue($values);
    $rendered = ExtendedLogger::getRfcLogLevelAsString($level);
    return $this->sanitizeValue($rendered);
  }

}
