<?php

namespace Drupal\extended_logger_db\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\extended_logger_db\Plugin\views\JsonValuePathFunctionsTrait;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\NumericFilter;

/**
 * Exposes log channels to views module.
 */
#[ViewsFilter('extended_logger_db_log_value_numeric')]
class ExtendedLoggerDbLogValueNumericFilter extends NumericFilter implements ContainerFactoryPluginInterface {

  use JsonValuePathFunctionsTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $value = $this->value['value'];
    if (isset($value)) {
      $jsonPath = $this->options['value_path'];
      $valueExpression = $this->getJsonExpression($this->tableAlias . '.data', $jsonPath, self::$valueFormatNumeric);
      $placeholder = ':' . $this->field . '_value';
      $expression = "$valueExpression $this->operator $placeholder";
      /** @var \Drupal\views\Plugin\views\query\Sql $query */
      $query = $this->query;
      $query->addWhereExpression(
        $this->options['group'],
        $expression,
        [$placeholder => $value],
      );
    }
  }

}
