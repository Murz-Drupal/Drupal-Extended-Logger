<?php

namespace Drupal\extended_logger_db\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides a field handler that renders a microtime more compact.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("extended_logger_time")
 */
class ExtendedLoggerTime extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    // Removing the microseconds from the rendered output via an ugly hack.
    // @todo Try to make it less ugly.
    $value = preg_replace('#(\d+)\.\d{6}#', '\1', $value);
    return $this->sanitizeValue($value);
  }

}
