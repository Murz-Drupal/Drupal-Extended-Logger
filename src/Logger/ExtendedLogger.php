<?php
namespace Drupal\extended_logger\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\extended_logger\Event\ExtendedLoggerLogEvent;
use Drupal\extended_logger\Form\SettingsForm;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Redirects logging messages to syslog or stdout.
 */
class ExtendedLogger implements LoggerInterface {
  use RfcLoggerTrait;

  /**
   * Stores whether there is a system logger connection opened or not.
   *
   * @var bool
   */
  protected $syslogConnectionOpened = FALSE;

  /**
   * Constructs a ExtendedLogger object.
   *
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    protected LogMessageParserInterface $parser,
    protected ConfigFactoryInterface $configFactory,
    protected RequestStack $requestStack,
  ) {
    $this->config = $this->configFactory->get(SettingsForm::CONFIG_KEY);
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
  public function log($level, $message, array $context = []) {
    global $base_url;

    $fields = $this->config->get('fields') ?? [];

    $record = [];

    foreach ($fields as $label) {
      switch ($label) {
        case 'message':
          $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
          $record[$label] = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);

        case 'message_raw':
          $record[$label] = $message;
          break;

        case 'base_url':
          $record[$label] = $base_url;
          break;

        case 'timestamp_msec':
          $record[$label] = microtime();
          break;

        case 'request_time':
          $request ??= $this->requestStack->getCurrentRequest();
          $record[$label] = $request->server->get('REQUEST_TIME');
          break;

        case 'request_time_msec':
          $request ??= $this->requestStack->getCurrentRequest();
          $record[$label] = $request->server->get('REQUEST_TIME_FLOAT');
          break;

        case 'severity':
          $record[$label] = $level;
          break;

        case 'level':
          // @todo Make a conversion from int to string.
          $record[$label] = $this->logLevelToString($level);
          break;

        // Special label "metadata" to pass any free form data.
        case 'metadata':
        // Default context keys from Drupal Core.
        case 'timestamp':
        case 'channel':
        case 'ip':
        case 'request_uri':
        case 'referer':
        case 'uid':
        case 'link':
        default:
          if (isset($context[$label])) {
            $record[$label] = $context[$label];
          }
      }
    }
    foreach ($this->config->get('fields_custom') as $field) {
      if (isset($context[$field])) {
        $record[$field] = $context[$field];
      }
    }
    $event = new ExtendedLoggerLogEvent($record);
    $this->eventDispatcher = \Drupal::service('event_dispatcher');
    $this->eventDispatcher->dispatch($event, ExtendedLoggerLogEvent::EVENT_NAME);

    $this->write($event->record, $level);
  }

  protected function write(array $record, int $level) {
    $recordString = json_encode($record);
    $target = $this->config->get('target') ?? 'syslog';
    switch ($target) {
      case 'syslog':
        if (!$this->getSyslogConnection()) {
          throw new \Exception("Can't open the connection to syslog");
        }
        syslog($level, $recordString);
        break;

      case 'output':
        file_put_contents('php://' . $this->config->get('target_output_stream'), $recordString . "\n");
        break;

      case 'file':
        file_put_contents($this->config->get('target_file_target'), $recordString . "\n", FILE_APPEND);
        break;

      case 'null':
        break;

      default:
        throw new \Exception("Configured log target \"$target\" is not supported.");
    }
  }

  protected function logLevelToString(int $level): string {
    switch ($level) {
      case 0:
        return LogLevel::EMERGENCY;
      case 1:
        return LogLevel::ALERT;
      case 2:
        return LogLevel::CRITICAL;
      case 3:
        return LogLevel::ERROR;
      case 4:
        return LogLevel::WARNING;
      case 5:
        return LogLevel::NOTICE;
      case 6:
        return LogLevel::INFO;
      default:
      case 7:
        return LogLevel::DEBUG;
    }
  }
}
