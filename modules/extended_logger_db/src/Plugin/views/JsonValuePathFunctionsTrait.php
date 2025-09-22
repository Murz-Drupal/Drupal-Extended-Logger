<?php

namespace Drupal\extended_logger_db\Plugin\views;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides JSON value expression for Views handlers.
 */
trait JsonValuePathFunctionsTrait {

  /*
   * We can't use constants in traits in old PHP versions, therefore have to use
   * regular variables as a workaround.
   */
  /**
   * Value format: raw.
   *
   * @var string
   */
  private static string $valueFormatRaw = 'RAW';

  /**
   * Value format: string.
   *
   * @var string
   */
  private static string $valueFormatString = 'STRING';

  /**
   * Value format: numeric.
   *
   * @var string
   */
  private static string $valueFormatNumeric = 'NUMERIC';

  /**
   * Cast-to-type constants.
   *
   * @var string
   */
  private static string $castToTypeString = 'STRING';

  /**
   * Cast-to-type: numeric.
   *
   * @var string
   */
  private static string $castToTypeNumeric = 'NUMERIC';

  /**
   * Type of db connection (e.g., 'mysql', 'pgsql', 'sqlite', etc.).
   *
   * @var string
   */
  protected string $connectionDbType;

  /**
   * Add option to the parent options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['value_path'] = [
      'default' => '',
    ];
    return $options;
  }

  /**
   * Add form item to the parent form definition.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['value_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value Path'),
      '#description' => $this->t('A path to the value in the log entry structure. Can be a dot-separated string like <code>metadata.group.my_value</code> or JSONPath like <code>$.metadata.group.my_value</code>.'),
      '#default_value' => $this->options['value_path'],
      '#required' => TRUE,
      '#weight' => -1,
    ];
    return $form;
  }

  /**
   * Returns information re: the DB to help determine the query syntax.
   */
  protected function getDbType() {
    if (!isset($this->connectionDbType)) {
      /** @var \Drupal\views\Plugin\views\query\Sql $query */
      $query = $this->query;
      $connection = $query->getConnection();
      $options = $connection->getConnectionOptions();
      $this->connectionDbType = $options['driver'];
    }
    return $this->connectionDbType;
  }

  /**
   * Expressions and placeholders organized by DB type and operator.
   */
  public function getJsonExpression($field, $jsonPath, $castToType = NULL) {
    if (!str_starts_with($jsonPath, '$')) {
      $jsonPath = '$.' . $jsonPath;
    }
    $jsonPath = addslashes($jsonPath);
    switch ($dbType = $this->getDbType()) {
      case 'mysql':
        $expression = "JSON_EXTRACT($field, '$jsonPath')";
        if ($castToType) {
          $expression = match ($castToType ?? '') {
            self::$castToTypeNumeric => "CAST(JSON_UNQUOTE($expression) AS UNSIGNED)",
            self::$castToTypeString => "CAST(JSON_UNQUOTE($expression) AS CHAR(4096))",
          };
        }
        else {
          $expression = "JSON_UNQUOTE($expression)";
        }
        return $expression;

      case 'pgsql':
        $expression = "JSONB_PATH_QUERY_FIRST($field, '$jsonPath')";
        if ($castToType) {
          $expression = match ($castToType) {
            self::$castToTypeNumeric => "($expression ->> 0)::NUMERIC",
            self::$castToTypeString => "($expression ->> 0)::TEXT",
          };
        }
        return $expression;

      case 'sqlite':
        $expression = "JSON_EXTRACT($field, '$jsonPath')";
        if ($castToType) {
          $expression = match ($castToType) {
            self::$castToTypeNumeric => "CAST($expression AS NUMERIC)",
            self::$castToTypeString => "CAST($expression AS TEXT)",
          };
        }
        return $expression;

      default:
        throw new \Exception("The database type '$dbType' is not supported by the Extended Logger DB module.");
    }
  }

}
