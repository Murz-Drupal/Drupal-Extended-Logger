<?php

declare(strict_types=1);

namespace Drupal\Tests\extended_logger\Unit;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\TemporaryStream;
use Drupal\Core\Utility\Error;
use Drupal\extended_logger\ExtendedLoggerEntry;
use Drupal\extended_logger\Logger\ExtendedLogger;
use Drupal\extended_logger\Logger\ExtendedLogMessageParser;
use Drupal\test_helpers\TestHelpers;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \Drupal\extended_logger\Logger\ExtendedLogger
 * @group extended_logger
 */
class ExtendedLoggerTest extends UnitTestCase {

  /**
   * @covers ::__construct
   * @covers ::getEnabledFields
   * @covers ::log
   */
  public function testLog() {
    global $base_url;
    $base_url = 'https://example.com/path';
    $server['REQUEST_TIME'] = 123;
    $server['REQUEST_TIME_FLOAT'] = 123.456;
    $request = new Request(server: $server);
    TestHelpers::service(ExtendedLogMessageParser::class, new ExtendedLogMessageParser());
    TestHelpers::service('request_stack')->push($request);

    $config = Yaml::parseFile(TestHelpers::getModuleFilePath('config/install/extended_logger.settings.yml'));
    $config['fields'] = array_merge($config['fields'], [
      'customField2',
      'custom_field_5',
      'custom_field_6',
      'custom_field_7',
      'custom_field_8',
    ]);
    $config['entry_exclude_empty'] = TRUE;
    $config['service.name'] = 'My cool service name';
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);

    $context = [
      'ip' => '192.168.1.1',
      'timestamp' => 1234567,
      'customField2' => 0,
      'customField3' => 'custom3 value',
      'custom_field_6' => NULL,
      'custom_field_8' => '0',
      'metadata' => ['foo' => ['bar' => 'baz']],
      '@my_placeholder' => 'Bob',
    ];
    $message_raw = 'A message from @my_placeholder!';
    $message = "A message from {$context['@my_placeholder']}!";

    $resultEntryValuesAll = [
      // The 'timestamp_float' is not static, checked separately.
      'message' => $message,
      'message_raw' => $message_raw,
      '@my_placeholder' => 'Bob',
      'base_url' => $base_url,
      'request_time_float' => $server['REQUEST_TIME_FLOAT'],
      'ip' => '192.168.1.1',
      'severity' => 4,
      'level' => 'warning',
      'metadata' => $context['metadata'],
      'customField2' => $context['customField2'],
      'custom_field_6' => $context['custom_field_6'],
      'custom_field_8' => '0',
      // Custom values should be at the end of the entry.
      'customField3' => $context['customField3'],
    ];
    $resultEntryValuesEnabled = $resultEntryValuesAll;
    unset($resultEntryValuesEnabled['custom_field_6']);
    unset($resultEntryValuesEnabled['customField3']);

    $resultEntry = new ExtendedLoggerEntry($resultEntryValuesEnabled);

    $logLevel = RfcLogLevel::WARNING;

    $logger = TestHelpers::initService(
      'extended_logger.logger',
      NULL,
      ['persist'],
    );

    $fields = $logger->getEnabledFields();
    $this->assertEquals($config['fields'], $fields);

    $logger->method('persist')->willReturnCallback(
      function (ExtendedLoggerEntry $entry, int $level) use ($logLevel, $resultEntry, $config) {
        $this->assertEquals($config['service_name'], $entry->get('service.name'));
        $entry->delete('service.name');
        $this->assertIsFloat($entry->get('timestamp_float'));
        $entry->delete('timestamp_float');
        if ($entry->get('trace_id')) {
          $entry->delete('trace_id');
        }
        $this->assertEquals(json_encode($resultEntry->getData()), $entry->__toString());
        $this->assertEquals($logLevel, $level);
      });

    $logger->log($logLevel, $message_raw, $context);

