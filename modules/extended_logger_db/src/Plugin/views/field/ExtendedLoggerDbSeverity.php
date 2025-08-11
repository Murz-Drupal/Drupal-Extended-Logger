<?php

namespace Drupal\extended_logger_db\Plugin\views\field;

use Drupal\extended_logger\Logger\ExtendedLogger;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides a field handler that renders a log severity.
 */
#[ViewsField("extended_logger_db_severity")]
class ExtendedLoggerDbSeverity extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $level = $this->getValue($values);
    $rendered = ExtendedLogger::getRfcLogLevelAsString($level);
    return $this->sanitizeValue($rendered);
  }

}
