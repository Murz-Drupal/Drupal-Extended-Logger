<?php

declare(strict_types=1);

namespace Drupal\Tests\extended_logger\Unit;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\test_helpers\TestHelpers;
use Drupal\Tests\UnitTestCase;
use Drupal\Component\Serialization\Yaml;
use Drupal\extended_logger\Logger\ExtendedLogMessageParser;

/**
 * @coversDefaultClass \Drupal\extended_logger\Logger\ExtendedLogMessageParser
 * @group extended_logger
 */
class ExtendedLogMessageParserTest extends UnitTestCase {

  use StringTranslationTrait;

  /**
   * @covers ::parseMessagePlaceholders
   */
  public function testParseMessagePlaceholders() {
    TestHelpers::service('string_translation');
    $message = 'Test message with placeholders: @placeholder1, {placeholder2} and {nested.key1}, {nested.key2} and even {$.metadata.values}';
    $context = [
      '@placeholder1' => 'value1',
      'placeholder2' => 'value2',
      'nested' => [
        'key1' => 'nested value 1',
        'key2' => $this->t('translated value 2'),
      ],
      'metadata' => [
        'values' => [
          'val1' => 'My value 1',
          'val2' => 'My value 2',
        ],
      ],
    ];

    $parser = new ExtendedLogMessageParser();
    $placeholders = $parser->parseMessagePlaceholders($message, $context);
    $messageExpected = $message;
    $expected = [
      '{placeholder2}' => $context['placeholder2'],
      '{nested.key1}' => $context['nested']['key1'],
      '{nested.key2}' => (string) $context['nested']['key2'],
      '{$.metadata.values}' => Yaml::encode($context['metadata']['values']),
      '@placeholder1' => $context['@placeholder1'],
    ];
    $this->assertEquals($messageExpected, $message);
    $this->assertEquals($expected, $placeholders);
  }

}
