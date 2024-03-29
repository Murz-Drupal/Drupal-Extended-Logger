<?php

namespace Drupal\Tests\extended_logger\Unit;

use Drupal\Core\Logger\LogMessageParser;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\extended_logger\ExtendedLoggerEntry;
use Drupal\extended_logger\Logger\ExtendedLogger;
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
   * @covers ::log
   */
  public function testLog() {
    global $base_url;
    $base_url = 'https://example.com/path';
    $server['REQUEST_TIME'] = 123;
    $server['REQUEST_TIME_FLOAT'] = 123.456;
    $request = new Request(server: $server);
    TestHelpers::service('logger.log_message_parser', new LogMessageParser());
    TestHelpers::service('request_stack')->push($request);

    $configDefault = Yaml::parseFile(TestHelpers::getModuleFilePath('config/install/extended_logger.settings.yml'));
    $config = [
      'fields_custom' => ['customField2', 'custom_field_5', 'custom_field_6'],
    ] + $configDefault;
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_KEY, $config);

    $context = [
      'ip' => '192.168.1.1',
      'timestamp' => 1234567,
      'customField2' => 'custom2 value',
      'custom_field_6' => 'custom_field_6 value',
      'metadata' => ['foo' => ['bar' => 'baz']],
      '@placeholder' => 'Bob',
    ];
    $message_raw = 'A message from @placeholder!';
    $message = "A message from {$context['@placeholder']}!";

    $resultEntryValues = [
      // The 'timestamp_float' is not static, checked separately.
      'message' => $message,
      'message_raw' => $message_raw,
      'base_url' => $base_url,
      'request_time_float' => $server['REQUEST_TIME_FLOAT'],
      'ip' => '192.168.1.1',
      'severity' => 4,
      'level' => 'warning',
      'metadata' => $context['metadata'],
      'customField2' => $context['customField2'],
      'custom_field_6' => $context['custom_field_6'],
    ];
    $resultEntry = new ExtendedLoggerEntry();
    foreach ($resultEntryValues as $key => $value) {
      $resultEntry->$key = $value;
    }

    $logLevel = RfcLogLevel::WARNING;

    $logger = TestHelpers::initService(
      'extended_logger.logger',
      NULL,
      ['persist'],
    );
    $logger->method('persist')->willReturnCallback(
      function (ExtendedLoggerEntry $entry, int $level) use ($logLevel, $resultEntry) {
        $this->assertIsFloat($entry->get('timestamp_float'));
        $entry->delete('timestamp_float');
        if ($entry->get('trace_id')) {
          $entry->delete('trace_id');
        }
        $this->assertEquals(json_encode($resultEntry), $entry->__toString());
        $this->assertEquals($logLevel, $level);
      });

    $logger->log($logLevel, $message_raw, $context);
  }

  /**
   * @covers ::persist
   */
  public function testPersist() {
    $entryData = [
      'foo' => 'bar',
    ];
    $entry = new ExtendedLoggerEntry($entryData);
    $level = RfcLogLevel::EMERGENCY;
    $configDefault = Yaml::parseFile(TestHelpers::getModuleFilePath('config/install/extended_logger.settings.yml'));

    // Test writing to a file.
    $config = [
      'target' => 'file',
      'target_file_path' => '/tmp/my_drupal.log',
    ] + $configDefault;
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_KEY, $config);
    $calls = TestHelpers::mockPhpFunction('file_put_contents', ExtendedLogger::class);
    $logger = TestHelpers::initService('extended_logger.logger');
    TestHelpers::callPrivateMethod($logger, 'persist', [$entry, $level]);

    $this->assertEquals($config['target_file_path'], $calls[0][0]);
    $this->assertEquals(json_encode($entryData) . "\n", $calls[0][1]);

    // Test writing to the stderr.
    $config = [
      'target' => 'output',
      'target_output_stream' => 'stderr',
    ] + $configDefault;
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_KEY, $config);
    $calls = TestHelpers::mockPhpFunction('file_put_contents', ExtendedLogger::class);
    $logger = TestHelpers::initService('extended_logger.logger');
    TestHelpers::callPrivateMethod($logger, 'persist', [$entry, $level]);
    $this->assertEquals('php://stderr', $calls[0][0]);
    $this->assertEquals(json_encode($entry->getData()) . "\n", $calls[0][1]);

    // Test writing to syslog.
    $config = [
      'target' => 'syslog',
      'target_syslog_identity' => 'MyDrupal',
      'target_syslog_facility' => LOG_USER,
    ] + $configDefault;
    TestHelpers::service('config.factory')->stubSetConfig(ExtendedLogger::CONFIG_KEY, $config);
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
  }

}
