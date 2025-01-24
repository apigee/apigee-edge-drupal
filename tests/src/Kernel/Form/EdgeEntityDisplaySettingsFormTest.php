<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\Tests\apigee_edge\Kernel\Form;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\apigee_edge\Form\EdgeEntityDisplaySettingsForm;

/**
 * Tests for EdgeEntityDisplaySettingsForm.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class EdgeEntityDisplaySettingsFormTest extends KernelTestBase {

  /**
   * The entity type to test.
   */
  const ENTITY_TYPE = 'developer_app';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'apigee_edge',
    'key',
  ];

  /**
   * Tests the config form.
   */
  public function testForm() {
    $builder = $this->container->get('form_builder');
    $form = $builder->getForm(EdgeEntityDisplaySettingsForm::class, static::ENTITY_TYPE);

    $display_type_options = $form['display_settings']['display_type']['#options'];
    static::assertCount(3, $display_type_options);
    static::assertSame('Default', (string) $display_type_options['default']);
    static::assertSame('Display mode', (string) $display_type_options['view_mode']);

    $view_mode_options = $form['display_settings']['display_mode_container']['view_mode']['#options'];
    static::assertCount(1, $view_mode_options);
    static::assertSame('Default', (string) $view_mode_options['default']);

    // Add view mode.
    EntityViewMode::create([
      'id' => static::ENTITY_TYPE . '.foo',
      'targetEntityType' => static::ENTITY_TYPE,
      'label' => 'Foo',
      'status' => TRUE,
    ])->save();

    // Check if new view mode appears on form.
    $builder = $this->container->get('form_builder');
    $form = $builder->getForm(EdgeEntityDisplaySettingsForm::class, static::ENTITY_TYPE);
    $view_mode_options = $form['display_settings']['display_mode_container']['view_mode']['#options'];
    static::assertCount(2, $view_mode_options);
    static::assertSame('Default', (string) $view_mode_options['default']);
    static::assertSame('Foo', (string) $view_mode_options['foo']);

    // Submit form and test config.
    $form_state = new FormState();
    $form_state->setValue('display_type', 'view_mode');
    $form_state->setValue('view_mode', 'foo');
    $this->container->get('form_builder')->submitForm(EdgeEntityDisplaySettingsForm::class, $form_state, static::ENTITY_TYPE);

    $config = $this->config('apigee_edge.display_settings.' . static::ENTITY_TYPE);
    static::assertSame('view_mode', $config->get('display_type'));
    static::assertSame('foo', $config->get('view_mode'));
  }

}
