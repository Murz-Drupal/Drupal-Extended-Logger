<?php

namespace Drupal\extended_logger;

/**
 * The interface for an Extended Logger entry class.
 *
 * The class contains all the data that will be logged.
 *
 * @see Drupal\extended_logger\Logger\ExtendedLogger::LOGGER_FIELDS for the list
 * of common possible values.
 */
interface ExtendedLoggerEntryInterface {

  /**
   * The ExtendedLoggerEntry constructor.
   *
   * @param array $data
   *   (optional) An array with initial data.
   */
  public function __construct(array $data = NULL);

  /**
   * Sets a value to the log entry by a key.
   *
   * @param string $key
   *   A key.
   * @param mixed $value
   *   A value.
   *
   * @return \Drupal\extended_logger\ExtendedLoggerEntry
   *   The ExtendedLoggerEntry object.
   */
  public function set(string $key, $value): ExtendedLoggerEntry;

  /**
   * Gets a value from the log entry by a key.
   *
   * @param string $key
   *   A key.
   *
   * @return mixed
   *   The value by the key.
   */
  public function get(string $key): mixed;

  /**
   * Deletes a value from the log entry by a key.
   *
   * @param string $key
   *   A key.
   *
   * @return \Drupal\extended_logger\ExtendedLoggerEntry
   *   The ExtendedLoggerEntry object.
   */
  public function delete(string $key): ExtendedLoggerEntry;

  /**
   * Sets the whole data of the log entry.
   *
   * @param array $data
   *   The array with data.
   *
   * @return \Drupal\extended_logger\ExtendedLoggerEntry
   *   The ExtendedLoggerEntry object.
   */
  public function setData(array $data): ExtendedLoggerEntry;

  /**
   * Gets the whole log entry data.
   */
  public function getData(): mixed;

  /**
   * Converts the log data to the string represenation.
   */
  public function __toString(): string;

}
