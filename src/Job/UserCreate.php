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

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\Exception\DeveloperToUserConversationInvalidValueException;
use Drupal\apigee_edge\Structure\DeveloperToUserConversionResult;
use Drupal\user\Plugin\Validation\Constraint\UserNameUnique;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * A job to create a Drupal user from an Apigee Edge developer.
 */
class UserCreate extends UserCreateUpdate {

  /**
   * {@inheritdoc}
   */
  protected function beforeUserSave(DeveloperToUserConversionResult $result): void {
    // Abort the operation if any of these special problems occurred
    // meanwhile the conversation.
    foreach ($result->getProblems() as $problem) {
      if ($problem instanceof DeveloperToUserConversationInvalidValueException) {
        $violation = $problem->getViolation();
        // Skip user creation if username is already taken here instead
        // of getting a database exception in a lower layer.
        // (Username is not unique in Apigee Edge.)
        if ($problem->getTarget() === 'name' && $violation instanceof ConstraintViolation && $violation->getConstraint() instanceof UserNameUnique) {
          throw $problem;
        }
      }
    }
    parent::beforeUserSave($result);
  }

  /**
   * {@inheritdoc}
   */
  protected function afterUserSave(DeveloperToUserConversionResult $result): void {
    $context = [];
    // If user could be saved.
    if ($result->getUser()->id()) {
      $context['link'] = $result->getUser()->toLink(t('View user'))->toString();
    }
    // Only log problems after a user has been saved because this way we can
    // provide an link to its profile page in log entries.
    $this->logConversionProblems($result->getProblems(), $context);
    parent::afterUserSave($result);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Copying developer (@email) from Apigee Edge.', [
      '@email' => $this->email,
    ])->render();
  }

}
