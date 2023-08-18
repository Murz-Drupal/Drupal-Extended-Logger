<?php

namespace Drupal\Tests\extended_logger\Unit;

use Drupal\extended_logger\ExtendedLoggerEntry;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\extended_logger\ExtendedLoggerEntry
 * @group extended_logger
 */
class ExtendedLoggerEntryTest extends UnitTestCase {

  /**
   * @covers ::__construct
   * @covers ::get
   * @covers ::set
   * @covers ::delete
   * @covers ::getData
   * @covers ::setData
   * @covers ::__toString
   */
  public function testGeneral() {
    $entry0 = new ExtendedLoggerEntry();
    $this->assertEquals([], $entry0->getData());

    $data1 = ['foo' => 'bar', 'baz' => 'qix'];
    $entry1 = new ExtendedLoggerEntry($data1);
    $this->assertEquals($data1, $entry1->getData());
    $this->assertEquals($data1['foo'], $entry1->get('foo'));

    $data2 = $data1 + ['fred' => 'thud'];
    $entry1->set('fred', $data2['fred']);
    $this->assertEquals($data2, $entry1->getData());
    $this->assertEquals($data2['fred'], $entry1->get('fred'));
    $this->assertEquals(json_encode($data2), $entry1->__toString());

    $entry1->delete('fred');
    $this->assertEquals($data1, $entry1->getData());

    $entry1->setData($data2);
    $this->assertEquals($data2['fred'], $entry1->get('fred'));
  }

}
