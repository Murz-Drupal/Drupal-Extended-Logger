<?php

namespace Drupal\extended_logger\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\extended_logger\Event\ExtendedLoggerLogEvent;
use Drupal\extended_logger\ExtendedLoggerEntry;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SDK\Trace\Span;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// A workaround to make the logger compatible with Drupal 9.x and 10.x together.
if (version_compare(\Drupal::VERSION, '10.0.0') < 0) {
  require_once __DIR__ . '/ExtendedLoggerTrait.D9.inc';
}
else {
  require_once __DIR__ . '/ExtendedLoggerTrait.D10.inc';
}

/**
 * Redirects logging messages to syslog or stdout.
 */
class ExtendedLogger implements LoggerInterface {
  use RfcLoggerTrait;
  use ExtendedLoggerTrait;

  const CONFIG_KEY = 'extended_logger.settings';

  const LOGGER_FIELDS = [
    'time' => 'The timestamp as a string implementation in the "c" format.',
    'timestamp' => 'The log entry timestamp.',
    'timestamp_float' => 'The log entry timestamp in milliseconds.',
    'message' => 'The rendered log message with replaced placeholders.',
    'message_raw' => 'The raw log message, without replacing placeholders.',
    'base_url' => 'The base url of the site.',
    'request_time' => 'The main request timestamp.',
    'request_time_float' => 'The main request timestamp in milliseconds.',
    'channel' => 'The log recor channel.',
    'ip' => 'The user IP address.',
    'request_uri' => 'The request URI.',
    'referer' => 'The referrer.',
    'severity' => 'The severity level (numeric, 0-7).',
    'level' => 'The severity level in string (error, warning, notice, etc).',
    'uid' => 'The id of the current user.',
    'link' => 'The link value from the log context.',
    'metadata' => 'The structured value of the metadata key in the log context.',
    'exception' => 'Detailed information about an exception.',
  ];

  /**
   * Stores whether there is a system logger connection opened or not.
   *
   * @var bool
   */
  protected $syslogConnectionOpened = FALSE;


  /**
   * The logger configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructs a ExtendedLogger object.
   *
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The 'event_dispatcher' service.
   */
  public function __construct(
    protected LogMessageParserInterface $parser,
    protected ConfigFactoryInterface $configFactory,
    protected RequestStack $requestStack,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
    $this->config = $this->configFactory->get(self::CONFIG_KEY);
  }

  /**
   * Opens a connection to the system logger.
   */
  protected function getSyslogConnection(): bool {
    if (!$this->syslogConnectionOpened) {
      $this->syslogConnectionOpened = openlog(
        $this->config->get('target_syslog_identity') ?? '',
        LOG_NDELAY,
        $this->config->get('target_syslog_facility') ?? LOG_USER,
      );
    }
    return $this->syslogConnectionOpened;
  }

