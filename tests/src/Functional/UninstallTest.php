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

namespace Drupal\Tests\apigee_edge\Functional;

/**
 * Ensures the module can be uninstalled on the UI.
 *
 * @group apigee_edge
 */
class UninstallTest extends UninstallModuleTestBase {

  /**
   * {@inheritdoc}
   */
  protected function uninstalledModule(): string {
    return 'apigee_edge';
  }

  /**
   * {@inheritdoc}
   */
  protected function uninstallPreRequirements(): void {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer_in_browser */
    $module_installer_in_browser = \Drupal::service('module_installer');
    // Uninstall modules that require the Apigee Edge module.
    $module_installer_in_browser->uninstall(['apigee_edge_test', 'apigee_edge_debug']);
  }

}
