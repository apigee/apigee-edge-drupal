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

namespace Drupal\apigee_edge_teams\Structure;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Describes a team permission.
 */
final class TeamPermission {

  /**
   * The name of the team permission.
   *
   * @var string
   */
  private $name;

  /**
   * The human readable name of the team permission.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  private $label;

  /**
   * The optional description of the team permission.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup|null
   */
  private $description;

  /**
   * The category of the team permission.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  private $category;

  /**
   * TeamPermission constructor.
   *
   * @param string $name
   *   The unique machine name of the team permission.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the team permission, to be shown on the
   *   team permission administration page.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $category
   *   The category that the team permission belongs (ex.: "Team Apps", the
   *   name of the provider module, etc.), to be shown on the team permission
   *   administration page.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   A description of what the team permission does, to be shown on the team
   *   permission administration page.
   */
  public function __construct(string $name, TranslatableMarkup $label, TranslatableMarkup $category, ?TranslatableMarkup $description = NULL) {
    $this->name = $name;
    $this->label = $label;
    $this->description = $description;
    $this->category = $category;
  }

  /**
   * Returns the unique machine name of the team permission.
   *
   * @return string
   *   The name of the permission.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Returns the human readable name of the team permission.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The human readable name of the permission.
   */
  public function getLabel(): TranslatableMarkup {
    return $this->label;
  }

  /**
   * Returns the description of the team permission.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The description of the permission, or NULL.
   */
  public function getDescription(): ?TranslatableMarkup {
    return $this->description;
  }

  /**
   * Returns the category of the team permission.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The category of the team permission.
   */
  public function getCategory(): TranslatableMarkup {
    return $this->category;
  }

}
