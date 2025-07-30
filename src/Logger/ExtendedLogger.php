<?php

namespace Drupal\extended_logger\Logger;

use Ahc\Json\Fixer;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\extended_logger\Event\ExtendedLoggerLogEvent;
use Drupal\extended_logger\ExtendedLoggerEntry;
use Drupal\extended_logger\ExtendedLoggerEntryInterface;
use Drupal\extended_logger_db\ExtendedLoggerDbPersister;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SDK\Trace\Span;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
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

  const CONFIG_NAME = 'extended_logger.settings';

  const CONFIG_KEY_FIELDS = 'fields';
  const CONFIG_KEY_FIELDS_ALL = 'fields_all';
  const CONFIG_KEY_ENTRY_EXCLUDE_EMPTY = 'entry_exclude_empty';
  const CONFIG_KEY_SERVICE_NAME = 'service_name';
  const CONFIG_KEY_TARGET = 'target';
  const CONFIG_KEY_TARGET_SYSLOG_IDENTITY = 'target_syslog_identity';
  const CONFIG_KEY_TARGET_SYSLOG_FACILITY = 'target_syslog_facility';
  const CONFIG_KEY_TARGET_FILE_PATH = 'target_file_path';
  const CONFIG_KEY_TARGET_OUTPUT_STREAM = 'target_output_stream';
  const CONFIG_KEY_LOG_LINE_MAX_LENGTH = 'log_line_max_length';
  const CONFIG_KEY_BACKLOG_ITEMS_LIMIT = 'backlog_items_limit';
  const CONFIG_KEY_SKIP_EVENT_DISPATCH = 'skip_event_dispatch';

  const LOGGER_FIELDS = [
    'service.name' => 'The name of the service that produce the log.',
    'time' => 'The timestamp as a string implementation in the "c" format.',
    'timestamp' => 'The log entry timestamp.',
    'timestamp_float' => 'The log entry timestamp in milliseconds.',
    'message' => 'The rendered log message with replaced placeholders.',
    'message_raw' => 'The raw log message, without replacing placeholders.',
    'base_url' => 'The base url of the site.',
    'request_time' => 'The main request timestamp.',
    'request_time_float' => 'The main request timestamp in milliseconds.',
    'channel' => 'The log record channel.',
    'ip' => 'The user IP address.',
    'request_uri' => 'The request URI.',
    'referer' => 'The referrer.',
    'severity' => 'The severity level (numeric, 0-7).',
    'level' => 'The severity level in string (error, warning, notice, etc).',
    'uid' => 'The id of the current user.',
    'link' => 'The link value from the log context.',
    'metadata' => 'The structured value of the metadata key in the log context.',
    'exception' => 'Detailed information about an exception.',
    'backtrace' => 'Backtrace array for exceptions. Duplicate the backtrace from the exception field, so do not enable both at once to prevent duplications.',
  ];

  const CUT_SUFFIX = '_cut_"';

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
   * A db persister class.
   *
   * @var \Drupal\extended_logger_db\ExtendedLoggerDbPersister
   */
  protected ?ExtendedLoggerDbPersister $dbPersister = NULL;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|null
   */
  protected ?RequestStack $requestStack = NULL;

  /**
   * Constructs a ExtendedLogger object.
   *
   * @param \Drupal\Component\DependencyInjection\ContainerInterface $container
   *   The service container for lazy loading services.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(
    protected ContainerInterface $container,
    protected LogMessageParserInterface $parser,
    protected ConfigFactoryInterface $configFactory,
  ) {
    $this->config = $this->configFactory->get(self::CONFIG_NAME);
  }

  /**
   * Gets the request stack service.
   */
  protected function getCurrentRequest(): ?Request {
    if ($this->container->has('request_stack') === FALSE) {
      return NULL;
    }
    if ($this->requestStack === NULL) {
      $this->requestStack = $this->container->get('request_stack');
    }
    return $this->requestStack->getCurrentRequest();
  }

  /**
   * Gets the event dispatcher service.
   */
  protected function getEventDispatcher(): EventDispatcherInterface {
    return $this->container->get('event_dispatcher');
  }

  /**
   * Opens a connection to the system logger.
   */
  protected function getSyslogConnection(): bool {
    if (!$this->syslogConnectionOpened) {
      $this->syslogConnectionOpened = openlog(
        $this->config->get(self::CONFIG_KEY_TARGET_SYSLOG_IDENTITY) ?? '',
        LOG_NDELAY,
        $this->config->get(self::CONFIG_KEY_TARGET_SYSLOG_FACILITY) ?? LOG_USER,
      );
    }
    return $this->syslogConnectionOpened;
  }

  /**
   * Returns a list of enabled fields in the configuration.
   */
  public function getEnabledFields(): array {
    return $this->config->get(self::CONFIG_KEY_FIELDS) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function doLog($level, $message, array $context = []) {
    $entry = $this->prepareEntry($level, $message, $context);

    if (!$this->config->get(self::CONFIG_KEY_SKIP_EVENT_DISPATCH)) {
      $event = new ExtendedLoggerLogEvent($entry, $level, $message, $context);
      $this->getEventDispatcher()->dispatch($event);
      $entry = $event->entry;
    }

    if ($entry->isEmpty()) {
      return;
    }

    $this->persist($entry, $level);
  }

  /**
   * Prepares a log entry from the message and context.
   *
   * @param int $level
   *   The log level.
   * @param string $message
   *   The log message.
   * @param array $context
   *   The log context.
   *
   * @return \Drupal\extended_logger\ExtendedLoggerEntryInterface
   *   The prepared log entry.
   */
  protected function prepareEntry(int $level, string $message, array $context): ExtendedLoggerEntryInterface {
    $fieldsEnabled = $this->config->get(self::CONFIG_KEY_FIELDS) ?? [];

    if (
      isset($context['backtrace'])
      && $limit = $this->config->get(self::CONFIG_KEY_BACKLOG_ITEMS_LIMIT)
    ) {
      $context['backtrace'] = array_slice($context['backtrace'], 0, $limit);
    }

    $entry = new ExtendedLoggerEntry();
    foreach ($fieldsEnabled as $field) {
      switch ($field) {
        case 'service.name':
          if ($value = $this->config->get(self::CONFIG_KEY_SERVICE_NAME)) {
            $entry->set($field, $value);
          }
          break;

        case 'message':
          $messageCopy = $message;
          $messagePlaceholders ??= $this->parser->parseMessagePlaceholders($messageCopy, $context);
          $messageRendered = empty($messagePlaceholders) ? $message : strtr($messageCopy, $messagePlaceholders);
          $entry->set($field, $messageRendered);
          break;

        case 'message_raw':
          $messageCopy = $message;
          $messagePlaceholders ??= $this->parser->parseMessagePlaceholders($messageCopy, $context);
          $entry->set($field, $messageCopy);
          foreach ($messagePlaceholders ?? [] as $key => $value) {
            $entry->set($key, $value);
          }
          break;

        case 'base_url':
          global $base_url;
          $entry->set($field, $base_url);
          break;

        case 'timestamp_float':
          $entry->set($field, microtime(TRUE));
          break;

        case 'time':
          $entry->set($field, date('c', $context['timestamp']));
          break;

        case 'request_time':
          if ($request ??= $this->getCurrentRequest()) {
            $entry->set($field, $request->server->get('REQUEST_TIME'));
          }
          break;

        case 'request_time_float':
          if ($request ??= $this->getCurrentRequest()) {
            $entry->set($field, $request->server->get('REQUEST_TIME_FLOAT'));
          }
          break;

        case 'severity':
          $entry->set($field, $level);
          break;

        case 'level':
          $entry->set($field, self::getRfcLogLevelAsString($level));
          break;

        case 'exception':
          if (array_key_exists($field, $context)) {
            if ($context['exception'] instanceof \Throwable) {
              // We use a custom implementation instead of the
              // Drupal\Core\Utility\Error::decodeException()
              // to produce the array in a more standard way.
              $entry->set($field, $this->exceptionToArray($context['exception']));
            }
            else {
              $entry->set($field, $context['exception']);
            }
          }
          break;

        // A special label "metadata" to pass any free form data.
        case 'metadata':
          if (array_key_exists($field, $context)) {
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
        case 'backtrace':
          if (array_key_exists($field, $context)) {
            $entry->set($field, $context[$field]);
          }
          break;

        default:
          if (array_key_exists($field, $context)) {
            $entry->set($field, $context[$field]);
          }
          break;
      }
    }

    if ($this->config->get(self::CONFIG_KEY_FIELDS_ALL) ?? FALSE) {
      foreach ($context as $field => $value) {
        if (!isset($fieldsEnabled[$field])) {
          $entry->set($field, $value);
        }
      }
    }

    // If we have an OpenTelemetry span, add the trace id to the log entry.
    if (class_exists(Span::class)) {
      $span = Span::getCurrent();
      if ($span instanceof SpanInterface) {
        $spanContext = $span->getContext();
        if (
          $spanContext instanceof SpanContextInterface
          && $spanContext->isValid()
        ) {
          $traceId = $spanContext->getTraceId();
          $entry->set('trace_id', $traceId);
        }
      }
    }

    if ($this->config->get(self::CONFIG_KEY_ENTRY_EXCLUDE_EMPTY) ?? FALSE) {
      $entry->cleanEmptyValues();
    }

    return $entry;
  }

  /**
   * Persists a log entry to the log target.
   *
   * @param \Drupal\extended_logger\ExtendedLoggerEntryInterface $entry
   *   A log entry array.
   * @param int $level
   *      The log entry level.
   */
  protected function persist(ExtendedLoggerEntryInterface $entry, int $level): void {
    $target = $this->config->get(self::CONFIG_KEY_TARGET) ?? 'output';
    switch ($target) {
      case 'syslog':
        if (!$this->getSyslogConnection()) {
          throw new \Exception("Can't open the connection to syslog");
        }
        syslog($level, $this->getEntryAsString($entry));
        break;

      case 'output':
        file_put_contents('php://' . ($this->config->get(self::CONFIG_KEY_TARGET_OUTPUT_STREAM) ?? 'stderr'), $this->getEntryAsString($entry) . "\n");
        break;

      case 'file':
        $file = $this->config->get(self::CONFIG_KEY_TARGET_FILE_PATH);
        if (!empty($file)) {
          // Support any Drupal stream wrapper (public://, private://,
          // temporary://, etc.) for file paths.
          if (str_contains($file, '://')) {
            $wrapper = $this->container->get('stream_wrapper_manager')->getViaUri($file);
            if ($wrapper && method_exists($wrapper, 'realpath')) {
              $realpath = $wrapper->realpath();
              if ($realpath !== FALSE) {
                $file = $realpath;
              }
            }
          }
          file_put_contents($file, $this->getEntryAsString($entry) . "\n", FILE_APPEND);
        }
        break;

      case 'database':
        $this->dbPersister ??= $this->getDbPersister();
        if ($this->dbPersister) {
          $this->dbPersister->persist($level, $entry);
        }
        break;

      case 'none':
        break;

      default:
        throw new \Exception("Configured log target \"$target\" is not supported.");
    }
  }

  /**
   * Converts entry to string and cut to the max length with valid JSON.
   *
   * @param \Drupal\extended_logger\ExtendedLoggerEntryInterface $entry
   *   The log entry.
   *
   * @return string
   *   The string representation of the log entry.
   */
  protected function getEntryAsString(ExtendedLoggerEntryInterface $entry): string {
    $string = $entry->__toString();
    $maxLength = $this->config->get(self::CONFIG_KEY_LOG_LINE_MAX_LENGTH);
    if ($maxLength > 0 && strlen($string) > $maxLength) {
      $jsonFixer = new Fixer();
      $cutIndicatorLength = strlen(self::CUT_SUFFIX);
      $cutPos = $maxLength - $cutIndicatorLength - 1;
      do {
        $stringCut = substr($string, 0, $cutPos) . self::CUT_SUFFIX;
        try {
          $stringFixed = $jsonFixer->fix($stringCut);
          $stringFixedLength = strlen($stringFixed);
        }
        catch (\Exception) {
          $stringFixedLength = $maxLength + 1;
        }
        $cutPos -= 1;
      } while ($stringFixedLength > $maxLength);
      $string = $stringFixed;
    }
    return $string;
  }

  /**
   * Convert a level integer to a string representation of the RFC log level.
   *
   * @param int $level
   *      The log message level.
   *
   * @return string
   *      String representation of the log level.
   */
  public static function getRfcLogLevelAsString(int $level): string {
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
   * @param \Throwable $e
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

    if ($limit = $this->config->get(self::CONFIG_KEY_BACKLOG_ITEMS_LIMIT)) {
      $array['trace'] = array_slice($array['trace'], 0, $limit);
    }

    if ($ePrevious = $e->getPrevious()) {
      $array['previous'] = $this->exceptionToArray($ePrevious);
    }
    return $array;
  }

  /**
   * Gets the 'extended_logger_db.persister' service if initialized.
   */
  protected function getDbPersister(): ?ExtendedLoggerDbPersister {
    $this->dbPersister ??=
      $this->container->has('extended_logger_db.persister')
      ? $this->container->get('extended_logger_db.persister')
      : NULL;
    return $this->dbPersister;
  }

}
