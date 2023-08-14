<?php

namespace Drupal\extended_logger;

/**
 * Redirects logging messages to syslog or stdout.
 *
 * No declared properties listed, because the data can be structured in a free
 * form, and will be converted to JSON.
 *
 * @see Drupal\extended_logger\Form\SettingsForm::LOGGER_FIELDS for the list of
 * common possible values.
 */
class ExtendedLoggerEntry extends \stdClass {
}
