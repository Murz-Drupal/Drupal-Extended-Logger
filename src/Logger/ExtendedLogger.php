<?php

namespace Drupal\extended_logger\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\extended_logger\Event\ExtendedLoggerLogEvent;
use Drupal\extended_logger\ExtendedLoggerEntry;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// A workaround to make the logger compatible with Drupal 9.x and 10.x together.
if (version_compare(\Drupal::VERSION, '10.0.0') <= 0) {
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
    'timestamp' => 'The log entry timestamp.',
    'timestampFloat' => 'The log entry timestamp in milliseconds.',
    'message' => 'The rendered log message with replaced placeholders.',
    'messageRaw' => 'The raw log message, without replacing placeholders.',
    'baseUrl' => 'The base url of the site.',
    'requestTime' => 'The main request timestamp.',
    'requestTimeFloat' => 'The main request timestamp in milliseconds.',
    'channel' => 'The log recor channel.',
    'ip' => 'The user IP address.',
    'requestUri' => 'The request URI',
    'referer' => 'The referrer',
    'severity' => 'The severity level (numeric, 0-7).',
    'level' => 'The severity level in string (error, warning, notice, etc).',
    'uid' => 'The id of the current user.',
    'link' => 'The link value from the log context.',
    'metadata' => 'The structured value of the metadata key in the log context',
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
        $this->config->get('targetSyslogIdentity') ?? '',
        LOG_NDELAY,
        $this->config->get('targetSyslogFacility') ?? LOG_USER,
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

    foreach ($fields as $label) {
      switch ($label) {
        case 'message':
          $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
          $entry->$label = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);
          break;

        case 'messageRaw':
          $entry->$label = $message;
          break;

        case 'baseUrl':
          $entry->$label = $base_url;
          break;

        case 'timestampFloat':
          $entry->$label = microtime(TRUE);
          break;

        case 'requestTime':
          $request ??= $this->requestStack->getCurrentRequest();
          $entry->$label = $request->server->get('REQUEST_TIME');
          break;

        case 'requestTimeFloat':
          $request ??= $this->requestStack->getCurrentRequest();
          $entry->$label = $request->server->get('REQUEST_TIME_FLOAT');
          break;

        case 'severity':
          $entry->$label = $level;
          break;

        case 'level':
          $entry->$label = $this->getRfcLogLevelAsString($level);
          break;

        // A special label "metadata" to pass any free form data.
        case 'metadata':
          if (isset($context[$label])) {
            $entry->$label = $context[$label];
          }

        // Default context keys from Drupal Core.
        case 'timestamp':
        case 'channel':
        case 'ip':
        case 'requestUri':
        case 'referer':
        case 'uid':
        case 'link':
        default:
          if (isset($context[$label])) {
            $entry->$label = $context[$label];
          }
      }
    }
    foreach ($this->config->get('fieldsCustom') ?? [] as $field) {
      if (isset($context[$field])) {
        $entry->$field = $context[$field];
      }
    }
    $event = new ExtendedLoggerLogEvent($entry, $level, $message, $context);
    $this->eventDispatcher->dispatch($event);

    $this->persist($event->entry, $level);
  }

  /**
   * Persists a log entry to the log target.
   *
   * @param array $entry
   *   A log entry array.
   * @param int $level
   *      The log entry level.
   */
  protected function persist(ExtendedLoggerEntry $entry, int $level): void {
    $entryString = json_encode($entry);
    $target = $this->config->get('target') ?? 'syslog';
    switch ($target) {
      case 'syslog':
        if (!$this->getSyslogConnection()) {
          throw new \Exception("Can't open the connection to syslog");
        }
        syslog($level, $entryString);
        break;

      case 'output':
        file_put_contents('php://' . $this->config->get('targetOutputStream'), $entryString . "\n");
        break;

      case 'file':
        file_put_contents($this->config->get('targetFilePath'), $entryString . "\n", FILE_APPEND);
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

}
