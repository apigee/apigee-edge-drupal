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

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Exception\DeveloperDoesNotExistException;
use Drupal\apigee_edge\Exception\DeveloperToUserConversationInvalidValueException;
use Drupal\apigee_edge\Exception\DeveloperToUserConversionAttributeDoesNotExistException;
use Drupal\apigee_edge\Exception\UserDeveloperConversionException;
use Drupal\apigee_edge\Exception\UserDeveloperConversionNoStorageFormatterFoundException;
use Drupal\apigee_edge\Exception\UserDeveloperConversionUserFieldDoesNotExistException;
use Drupal\apigee_edge\Structure\DeveloperToUserConversionResult;
use Drupal\Core\TypedData\Plugin\DataType\ItemList;
use Drupal\Core\Utility\Error;

/**
 * Base class for user create/update sync jobs.
 */
abstract class UserCreateUpdate extends EdgeJob {

  use UserDeveloperSyncJobTrait;

  /**
   * The Apigee Edge developer's email.
   *
   * @var string
   */
  protected $email;

  /**
   * UserCreateUpdate constructor.
   *
   * @param string $email
   *   The email address of the user.
   */
  public function __construct(string $email) {
    parent::__construct();
    $this->email = $email;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    try {
      /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
      $developer = Developer::load($this->email);
      if ($developer === NULL) {
        throw new DeveloperDoesNotExistException($this->email);
      }

      $result = $this->userDeveloperConverter()->convertDeveloper($developer);
      $this->beforeUserSave($result);
      // Do not save user if there were no changes.
      if ($result->getSuccessfullyAppliedChanges() > 0) {
        // If the developer-user synchronization is in progress, then saving
        // the same developer in apigee_edge_user_presave() while creating
        // Drupal user based on a developer should be avoided.
        _apigee_edge_set_sync_in_progress(TRUE);
        $result->getUser()->save();
      }
    }
    catch (\Exception $exception) {
      $message = '@operation: Skipping %mail user. @message %function (line %line of %file). <pre>@backtrace_string</pre>';
      $context = [
        '%mail' => $this->email,
        '@operation' => get_class($this),
      ];
      $context += Error::decodeException($exception);
      $this->logger()->error($message, $context);
      $this->recordMessage(t('Skipping %mail user: @message', $context)->render());
    }
    finally {
      _apigee_edge_set_sync_in_progress(FALSE);
      if (isset($result)) {
        $this->afterUserSave($result);
      }
    }
  }

  /**
   * Execute actions before the user gets saved.
   *
   * @param \Drupal\apigee_edge\Structure\DeveloperToUserConversionResult $result
   *   Result of the entity conversion.
   *
   * @throws \Exception
   *   Can throw an exception to abort user save.
   */
  protected function beforeUserSave(DeveloperToUserConversionResult $result) : void {
    // Abort the operation if any of these special problems occurred
    // meanwhile the conversation.
    foreach ($result->getProblems() as $problem) {
      // Skip user save if username is already taken or the username
      // is too long instead of getting a database exception in a lower layer.
      // (Username field's value is not limited on Apigee Edge and it is not
      // unique either.)
      if (($problem instanceof DeveloperToUserConversationInvalidValueException) && $problem->getTarget() === 'name') {
        throw $problem;
      }
    }
    // It's necessary because changed time is automatically updated on the
    // UI only.
    $result->getUser()->setChangedTime(\Drupal::time()->getCurrentTime());
  }

  /**
   * Execute actions after the user has been saved.
   *
   * Actions here always gets executed even if the user save has failed.
   *
   * @param \Drupal\apigee_edge\Structure\DeveloperToUserConversionResult $result
   *   Result of the entity conversion.
   */
  protected function afterUserSave(DeveloperToUserConversionResult $result) : void {}

  /**
   * {@inheritdoc}
   */
  protected function logConversionProblem(UserDeveloperConversionException $problem, array $context = []) : void {
    $ro = new \ReflectionObject($this);
    $context += [
      '%mail' => $this->email,
      '@operation' => $ro->getShortName(),
    ];

    if ($problem instanceof DeveloperToUserConversionAttributeDoesNotExistException) {
      $message = '@operation: %field_name field value on %mail user has not changed because the relevant developer does not have %attribute_name attribute on Apigee Edge.';
      $context += [
        '%field_name' => $this->fieldAttributeConverter()->getFieldName($problem->getAttributeName()),
        '%attribute_name' => $problem->getAttributeName(),
      ];
      $this->logger()->warning($message, $context);
      $this->recordMessage(t("%field_name field value on %mail user has not changed because the relevant developer does not have %attribute_name attribute on Apigee Edge.", $context)->render());
    }
    elseif ($problem instanceof UserDeveloperConversionUserFieldDoesNotExistException) {
      $message = '@operation: %attribute_name attribute has been skipped because %field_name field does not exist on user.';
      $context += [
        '%field_name' => $problem->getFieldName(),
        '%attribute_name' => $this->fieldAttributeConverter()->getAttributeName($problem->getFieldName()),
      ];
      $this->logger()->warning($message, $context);
      $this->recordMessage(t("%attribute_name attribute has been skipped because %field_name field does not exist on user.", $context)->render());
    }
    elseif ($problem instanceof UserDeveloperConversionNoStorageFormatterFoundException) {
      $message = '@operation: %field_name field has been skipped because there is no available storage formatter for %field_type field type.';
      $context += [
        '%field_name' => $problem->getFieldDefinition()->getName(),
        '%field_type' => $problem->getFieldDefinition()->getType(),
      ];
      $this->logger()->warning($message, $context);
      $this->recordMessage(t('%field_name field has been skipped because there is no available storage formatter for %field_type field type.', $context)->render());
    }
    elseif ($problem instanceof DeveloperToUserConversationInvalidValueException) {
      $message = "@operation: %field_name field value on %mail user has not changed because %attribute_name attribute's value is invalid as a field value: %message";
      $context += [
        '%field_name' => $problem->getTarget(),
        '%attribute_name' => $problem->getSource(),
        '%field_value' => is_object($problem->getViolation()->getInvalidValue()) ? ($problem->getViolation()->getInvalidValue() instanceof ItemList ? var_export($problem->getViolation()->getInvalidValue()->getValue(), TRUE) : $problem->getViolation()->getInvalidValue()->value) : $problem->getViolation()->getInvalidValue(),
        '%message' => $problem->getViolation()->getMessage(),
      ];
      $this->logger()->warning($message, $context);
      $this->recordMessage(t("%field_name field value on %mail user has not changed because %attribute_name attribute's value is invalid as a field value: %message", $context)->render());
    }
    else {
      $context += Error::decodeException($problem);
      $this->logger()->warning('@operation: Unexpected problem occurred while creating %mail user: @message %function (line %line of %file). <pre>@backtrace_string</pre>');
      $this->recordMessage(t("Unexpected problem occurred while processing %mail user: @message", $context)->render());
    }
  }

}
