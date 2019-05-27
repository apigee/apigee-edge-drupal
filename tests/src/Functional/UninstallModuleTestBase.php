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
 * Base class for testing module uninstall on the UI.
 */
abstract class UninstallModuleTestBase extends ApigeeEdgeFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installExtraModules([$this->uninstalledModule()]);
  }

  /**
   * Ensures the module can be uninstalled on the UI.
   */
  public function testModuleUninstall(): void {
    $account = $this->drupalCreateUser(['administer modules']);
    $this->drupalLogin($account);
    $this->uninstallPreRequirements();
    $this->drupalGet('admin/modules/uninstall');
    $edit = [];
    $module_name = $this->uninstalledModule();
    $edit["uninstall[$module_name]"] = TRUE;
    $this->drupalPostForm('admin/modules/uninstall', $edit, t('Uninstall'));
    $this->drupalPostForm(NULL, NULL, t('Uninstall'));
    $this->assertText(t('The selected modules have been uninstalled.'), 'Modules status has been updated.');
  }

  /**
   * Returns the name of the uninstalled module.
   *
   * @return string
   *   The name of the module.
   */
  abstract  protected function uninstalledModule(): string;

  /**
   * Run actions before module gets installed.
   */
  protected function uninstallPreRequirements(): void {}

}
