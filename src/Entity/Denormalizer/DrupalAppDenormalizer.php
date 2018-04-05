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

namespace Drupal\apigee_edge\Entity\Denormalizer;

use Apigee\Edge\Api\Management\Denormalizer\AppDenormalizer;
use Drupal\apigee_edge\Entity\DeveloperApp;

/**
 * Ensures that loaded apps in Drupal are always Drupal entities.
 */
class DrupalAppDenormalizer extends AppDenormalizer {

  /**
   * {@inheritdoc}
   */
  protected $developerAppClass = DeveloperApp::class;

  // TODO Override this when company apps support comes.
  // protected $companyAppClass = CompanyApp::class;.
}
