<?php

namespace Drupal\extended_logger\Trait;

/**
 * Configure Extended Logger settings for this site.
 */
trait SettingLabelTrait {

  /**
   * The typed Extended Logger settings.
   *
   * @var \Drupal\Core\Config\Schema\Mapping|\Drupal\Core\Config\Schema\Undefined
   */
  private $settingsTyped;

  /**
   * Gets the label for a setting from typed settings object.
   */
  private function getSettingLabel(string $key, ?string $fallback = NULL): string {
    try {
      $label = $this->settingsTyped->get($key)->getDataDefinition()->getLabel();
    }
    catch (\InvalidArgumentException $e) {
      $label = $fallback ?: "[$key]";
    }
    return $label;
  }

}
