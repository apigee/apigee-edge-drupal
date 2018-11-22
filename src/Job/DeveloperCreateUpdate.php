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

use Drupal\apigee_edge\Exception\UserDeveloperConversionException;
use Drupal\apigee_edge\Exception\UserDeveloperConversionNoStorageFormatterFoundException;
use Drupal\apigee_edge\Exception\UserDeveloperConversionUserFieldDoesNotExistException;
use Drupal\apigee_edge\Exception\UserDoesNotExistWithEmail;
use Drupal\apigee_edge\Structure\UserToDeveloperConversionResult;
use Drupal\Core\Utility\Error;
use Drupal\user\UserInterface;

/**
 * Base class for user create/update sync jobs.
 */
abstract class DeveloperCreateUpdate extends EdgeJob {

  use UserDeveloperSyncJobTrait;

  /**
   * The Drupal user's email.
   *
   * @var string
   */
  protected $email;

  /**
   * DeveloperCreateUpdate constructor.
   *
   * @param string $email
   *   The email address of the developer.
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
      /** @var \Drupal\user\UserInterface $account */
      $account = user_load_by_mail($this->email);
      if (!$account) {
        throw new UserDoesNotExistWithEmail($this->email);
      }

      $result = $this->userDeveloperConverter()->convertUser($account);
      $this->beforeDeveloperSave($result, $account);
      // Do not save user if there were no changes.
      if ($result->getSuccessfullyAppliedChanges() > 0) {
        $result->getDeveloper()->save();
      }
    }
    catch (\Exception $exception) {
      $message = '@operation: Skipping %email developer. @message %function (line %line of %file). <pre>@backtrace_string</pre>';
      $context = [
        '%mail' => $this->email,
        'link' => $account->toLink(t('View user'))->toString(),
        '@operation' => get_class($this),
      ];
      $context += Error::decodeException($exception);
      $this->logger()->error($message, $context);
      $this->recordMessage(t('Skipping %mail developer: @message', $context)->render());
    }
    finally {
      if (isset($result)) {
        $this->afterDeveloperSave($result, $account);
      }
    }
  }

  /**
   * Execute actions before the developer gets saved.
   *
   * @param \Drupal\apigee_edge\Structure\UserToDeveloperConversionResult $result
   *   The result of the entity conversion.
   * @param \Drupal\user\UserInterface $user
   *   The converted user entity.
   *
   * @throws \Exception
   *   Can throw exception to abort developer save.
   */
  protected function beforeDeveloperSave(UserToDeveloperConversionResult $result, UserInterface $user) : void {
    $context = [
      'link' => $user->toLink(t('View user'))->toString(),
    ];
    $this->logConversionProblems($result->getProblems(), $context);
  }

  /**
   * Execute actions after the developer has been saved.
   *
   * Actions here always gets executed even if the developer save has failed.
   *
   * @param \Drupal\apigee_edge\Structure\UserToDeveloperConversionResult $result
   *   The result of the entity conversion.
   * @param \Drupal\user\UserInterface $user
   *   The converted user entity.
   */
  protected function afterDeveloperSave(UserToDeveloperConversionResult $result, UserInterface $user) : void {}

  /**
   * {@inheritdoc}
   */
  protected function logConversionProblem(UserDeveloperConversionException $problem, array $context = []) : void {
    $ro = new \ReflectionObject($this);
    $context = [
      '%mail' => $this->email,
      '@operation' => $ro->getShortName(),
    ];
    if ($problem instanceof UserDeveloperConversionUserFieldDoesNotExistException) {
      $message = "@operation: %mail developer's %attribute_name attribute has been skipped because %field_name field does not exist.";
      $context['%field_name'] = $problem->getFieldName();
      $context['%attribute_name'] = $this->fieldAttributeConverter()->getAttributeName($problem->getFieldName());
      $this->logger()->warning($message, $context);
      $this->recordMessage(t("%mail developer's %attribute_name attribute has been skipped because %field_name field does not exist.", $context)->render());
    }
    elseif ($problem instanceof UserDeveloperConversionNoStorageFormatterFoundException) {
      $message = "@operation: %mail developer's %attribute_name attribute has been skipped because there is no available storage formatter for %field_type field type.";
      $context['%field_type'] = $problem->getFieldDefinition()->getType();
      $context['%attribute_name'] = $this->fieldAttributeConverter()->getAttributeName($problem->getFieldDefinition()->getName());
      $this->logger()->warning($message, $context);
      $this->recordMessage(t("%mail developer's %attribute_name attribute has been skipped because there is no available storage formatter for %field_type field type.", $context)->render());
    }
    else {
      $context += Error::decodeException($problem);
      $this->logger()->warning('@operation: Unexpected problem occurred while creating %mail user: @message %function (line %line of %file). <pre>@backtrace_string</pre>');
      $this->recordMessage(t("Unexpected problem occurred while processing %mail developer: @message", $context)->render());
    }
  }

}
