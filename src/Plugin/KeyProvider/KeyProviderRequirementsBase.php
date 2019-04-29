<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Plugin\KeyProvider;

use Drupal\apigee_edge\Exception\KeyProviderRequirementsException;
use Drupal\apigee_edge\Plugin\KeyProviderRequirementsInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyProviderBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for key providers with pre-requirements.
 */
abstract class KeyProviderRequirementsBase extends KeyProviderBase implements KeyProviderRequirementsInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * KeyProviderRequirementsBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.apigee_edge')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->checkRequirements($form_state->getFormObject()->getEntity());
    }
    catch (KeyProviderRequirementsException $exception) {
      $form_state->setError($form['settings']['provider_section']['key_provider'], $exception->getTranslatableMarkupMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  final public function getKeyValue(KeyInterface $key) {
    // Throwing an exception would be better than returning NULL but the key
    // module's design does not allow this.
    // Related issue: https://www.drupal.org/project/key/issues/3038212
    try {
      $this->checkRequirements($key);
    }
    catch (KeyProviderRequirementsException $exception) {
      $context = [
        '@message' => (string) $exception,
      ];
      $context += Error::decodeException($exception);
      $this->getLogger()->error('Could not retrieve Apigee Edge authentication key value from the environment variables: @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
      return NULL;
    }

    return $this->realGetKeyValue($key);
  }

  /**
   * The method that returns a key's value after pre-requirements got validated.
   *
   * @param \Drupal\key\KeyInterface $key
   *   A key entity.
   */
  abstract protected function realGetKeyValue(KeyInterface $key);

  /**
   * Gets the logger service.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger service.
   */
  protected function getLogger(): LoggerInterface {
    // This fallback is needed when the plugin instance is serialized and the
    // property is null.
    return $this->logger ?? \Drupal::service('logger.channel.apigee_edge');
  }

}
