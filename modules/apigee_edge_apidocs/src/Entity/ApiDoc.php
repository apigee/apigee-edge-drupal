<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\apigee_edge_apidocs\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\link\LinkItemInterface;

/**
 * Defines the API Doc entity.
 *
 * @ContentEntityType(
 *   id = "apidoc",
 *   label = @Translation("API Doc"),
 *   label_singular = @Translation("API Doc"),
 *   label_plural = @Translation("API Docs"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\apigee_edge_apidocs\ApiDocListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "translation" = "Drupal\content_translation\ContentTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\apigee_edge_apidocs\Form\ApiDocForm",
 *       "add" = "Drupal\apigee_edge_apidocs\Form\ApiDocForm",
 *       "edit" = "Drupal\apigee_edge_apidocs\Form\ApiDocForm",
 *       "delete" = "Drupal\apigee_edge_apidocs\Form\ApiDocDeleteForm",
 *     },
 *     "access" = "Drupal\apigee_edge_apidocs\ApiDocAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\apigee_edge_apidocs\ApiDocHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "apidoc",
 *   data_table = "apidoc_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer apidoc entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/apidoc/{apidoc}",
 *     "add-form" = "/admin/structure/apidoc/add",
 *     "edit-form" = "/admin/structure/apidoc/{apidoc}/edit",
 *     "delete-form" = "/admin/structure/apidoc/{apidoc}/delete",
 *     "collection" = "/admin/structure/apidoc",
 *   },
 *   field_ui_base_route = "apigee_edge_apidocs.settings"
 * )
 */
class ApiDoc extends ContentEntityBase implements ApiDocInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getName() : string {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name) : ApiDocInterface {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() : string {
    return $this->get('description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $description) : ApiDocInterface {
    $this->set('description', $description);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() : int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp) : ApiDocInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() : bool {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished(bool $published) : ApiDocInterface {
    $this->set('status', $published);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpecAsFile() : bool {
    // Default is to use spec as file.
    if ($this->get('spec_as_file')->isEmpty()) {
      return TRUE;
    }

    return (bool) $this->get('spec_as_file')->getValue()[0]['value'];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the API.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDescription(t('Description of the API.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['spec_as_file'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Provide OpenAPI specification as a file'))
      ->setDescription(t('Indicate if the OpenAPI spec will be provided as a
                          file (or a URL otherwise).'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 0,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['spec'] = BaseFieldDefinition::create('file')
      ->setLabel('OpenAPI specification file')
      ->setDescription('The spec snapshot.')
      ->setSettings([
        'file_directory' => 'apidoc_specs',
        'file_extensions' => 'yml yaml json',
        'hander' => 'default:file',
        'text_processing' => 0,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'file',
        'weight' => -4,
      ])->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'label' => 'hidden',
        'type' => 'file_generic',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['file_link'] = BaseFieldDefinition::create('file_link')
      ->setLabel(t('URL to OpenAPI specification file'))
      ->setDescription(t('The URL to an OpenAPI file spec.'))
      ->setSettings([
        'file_extensions' => 'yml yaml json',
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_DISABLED,
      ])
      ->setDisplayOptions('form', [
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['api_product'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('API Product'))
      ->setDescription(t('The API Product this is documenting.'))
      ->setSetting('target_type', 'api_product')
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the API Doc is published.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 1,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // If "spec_as_file", grab file from "file_link" and save it into the
    // "spec" file field. The file_link field should already have validated that
    // a valid file exists at that URL.

    if (!$this->getSpecAsFile()) {
      $file_uri = $this->get('file_link')->getValue()[0]['uri'];
      $data = file_get_contents($file_uri);
      if (!$data) {
        throw new \LogicException(
          'File could not be retrieved at specified URL.'
        );
      }

      $filename = \Drupal::service('file_system')->basename($file_uri);
      $specs_definition = $this->getFieldDefinition('spec')->getItemDefinition();
      $target_dir = $specs_definition->getSetting('file_directory');
      $uri_scheme = $specs_definition->getSetting('uri_scheme');
      $file = file_save_data($data, "$uri_scheme://$target_dir/$filename", FILE_EXISTS_RENAME);
      $spec_value = [
          'target_id' => $file->id(),
        ] + $this->get('spec')->getValue()[0];
      $this->set('spec', $spec_value);
    }

  }

}
