<?php

namespace Drupal\extended_logger;

/**
 * The Extended Logger entry object, containing all data that will be logged.
 *
 * No declared properties listed, because the data can be structured in a free
 * form, and will be converted to JSON.
 *
 * @see Drupal\extended_logger\Form\SettingsForm::LOGGER_FIELDS for the list of
 * common possible values.
 */
class ExtendedLoggerEntry implements ExtendedLoggerEntryInterface {

  /**
   * A storage of the log entry data.
   *
   * @var array
   */
  protected array $data;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $data = NULL) {
    $this->data = is_array($data)
      ? $data
      : [];
  }

  /**
   * {@inheritdoc}
   */
  public function set(string $key, $value): ExtendedLoggerEntry {
    $this->data[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $key): mixed {
    return $this->data[$key] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $key): ExtendedLoggerEntry {
    unset($this->data[$key]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setData(array $data): ExtendedLoggerEntry {
    $this->data = $data;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return json_encode($this->data);
  }

}