  /**
   * {@inheritdoc}
   */
  public function doLog($level, $message, array $context = []) {
    global $base_url;

    $fields = $this->config->get('fields') ?? [];

    $entry = new ExtendedLoggerEntry();

    foreach ($fields as $field) {
      switch ($field) {
        case '0':
          // Skipping turned off fields.
          break;

        case 'message':
          $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
          $entry->set($field, empty($message_placeholders) ? $message : strtr($message, $message_placeholders));
          break;

        case 'message_raw':
          $entry->set($field, $message);
          break;

        case 'base_url':
          $entry->set($field, $base_url);
          break;

        case 'timestamp_float':
          $entry->set($field, microtime(TRUE));
          break;

        case 'time':
          $entry->set($field, date('c', $context['timestamp']));
          break;

        case 'request_time':
          $request ??= $this->requestStack->getCurrentRequest();
          $entry->set($field, $request->server->get('REQUEST_TIME'));
          break;

        case 'request_time_float':
          $request ??= $this->requestStack->getCurrentRequest();
          $entry->set($field, $request->server->get('REQUEST_TIME_FLOAT'));
          break;

        case 'severity':
          $entry->set($field, $level);
          break;

        case 'level':
          $entry->set($field, $this->getRfcLogLevelAsString($level));
          break;

        case 'exception':
          if (isset($context['exception'])) {
            if ($context['exception'] instanceof \Throwable) {
              $entry->set($field, $this->exceptionToArray($context['exception']));
            }
            else {
              $entry->set($field, $context['exception']);
            }
          }
          break;

        // A special label "metadata" to pass any free form data.
        case 'metadata':
          if (isset($context[$field])) {
            $entry->set($field, $context[$field]);
          }
          break;

        // Default context keys from Drupal Core.
        case 'timestamp':
        case 'channel':
        case 'ip':
        case 'request_uri':
        case 'referer':
        case 'uid':
        case 'link':
          if (isset($context[$field])) {
            $entry->set($field, $context[$field]);
          }
          break;

        default:
          break;
      }
    }
    if ($this->config->get('fields_all') ?? FALSE) {
      foreach ($context as $field => $value) {
        if (!isset($fields[$field])) {
          $entry->set($field, $value);
        }
      }
      $entry->set($field, $context[$field]);
    }
    else {
      foreach ($this->config->get('fields_custom') ?? [] as $field) {
        if (isset($context[$field])) {
          $entry->set($field, $context[$field]);
        }
      }
    }

    // If we have an OpenTelemetry span, add the trace id to the log entry.
    if (class_exists(Span::class)) {
      $span = Span::getCurrent();
      if ($span instanceof SpanInterface) {
        $spanContext = $span->getContext();
        if ($spanContext instanceof SpanContextInterface) {
          $traceId = $spanContext->getTraceId();
          $entry->set('trace_id', $traceId);
        }
      }
    }

    $event = new ExtendedLoggerLogEvent($entry, $level, $message, $context);
    $this->eventDispatcher->dispatch($event);

    $this->persist($event->entry, $level);
  }

  /**
   * Persists a log entry to the log target.
   *
   * @param \Drupal\extended_logger\ExtendedLoggerEntry $entry
   *   A log entry array.
   * @param int $level
   *      The log entry level.
   */
  protected function persist(ExtendedLoggerEntry $entry, int $level): void {
    $target = $this->config->get('target') ?? 'syslog';
    switch ($target) {
      case 'syslog':
        if (!$this->getSyslogConnection()) {
          throw new \Exception("Can't open the connection to syslog");
        }
        syslog($level, $entry->__toString());
        break;

      case 'output':
        file_put_contents('php://' . $this->config->get('target_output_stream') ?? 'stdout', $entry->__toString() . "\n");
        break;

      case 'file':
        $file = $this->config->get('target_file_path');
        if (!empty($file)) {
          file_put_contents($file, $entry->__toString() . "\n", FILE_APPEND);
        }
        break;

      case 'null':
        break;

      default:
        throw new \Exception("Configured log target \"$target\" is not supported.");
    }
  }

  /**
   * Convert a level integer to a string representiation of the RFC log level.
   *
   * @param int $level
   *      The log message level.
   *
   * @return string
   *      String representation of the log level.
   */
  protected function getRfcLogLevelAsString(int $level): string {
    return match ($level) {
      RfcLogLevel::EMERGENCY => LogLevel::EMERGENCY,
      RfcLogLevel::ALERT => LogLevel::ALERT,
      RfcLogLevel::CRITICAL => LogLevel::CRITICAL,
      RfcLogLevel::ERROR => LogLevel::ERROR,
      RfcLogLevel::WARNING => LogLevel::WARNING,
      RfcLogLevel::NOTICE => LogLevel::NOTICE,
      RfcLogLevel::INFO => LogLevel::INFO,
      RfcLogLevel::DEBUG => LogLevel::DEBUG,
    };
  }

  /**
   * Converts an exception to the associative array representation.
   *
   * @param \Exception $e
   *   An exception.
   *
   * @return array
   *   An associative array with the exception data.
   */
  private function exceptionToArray(\Throwable $e) {
    $array = [
      'message' => $e->getMessage(),
      'code' => $e->getCode(),
      'file' => $e->getFile(),
      'line' => $e->getLine(),
      'trace' => $e->getTrace(),
    ];
    if ($ePrevious = $e->getPrevious()) {
      $array['previous'] = $this->exceptionToArray($ePrevious);
    }
    return $array;
  }

}
