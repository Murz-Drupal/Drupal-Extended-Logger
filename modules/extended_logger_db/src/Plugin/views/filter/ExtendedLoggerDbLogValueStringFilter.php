<?php

namespace Drupal\extended_logger_db\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\extended_logger_db\Plugin\views\JsonValuePathFunctionsTrait;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Exposes log channels to views module.
 */
#[ViewsFilter('extended_logger_db_log_value_string')]
class ExtendedLoggerDbLogValueStringFilter extends StringFilter implements ContainerFactoryPluginInterface {
  use JsonValuePathFunctionsTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $jsonPath = $this->options['value_path'];
    $valueExpression = $this->getJsonExpression($this->tableAlias . '.data', $jsonPath, self::$valueFormatString);
    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($valueExpression);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function operatorOptions($which = 'title') {
    $operatorOptions = parent::operatorOptions($which);
    // @todo Add support for the "word" operator.
    unset($operatorOptions['word']);
    unset($operatorOptions['allwords']);
    return $operatorOptions;
  }

  /**
   * Helper function to add where expressions.
   *
   * @param int $group
   *   The group number to add the where expression to.
   * @param string $valueExpression
   *   The expression pointing to the queries field, for example "foo.bar".
   * @param mixed $value
   *   The filtering value.
   * @param string|null $operator
   *   (optional) The operator to use in the expression, for example '=',
   *   'LIKE', 'IS NULL', etc. If NULL, then no operator is added to the
   *   expression.
   */
  private function addWhereExpressionAsWhere($group, $valueExpression, $value = NULL, $operator = NULL) {
    if (is_string($valueExpression)) {
      $expression = "$valueExpression $operator";
      $placeholder = ':' . $this->field . '_value';
      $expression .= ' ' . $placeholder;
      $placeholders = [
        $placeholder => $value,
      ];
    }
    else {
      $expression = $valueExpression;
      $placeholders = NULL;
    }

    // Placeholder should be unique per field.
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhereExpression(
      $group,
      $expression,
      $placeholders,
    );
  }

  /**
   * Adds a where clause for the operation, 'contains'.
   */
  protected function opContainsWord($field) {
    // @todo Implement this function.
    throw new \Exception('Not implemented yet');
  }

  /**
   * Adds a where clause for the operation, 'equals'.
   */
  public function opEqual($field) {
    $this->addWhereExpressionAsWhere($this->options['group'], $field, $this->connection->escapeLike($this->value), $this->operator());
  }

  /**
   * Adds a where clause for the operation, 'LIKE'.
   */
  protected function opContains($field) {
    $operator = $this->getConditionOperator('LIKE');
    $this->addWhereExpressionAsWhere($this->options['group'], $field, '%' . $this->connection->escapeLike($this->value) . '%', $operator);
  }

  /**
   * Adds a where clause for the operation, 'starts with'.
   */
  protected function opStartsWith($field) {
    $operator = $this->getConditionOperator('LIKE');
    $this->addWhereExpressionAsWhere($this->options['group'], $field, $this->connection->escapeLike($this->value) . '%', $operator);
  }

  /**
   * Adds a where clause for the operation, 'not starts with'.
   */
  protected function opNotStartsWith($field) {
    $operator = $this->getConditionOperator('NOT LIKE');
    $this->addWhereExpressionAsWhere($this->options['group'], $field, $this->connection->escapeLike($this->value) . '%', $operator);
  }

  /**
   * Adds a where clause for the operation, 'ends with'.
   */
  protected function opEndsWith($field) {
    $operator = $this->getConditionOperator('LIKE');
    $this->addWhereExpressionAsWhere($this->options['group'], $field, '%' . $this->connection->escapeLike($this->value), $operator);
  }

  /**
   * Adds a where clause for the operation, 'not ends with'.
   */
  protected function opNotEndsWith($field) {
    $operator = $this->getConditionOperator('NOT LIKE');
    $this->addWhereExpressionAsWhere($this->options['group'], $field, '%' . $this->connection->escapeLike($this->value), $operator);
  }

  /**
   * Adds a where clause for the operation, 'not LIKE'.
   */
  protected function opNotLike($field) {
    $operator = $this->getConditionOperator('NOT LIKE');
    $this->addWhereExpressionAsWhere($this->options['group'], $field, '%' . $this->connection->escapeLike($this->value) . '%', $operator);
  }

  /**
   * Adds a where clause for the operation, 'shorter than'.
   */
  protected function opShorterThan($field) {
    $placeholder = $this->placeholder();
    // Type cast the argument to an integer because the SQLite database driver
    // has to do some specific alterations to the query base on that data type.
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhereExpression($this->options['group'], "LENGTH($field) < $placeholder", [$placeholder => (int) $this->value]);
  }

  /**
   * Adds a where clause for the operation, 'longer than'.
   */
  protected function opLongerThan($field) {
    $placeholder = $this->placeholder();
    // Type cast the argument to an integer because the SQLite database driver
    // has to do some specific alterations to the query base on that data type.
    $this->addWhereExpressionAsWhere($this->options['group'], "LENGTH($field) > $placeholder", [$placeholder => (int) $this->value]);
  }

  /**
   * Filters by a regular expression.
   *
   * @param string $field
   *   The expression pointing to the queries field, for example "foo.bar".
   */
  protected function opRegex($field) {
    $this->addWhereExpressionAsWhere($this->options['group'], $field, $this->value, $this->getConditionOperator('REGEXP'));
  }

  /**
   * Filters by a negated regular expression.
   *
   * @param string $field
   *   The expression pointing to the queries field, for example "foo.bar".
   */
  protected function opNotRegex($field) {
    $this->addWhereExpressionAsWhere($this->options['group'], $field, $this->value, $this->getConditionOperator('NOT REGEXP'));
  }

  /**
   * Adds a where clause for the operation, 'EMPTY'.
   */
  protected function opEmpty($field) {
    if ($this->operator == 'empty') {
      $operator = "IS NULL";
    }
    else {
      $operator = "IS NOT NULL";
    }

    $this->addWhereExpressionAsWhere($this->options['group'], $field, NULL, $operator);
  }

}
