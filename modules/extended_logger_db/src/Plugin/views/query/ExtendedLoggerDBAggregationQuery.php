<?php

namespace Drupal\extended_logger_db\Plugin\views\query;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsQuery;
use Drupal\views\Plugin\views\query\Sql;

/**
 * Views query plugin for an SQL query.
 *
 * @ingroup views_query_plugins
 */
#[ViewsQuery(
  id: 'extended_logger_db_query',
  title: new TranslatableMarkup('Extended Logger DB SQL Query'),
  help: new TranslatableMarkup('An override of the base SQL query plugin to support custom aggregation plugins.')
)]
class ExtendedLoggerDBAggregationQuery extends Sql {

  /**
   * {@inheritdoc}
   */
  public function getAggregationInfo() {
    // A workaround for an issue to allow custom field plugins for aggregation.
    // @see https://www.drupal.org/project/drupal/issues/3541066
    $infoOriginal = parent::getAggregationInfo();
    $info = [
      'group' => $infoOriginal['group'],
      'json_value_count' => [
        'title' => $this->t('Count (log value by path)'),
        'method' => 'aggregationMethodJsonValueSimple',
        'handler' => [
          'argument' => 'groupby_numeric',
          'field' => 'extended_logger_db_value_numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ],
      ],
      'json_value_count_distinct' => [
        'title' => $this->t('Count DISTINCT (log value by path)'),
        'method' => 'aggregationMethodJsonValueDistinct',
        'handler' => [
          'argument' => 'groupby_numeric',
          'field' => 'extended_logger_db_value_numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ],
      ],
      'json_value_sum' => [
        'title' => $this->t('Sum (log value by path)'),
        'method' => 'aggregationMethodJsonValueSimple',
        'handler' => [
          'argument' => 'groupby_numeric',
          'field' => 'extended_logger_db_value_numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ],
      ],
      'json_value_avg' => [
        'title' => $this->t('Average (log value by path)'),
        'method' => 'aggregationMethodJsonValueSimple',
        'handler' => [
          'argument' => 'groupby_numeric',
          'field' => 'extended_logger_db_value_numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ],
      ],
      'json_value_min' => [
        'title' => $this->t('Minimum (log value by path)'),
        'method' => 'aggregationMethodJsonValueSimple',
        'handler' => [
          'argument' => 'groupby_numeric',
          'field' => 'extended_logger_db_value_numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ],
      ],
      'json_value_max' => [
        'title' => $this->t('Maximum (log value by path)'),
        'method' => 'aggregationMethodJsonValueSimple',
        'handler' => [
          'argument' => 'groupby_numeric',
          'field' => 'extended_logger_db_value_numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ],
      ],
      'json_value_stddev_pop' => [
        'title' => $this->t('Standard deviation (log value by path)'),
        'method' => 'aggregationMethodJsonValueSimple',
        'handler' => [
          'argument' => 'groupby_numeric',
          'field' => 'extended_logger_db_value_numeric',
          'filter' => 'groupby_numeric',
          'sort' => 'groupby_numeric',
        ],
      ],
    ];

    return $info;
  }

  /**
   * Builds a simple SQL expression.
   */
  public function aggregationMethodJsonValueSimple($group_type, $field) {
    $group_type = str_replace('json_value_', '', $group_type);
    return $this->aggregationMethodSimple($group_type, $field);
  }

  /**
   * Builds a SQL expression using DISTINCT.
   */
  public function aggregationMethodJsonValueDistinct($group_type, $field) {
    $group_type = str_replace('json_value_', '', $group_type);
    return $this->aggregationMethodJsonValueDistinct($group_type, $field);
  }

}