    // Test "fields_all".
    $config['fields_all'] = TRUE;
    $config['entry_exclude_empty'] = FALSE;
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);
    $logger = TestHelpers::initService(
      'extended_logger.logger',
      NULL,
      ['persist'],
    );
    $resultEntry = new ExtendedLoggerEntry($resultEntryValuesAll);

    $fields = $logger->getEnabledFields();
    $this->assertEquals($config['fields'], $fields);

    $logger->method('persist')->willReturnCallback(
      function (ExtendedLoggerEntry $entry, int $level) use ($logLevel, $resultEntry, $config) {
        $this->assertEquals($config['service_name'], $entry->get('service.name'));
        $entry->delete('service.name');
        $this->assertIsFloat($entry->get('timestamp_float'));
        $entry->delete('timestamp_float');
        $this->assertIsInt($entry->get('timestamp'));
        $entry->delete('timestamp');
        if ($entry->get('trace_id')) {
          $entry->delete('trace_id');
        }
        $this->assertEquals(json_encode($resultEntry->getData()), $entry->__toString());
        $this->assertEquals($logLevel, $level);
      });

    $logger->log($logLevel, $message_raw, $context);

  }

  /**
   * @covers ::log
   * @covers ::doLog
   * @covers ::prepareEntry
   */
  public function testLogWithEmptyConfig() {
    TestHelpers::service(ExtendedLogMessageParser::class, new ExtendedLogMessageParser());
    $calls = TestHelpers::mockPhpFunction('file_put_contents', ExtendedLogger::class);
    $logger = TestHelpers::initService('extended_logger.logger');
    $logger->log(3, 'Test message');
    $this->assertEmpty($calls);
  }

  /**
   * @covers ::persist
   */
  public function testPersist() {
    TestHelpers::service(ExtendedLogMessageParser::class, new ExtendedLogMessageParser());
    $entryData = [
      'service.name' => 'drupal',
      'timestamp_float' => 12345.984621,
      'message' => 'My message',
      'metadata' => [
        'foo' => [
          'bar' => 'baz',
        ],
        'qix' => [
          12.3,
          45.6,
        ],
      ],
    ];
    $entryDataCut129 = '{"service.name":"drupal","timestamp_float":12345.984621,"message":"My message","metadata":{"foo":{"bar":"baz_cut_"}}}';
    $entry = new ExtendedLoggerEntry($entryData);
    $level = RfcLogLevel::EMERGENCY;
    $configDefault = Yaml::parseFile(TestHelpers::getModuleFilePath('config/install/extended_logger.settings.yml'));

    // Test writing to a file.
    $config = [
      'target' => 'file',
      'target_file_path' => '/tmp/my_drupal.log',
    ] + $configDefault;
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);
    $calls = TestHelpers::mockPhpFunction('file_put_contents', ExtendedLogger::class);
    $logger = TestHelpers::initService('extended_logger.logger');
    TestHelpers::callPrivateMethod($logger, 'persist', [$entry, $level]);

    $this->assertEquals($config['target_file_path'], $calls[0][0]);
    $this->assertEquals(json_encode($entryData) . "\n", $calls[0][1]);
    TestHelpers::unmockAllPhpFunctions();

    // Test writing to the stderr.
    $config = [
      'target' => 'output',
      'target_output_stream' => 'stderr',
      'log_line_max_length' => 129,
    ] + $configDefault;
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);
    $calls = TestHelpers::mockPhpFunction('file_put_contents', ExtendedLogger::class);
    $logger = TestHelpers::initService('extended_logger.logger');
    TestHelpers::callPrivateMethod($logger, 'persist', [$entry, $level]);
    $this->assertEquals('php://stderr', $calls[0][0]);
    $this->assertEquals($entryDataCut129 . "\n", $calls[0][1]);
    TestHelpers::unmockAllPhpFunctions();

    // Test writing to syslog.
    $config = [
      'target' => 'syslog',
      'target_syslog_identity' => 'MyDrupal',
      'target_syslog_facility' => LOG_USER,
    ] + $configDefault;
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);
    $openlogCalls = TestHelpers::mockPhpFunction('openlog', ExtendedLogger::class, function () {
      return TRUE;
    });
    $syslogCalls = TestHelpers::mockPhpFunction('syslog', ExtendedLogger::class);
    $logger = TestHelpers::initService('extended_logger.logger');
    TestHelpers::callPrivateMethod($logger, 'persist', [$entry, $level]);
    $this->assertEquals($config['target_syslog_identity'], $openlogCalls[0][0]);
    $this->assertEquals($config['target_syslog_facility'], $openlogCalls[0][2]);
    $this->assertEquals($level, $syslogCalls[0][0]);
    $this->assertEquals($entry->__toString(), $syslogCalls[0][1]);
    TestHelpers::unmockAllPhpFunctions();
  }

  /**
   * @covers ::persist
   */
  public function testFileStreamWrappers() {
    TestHelpers::service(ExtendedLogMessageParser::class, new ExtendedLogMessageParser());
    $configDefault = Yaml::parseFile(TestHelpers::getModuleFilePath('config/install/extended_logger.settings.yml'));
    $fileName = uniqid('drupal_test_log_');
    $config = [
      'target' => 'file',
      'target_file_path' => "my_wr://$fileName",
    ] + $configDefault;
    $fileWrapperPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);

    $streamWrapper = new TemporaryStream();
    $streamWrapperManager = TestHelpers::service('stream_wrapper.my_wr', $streamWrapper);
    $streamWrapperManager = TestHelpers::service('stream_wrapper_manager', new StreamWrapperManager(TestHelpers::getContainer()));
    TestHelpers::service('file_system', initService: TRUE);
    $streamWrapperManager->addStreamWrapper('stream_wrapper.my_wr', TemporaryStream::class, 'my_wr');
    $logger = TestHelpers::initService('extended_logger.logger');
    $logger->log(1, 'Foo');
    $storedLog = json_decode(file_get_contents($fileWrapperPath), associative: TRUE);
    $this->assertEquals('Foo', $storedLog['message']);
    unlink($fileWrapperPath);
  }

  /**
   * @covers ::getEntryAsString
   */
  public function testGetEntryAsString() {
    $entryData = [
      'service.name' => 'drupal',
      'timestamp_float' => 12345.984621,
      'message' => 'My message',
      'metadata' => [
        'foo' => [
          'bar' => 'baz',
        ],
        'qix' => [
          12.3,
          45.6,
        ],
      ],
    ];
    $entry = new ExtendedLoggerEntry($entryData);
    $entryAsJson = json_encode($entryData);
    $entryAsJsonLength = strlen($entryAsJson);

    // Test without truncation (normal case).
    $config['log_line_max_length'] = NULL;
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);

    TestHelpers::service(ExtendedLogMessageParser::class, new ExtendedLogMessageParser());
    $logger = TestHelpers::initService('extended_logger.logger');
    $result = TestHelpers::callPrivateMethod($logger, 'getEntryAsString', [$entry]);
    $this->assertEquals($entryAsJson, $result);

    // Dangerous cut points that can lead to invalid JSON.
    $dangerousCutPoints = [
      -1,
      -18,
    ];

    foreach ($dangerousCutPoints as $cutPoint) {
      // Test with cutting to invalid JSON.
      $config['log_line_max_length'] = $entryAsJsonLength + $cutPoint;
      TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);

      TestHelpers::service(ExtendedLogMessageParser::class, new ExtendedLogMessageParser());
      $logger = TestHelpers::initService('extended_logger.logger');
      $result = TestHelpers::callPrivateMethod($logger, 'getEntryAsString', [$entry]);
      $this->assertJson($result);
      $this->assertLessThanOrEqual($entryAsJsonLength, strlen($result));
      // The result should contain the cut indicator if truncated.
      $this->assertStringContainsString(ExtendedLogger::CUT_SUFFIX, $result);
    }
  }

  /**
   * @covers ::doLog
   * @covers ::exceptionToArray
   */
  public function testEntryWithException() {
    $limit = 3;
    $config = [
      'fields' => [
        'exception',
        'backtrace',
      ],
      'backlog_items_limit' => $limit,
    ];
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);

    TestHelpers::service(ExtendedLogMessageParser::class, new ExtendedLogMessageParser());
    $logger = TestHelpers::initService('extended_logger.logger');
    $exception = new \Exception('Test exception', 0, new \Exception('Inner exception'));
    $context = Error::decodeException($exception);

    $entry = TestHelpers::callPrivateMethod($logger, 'prepareEntry', [
      Error::ERROR,
      Error::DEFAULT_ERROR_MESSAGE,
      $context,
    ]);

    $log = $entry->getData();
    $this->assertCount($limit, $log['exception']['trace']);
    $this->assertCount($limit, $log['backtrace']);
  }

  /**
   * @covers ::prepareEntry
   */
  public function testEntryWithJsonPathPlaceholders() {
    $config = [
      'fields' => [
        'message',
        'message_raw',
        'customField2',
      ],
    ];
    $message = 'Test: {$.customField2.foo.bar} {customField1.subField2} prefix_@placeholder2_suffix. End';
    $context = [
      'customField1' => [
        'subField1' => 'subValue1',
        'subField2' => 'subValue2',
      ],
      '@placeholder1' => 'placeholderValue1',
      '@placeholder2' => 'placeholderValue2',
      'customField2' => [
        'foo' => [
          'bar' => 'baz',
          'qix' => 'qux',
        ],
      ],
    ];

    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);
    TestHelpers::service(ExtendedLogMessageParser::class, new ExtendedLogMessageParser());
    $logger = TestHelpers::initService('extended_logger.logger');
    $entry = TestHelpers::callPrivateMethod($logger, 'prepareEntry', [
      RfcLogLevel::INFO,
      $message,
      $context,
    ]);
    $log = $entry->getData();

    $this->assertEquals($message, $log['message_raw']);
    $this->assertEquals('Test: baz subValue2 prefix_placeholderValue2_suffix. End', $log['message']);

    $this->assertEquals($context['customField1']['subField2'], $log['customField1']['subField2']);
    // This value should be absent, because the customField1 is not selected to
    // store in the config, and no usage of this value in the placeholders.
    $this->assertFalse(isset($log['customField1']['subField1']));

    $this->assertEquals($context['customField2']['foo']['bar'], $log['customField2']['foo']['bar']);
    // This value should be present, because the customField2 is selected to
    // store in the config.
    $this->assertEquals($context['customField2']['foo']['qix'], $log['customField2']['foo']['qix']);

    $this->assertEquals($context['@placeholder1'], $log['@placeholder1']);
    // This value should be present, because Drupal Core's function
    // parseMessagePlaceholders() extracts all Drupal-related placeholders.
    $this->assertEquals($context['@placeholder2'], $log['@placeholder2']);

    $config = [
      'fields' => [
        'message',
        'message_raw',
        'customField2',
      ],
      'fields_all' => TRUE,
    ];
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_NAME, $config);
    TestHelpers::service(ExtendedLogMessageParser::class, new ExtendedLogMessageParser());
    $logger = TestHelpers::initService('extended_logger.logger');
    $entry = TestHelpers::callPrivateMethod($logger, 'prepareEntry', [
      RfcLogLevel::INFO,
      $message,
      $context,
    ]);
    $log = $entry->getData();
    $this->assertTrue(TestHelpers::isNestedArraySubsetOf($log, $context));
  }

  /**
   * @covers ::getJsonPathValue
   */
  public function testGetJsonPathValue() {
    $data = [
      'foo' => [
        'bar' => 'baz',
        'qix' => 'qux',
      ],
    ];
    // Test simple dot notation.
    $simplePath = 'foo.bar';
    $result = ExtendedLogger::getJsonPathValue($data, $simplePath);
    $this->assertEquals('baz', $result);

    // Test JSONPath notation.
    $jsonPath = '$.foo.bar';
    $resultJsonPath = ExtendedLogger::getJsonPathValue($data, $jsonPath);
    if ($resultJsonPath === ExtendedLogger::JSONPATH_LIBRARY_MISSING_MESSAGE) {
      $this->markTestSkipped('JSONPath library missing');
    }
    else {
      $this->assertEquals('baz', $resultJsonPath);
    }

    // Test JSONPath exception.
    $data = [
      'foo' => [
        'bar' => 'baz',
      ],
    ];
    $invalidJsonPath = '{$.foo[INVALID]}';
    $result = ExtendedLogger::getJsonPathValue($data, $invalidJsonPath);
    // The result should be a string with the error message.
    $this->assertIsString($result);
    $this->assertStringContainsString('Unable to parse token', $result);
  }

}
