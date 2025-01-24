<?php

/*
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

namespace Drupal\apigee_mock_api_client;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class ApigeeMockClientServiceProvider.
 *
 * @todo replace this with a service decorator that injects the apigee_mock_api_client.mock_http_client_factory dependency.
 * @see https://github.com/apigee/apigee-edge-drupal/pull/79#discussion_r229186448
 *
 * This class is automatically picked up by the container builder.
 * @see: https://www.drupal.org/docs/8/api/services-and-dependency-injection/altering-existing-services-providing-dynamic-services.
 */
class ApigeeMockApiClientServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Override the ClientFactory with our mock client factory.
    $container->getDefinition('apigee_edge.sdk_connector')
      ->replaceArgument(0, new Reference('apigee_mock_api_client.mock_http_client_factory'));
  }

}
