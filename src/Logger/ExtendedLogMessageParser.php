<?php

namespace Drupal\extended_logger\Logger;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Logger\LogMessageParser;
use Flow\JSONPath\JSONPath;

/**
 * Parses log messages and their placeholders with JSONPath support.
 */
class ExtendedLogMessageParser extends LogMessageParser {

  /**
   * {@inheritdoc}
   */
  public function parseMessagePlaceholders(&$message, array &$context) {
    preg_match_all('/\{([^{}]+)\}/', $message, $matches);
    $psr3Placeholders = $matches[1] ?? [];

    // Collect raw variables for placeholders from the context.
    $variablesRaw = [];
    foreach ($psr3Placeholders as $placeholder) {
      // Support for JSONPath placeholders.
      if (str_starts_with($placeholder, '$.')) {
        $jsonData ??= new JSONPath($context);
        try {
          $value = $jsonData->find($placeholder)->getData();
        }
        catch (\Exception $e) {
          $value = [$e->getMessage()];
        }
        if (!empty($value)) {
          if (count($value) > 1) {
            $variablesRaw[$placeholder] = $value;
          }
          else {
            $variablesRaw[$placeholder] = current($value);
          }
        }
      }
      // Support for nested array value placeholders.
      elseif (strpos($placeholder, '.') !== FALSE) {
        $nestedPath = explode('.', $placeholder);
        $nestedValue = NestedArray::getValue($context, $nestedPath, $keyExists);
        if ($keyExists) {
          $variablesRaw[$placeholder] = $nestedValue;
        }
      }
      // Support for plain context placeholders.
      elseif (isset($context[$placeholder])) {
        $variablesRaw[$placeholder] = $context[$placeholder];
      }
      // Support for Drupal style context values, starting with '@'.
      elseif (isset($context['@' . $placeholder])) {
        $variablesRaw[$placeholder] = $context[$placeholder];
      }
    }

    // Convert raw variables to strings suitable for the message.
    $variables = [];
    foreach ($variablesRaw as $key => $value) {
      $keySubstring = '{' . $key . '}';
      if (is_scalar($value)) {
        $variables[$keySubstring] = $value;
      }
      elseif ($value instanceof \Stringable) {
        $variables[$keySubstring] = (string) $value;
      }
      else {
        // Use YAML encoding for complex values as the most human-readable.
        // Maybe expose this in the settings later to allow choosing the format.
        $variables[$keySubstring] = Yaml::encode($value);
      }
    }

    // Drupal's parseMessagePlaceholders replaces PSR3-style placeholders
    // to the '@'-prefixed strings, that breaks the logic.
    // Copy the original message to avoid modifying it, as we already processed
    // the PSR3-style placeholders.
    $messageCopy = $message;

    // As Drupal's parseMessagePlaceholders forcibly replaces the PSR3-style
    // placeholders to the Drupal style, we need to replace the symbols
    // to make it skip the PSR3-style placeholders.
    $messageCopy = str_replace('{', '[[[', $messageCopy);
    $messageCopy = str_replace('}', ']]]', $messageCopy);

    // Merge with legacy variables from the Drupal default parser.
    // We do not need to put the `$messageCopy` changes back to the original
    // `$message`, as we already processed the PSR3-style placeholders.
    $variables += parent::parseMessagePlaceholders($messageCopy, $context);

    return $variables;
  }

}
