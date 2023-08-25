<?php

/**
 * Copyright 2023 Google Inc.
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

namespace Drupal\apigee_edge\User;

use Drupal\user\UserInterface;

/**
 * Contract for triggering an Apigee specific reaction on user removal.
 */
interface UserRemovalHandlerInterface {

  /**
   * React on user removal.
   *
   * @param \Drupal\user\UserInterface $account
   *   The deleted user account.
   *
   * @see \hook_ENTITY_TYPE_delete()
   */
  public function __invoke(UserInterface $account): void;

}
