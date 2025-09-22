<?php

declare(strict_types=1);

namespace Drupal\extended_logger_db_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\RfcLogLevel;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for the extended_logger_db_test test page.
 */
class MakeLogEntriesController extends ControllerBase {

  /**
   * Generates test metrics.
   *
   * @return array
   *   The render array.
   */
  public function __invoke(): JsonResponse {
    self::makeLogEntries();
    return new JsonResponse([
      'status' => 'success',
      'message' => 'Log entries created successfully.',
    ]);
  }

  /**
   * Creates test log entries with various metadata.
   */
  public static function makeLogEntries(): void {
    $logger = \Drupal::logger('extended_logger_db_test');
    $logger->log(RfcLogLevel::NOTICE, 'Foo', [
      'bar' => 'baz',
    ]);

    for ($i = 1; $i <= 3; $i++) {
      $logger->log(RfcLogLevel::INFO, "Test message $i {nested_values.key4} {\$.array[1]}", [
        'metadata' => [
          'key1' => "message $i key1 value",
          'key2' => "message $i key2 value",
          'nested_values' => [
            'key3' => "message $i key3 value",
            'key4' => "message $i key4 value",
          ],
          'array' => [
            "message $i item0 value",
            "message $i item1 value",
          ],
          'number_increment_1' => $i,
          'number_increment_2' => 100 + $i,
          'number fixed 42' => 42,
          'zero_value' => 0,
          'zero_string_value' => '0',
          'boolean_value' => ($i % 2 === 0),
          'null_value' => NULL,
        ],
      ]);
    }
  }

}
