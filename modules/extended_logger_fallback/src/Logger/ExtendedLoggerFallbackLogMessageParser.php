<?php

namespace Drupal\extended_logger_fallback\Logger;

use Drupal\Core\Logger\LogMessageParserInterface;

/**
 * Parses log messages and their placeholders with JSONPath support.
 */
class ExtendedLoggerFallbackLogMessageParser implements LogMessageParserInterface {

  /**
   * Constructs the ExtendedLoggerFallbackLogMessageParser.
   *
   * @param \Drupal\Core\Logger\LogMessageParserInterface $extendedParser
   *   The extended log message parser service.
   */
  public function __construct(
    protected readonly LogMessageParserInterface $extendedParser,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function parseMessagePlaceholders(&$message, array &$context) {
    $variables = $this->extendedParser->parseMessagePlaceholders($message, $context);
    // Replace the PSR3-style placeholders to the Drupal style.
    $variablesFallback = [];
    $replacements = [];
    foreach ($variables as $key => $value) {
      if (!str_starts_with($key, '{')) {
        $variablesFallback[$key] = $value;
        continue;
      }
      $keyOnly = str_replace(['{', '}'], '', $key);
      $keyFallback = '@' . preg_replace('/[^A-Za-z0-9_]+/', '--', $keyOnly);
      $variablesFallback[$keyFallback] = $value;
      $replacements[$key] = $keyFallback;
    }
    $message = strtr($message, $replacements);
    return $variablesFallback;
  }

}
