<?php

namespace Drupal\extended_logger\Util;

/**
 * Contains Extended Logger utils.
 */
class ExtendedLoggerUtils {

  /**
   * Converts an array of form options into a list of selected values.
   *
   * @param array $options
   *   The submitted values from the form.
   *
   * @return array
   *   An array of values that were checked.
   */
  public static function formOptionsToList(array $options): array {
    return array_values(array_filter($options, function ($value) {
      return $value != 0;
    }, ARRAY_FILTER_USE_BOTH));
  }

}
