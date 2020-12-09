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

namespace Drupal\apigee_edge_teams;

use Drupal\apigee_edge_teams\Entity\TeamInvitationInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Handles notifications for team_invitation via email.
 */
class TeamInvitationNotifierEmail implements TeamInvitationNotifierInterface {

  /**
   * The mail service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * TeamInvitationNotifierEmail constructor.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager) {
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function sendNotificationsFor(TeamInvitationInterface $team_invitation): bool {
    $email = $team_invitation->getRecipient();
    $langcode = $this->languageManager->getDefaultLanguage()->getId();

    $params = [
      'team_invitation' => $team_invitation,
      'user' => NULL,
    ];

    /** @var \Drupal\user\UserInterface $user */
    $user = user_load_by_mail($email);
    if ($user) {
      $langcode = $user->getPreferredLangcode();
      $params['user'] = $user;
    }

    // Send email notification.
    $message = $this->mailManager->mail('apigee_edge_teams', 'team_invitation_created', $email, $langcode, $params);
    return $message['result'];
  }

}
