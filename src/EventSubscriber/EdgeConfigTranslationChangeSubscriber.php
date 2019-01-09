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

namespace Drupal\apigee_edge\EventSubscriber;

use Drupal\language\Config\LanguageConfigOverrideCrudEvent;
use Drupal\language\Config\LanguageConfigOverrideEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Clears caches when an Apigee Edge related config translation gets updated.
 *
 * The primary purpose of this subscriber is to clear all caches when Apigee
 * Edge custom entity labels gets translated via config objects.
 */
final class EdgeConfigTranslationChangeSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    if (class_exists('\Drupal\language\Config\LanguageConfigOverrideEvents')) {
      return [
        LanguageConfigOverrideEvents::SAVE_OVERRIDE => 'clearCache',
        LanguageConfigOverrideEvents::DELETE_OVERRIDE => 'clearCache',
      ];
    }
    return [];
  }

  /**
   * Clears caches when an Edge entity type's config translation gets updated.
   *
   * @param \Drupal\language\Config\LanguageConfigOverrideCrudEvent $event
   *   The event object.
   */
  public function clearCache(LanguageConfigOverrideCrudEvent $event) {
    /** @var \Drupal\language\Config\LanguageConfigOverride $override */
    $override = $event->getLanguageConfigOverride();
    if (preg_match('/^apigee_edge/', $override->getName())) {
      // It is easier to do that rather than just trying to figure our all
      // cache bins and tags that requires invalidation. We tried that.
      drupal_flush_all_caches();
    }
  }

}
