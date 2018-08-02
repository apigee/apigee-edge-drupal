<?php

/**
 * @file
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

/**
 * @file
 * Hooks for apigee_edge module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters the title of "My developer apps" page and menu link item.
 *
 * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
 *   The menu link/page title.
 * @param \Drupal\user\UserInterface|null $user
 *   It can be used if an admin visits another user's
 *   "My developer apps" page.
 */
function hook_apigee_edge_my_developer_apps_title_alter(\Drupal\Core\StringTranslation\TranslatableMarkup &$title, ?\Drupal\user\UserInterface $user = NULL) {
}

/**
 * @} End of "addtogroup hooks".
 */
