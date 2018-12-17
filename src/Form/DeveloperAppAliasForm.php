<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Form;

/**
 * Provides a form for changing Developer App aliases.
 */
class DeveloperAppAliasForm extends EdgeEntityAliasConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_app_alias_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.developer_app_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function entityTypeName(): string {
    return $this->t('Developer App');
  }

  /**
   * {@inheritdoc}
   */
  protected function originalSingularLabel(): string {
    return $this->t('App');
  }

  /**
   * {@inheritdoc}
   */
  protected function originalPluralLabel(): string {
    return $this->t('Apps');
  }

}
