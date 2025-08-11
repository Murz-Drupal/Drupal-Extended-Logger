<?php

namespace Drupal\extended_logger_db;

use Drupal\Core\Extension\ExtensionPathResolver;

use Drupal\Core\Config\FileStorage;
use Drupal\views\Entity\View;

/**
 * Persist a log entry to the database.
 */
class ExtendedLoggerDbUtils {

  /**
   * Constructs an ExtendedLoggerDbUtils object.
   *
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
   *   The extension path resolver service.
   */
  public function __construct(
    protected ExtensionPathResolver $extensionPathResolver,
  ) {
  }

  /**
   * Resets the Extended Logger Views page settings to defaults.
   */
  public function resetView(): void {
    $moduleDirectory = $this->extensionPathResolver->getPath('module', 'extended_logger_db');
    $configStorage = new FileStorage($moduleDirectory . '/config/install');
    $configName = 'views.view.extended_logger_logs';

    $configData = $configStorage->read($configName);

    /** @var \Drupal\views\ViewEntityInterface $view     */
    $view = View::load('extended_logger_logs');
    foreach ($configData as $key => $value) {
      $view->set($key, $value);
    }
    $view->save();
  }

}
