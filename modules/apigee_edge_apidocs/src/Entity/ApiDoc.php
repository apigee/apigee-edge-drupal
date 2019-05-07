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
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
 *       "reimport_spec" = "Drupal\apigee_edge_apidocs\Form\ApiDocUpdateSpecForm",
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
 *     "reimport-spec-form" = "/admin/structure/apidoc/{apidoc}/reimport",
 *     "collection" = "/admin/structure/apidoc",
 *   },
 *   field_ui_base_route = "apigee_edge_apidocs.settings"
 * )
 */
class ApiDoc extends ContentEntityBase implements ApiDocInterface {

  use EntityChangedTrait;
  use StringTranslationTrait;

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

    $fields['spec_file_source'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('OpenAPI specification file source'))
      ->setDescription(t('Indicate if the OpenAPI spec will be provided as a
                          file for upload or a URL.'))
      ->setDefaultValue(ApiDocInterface::SPEC_AS_FILE)
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        ApiDocInterface::SPEC_AS_FILE => t('File'),
        ApiDocInterface::SPEC_AS_URL => t('URL'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 0,
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
      ->addConstraint('ApiDocFileLink')
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

    $fields['spec_md5'] = BaseFieldDefinition::create('string')
      ->setLabel(t('OpenAPI specification file MD5'))
      ->setDescription(t('OpenAPI specification file MD5'))
      ->setSettings([
        'text_processing' => 0,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

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

    $this->reimportOpenApiSpecFile(FALSE, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function reimportOpenApiSpecFile($save = TRUE, $new_revision = TRUE) {
    $needs_save = FALSE;
    $spec_value = $this->get('spec')->isEmpty() ? [] : $this->get('spec')->getValue()[0];

    // If "spec_file_source" uses URL, grab file from "file_link" and save it
    // into the "spec" file field. The file_link field should already have
    // validated that a valid file exists at that URL.
    if ($this->get('spec_file_source')->value == ApiDocInterface::SPEC_AS_URL) {

      // If the file_link field is empty, return without showing errors.
      if ($this->get('file_link')->isEmpty()) {
        return TRUE;
      }

      $file_uri = $this->get('file_link')->getValue()[0]['uri'];
      $data = file_get_contents($file_uri);
      if (empty($data)) {
        \Drupal::messenger()
          ->addMessage($this->t('Could not retrieve OpenAPI specifications file located at %url', [
            '%url' => $file_uri,
          ]), 'error');
        return FALSE;
      }

      // Only save file if it hasn't been fetched previously.
      $data_md5 = md5($data);
      $prev_md5 = $this->get('spec_md5')->isEmpty() ? NULL : $this->get('spec_md5')->value;
      if ($prev_md5 != $data_md5) {
        $filename = \Drupal::service('file_system')->basename($file_uri);
        $specs_definition = $this->getFieldDefinition('spec')->getItemDefinition();
        $target_dir = $specs_definition->getSetting('file_directory');
        $uri_scheme = $specs_definition->getSetting('uri_scheme');
        $file = file_save_data($data, "$uri_scheme://$target_dir/$filename", FILE_EXISTS_RENAME);
        $spec_value = [
          'target_id' => $file->id(),
        ] + $spec_value;
        $this->set('spec', $spec_value);
        $this->set('spec_md5', $data_md5);

        $needs_save = TRUE;
      }
    }

    elseif (!empty($spec_value['target_id'])) {
      /* @var \Drupal\file\Entity\File $file */
      $file = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->load($spec_value['target_id']);

      if ($file) {
        $prev_md5 = $this->get('spec_md5')->isEmpty() ? NULL : $this->get('spec_md5')->value;
        $file_md5 = md5_file($file->getFileUri());
        if ($prev_md5 != $file_md5) {
          $this->set('spec_md5', $file_md5);
          $needs_save = TRUE;
        }
      }
    }

    // Only save if changes were made.
    if ($save && $needs_save) {
      if ($new_revision && $this->getEntityType()->isRevisionable()) {
        $this->setNewRevision();
      }
      $this->save();
    }

    return TRUE;
  }

}
