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

namespace Drupal\apigee_edge_teams\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining invitation entities.
 */
interface TeamInvitationInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Invitation is expired.
   */
  const STATUS_EXPIRED = -1;

  /**
   * Invitation is sent and pending.
   */
  const STATUS_PENDING = 0;

  /**
   * Invitation is accepted.
   */
  const STATUS_ACCEPTED = 1;

  /**
   * Invitation is declined.
   */
  const STATUS_DECLINED = 2;

  /**
   * Returns the label for this invitation.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Sets the invitation label.
   *
   * @param string $label
   *   The invitation label.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface
   *   The invitation.
   */
  public function setLabel(string $label): self;

  /**
   * Returns the team entity.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInterface|null
   *   The team entity or null.
   */
  public function getTeam(): ?TeamInterface;

  /**
   * Sets the team of the invitation.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team entity.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface
   *   The invitation.
   */
  public function setTeam(TeamInterface $team): self;

  /**
   * Returns the team roles.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamRoleInterface[]|null
   *   The team roles or null.
   */
  public function getTeamRoles(): ?array;

  /**
   * Sets the team roles of the invitation.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamRoleInterface[] $team_roles
   *   An array of team roles.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface
   *   The invitation.
   */
  public function setTeamRoles(array $team_roles): self;

  /**
   * Returns the status of the invitation.
   *
   * @return int
   *   The invitation status.
   */
  public function getStatus(): int;

  /**
   * Sets the status of the invitation.
   *
   * @param int $status
   *   The status.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface
   *   The invitation.
   */
  public function setStatus(int $status): self;

  /**
   * Returns the recipient email for an invitation.
   *
   * @return string
   *   The recipient email.
   */
  public function getRecipient(): ?string;

  /**
   * Sets the recipient of the invitation.
   *
   * @param string $email
   *   The recipient email.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface
   *   The invitation.
   */
  public function setRecipient(string $email): self;

  /**
   * Returns the creation date for an invitation.
   *
   * @return int
   *   Timestamp for the invitation creation date.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the expiry time of the invitation.
   *
   * @param int $expiry_time
   *   The expiry time.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface
   *   The invitation.
   */
  public function setExpiryTime(int $expiry_time): self;

  /**
   * Returns the expiry time for an invitation.
   *
   * @return int
   *   Timestamp for the invitation expiry date.
   */
  public function getExpiryTime(): int;

  /**
   * Returns TRUE if the invitation is expired.
   *
   * @return bool
   *   TRUE if expired. FALSE otherwise.
   */
  public function isExpired(): bool;

  /**
   * Returns TRUE if the invitation is pending.
   *
   * @return bool
   *   TRUE if pending. FALSE otherwise.
   */
  public function isPending(): bool;

  /**
   * Returns TRUE if the invitation is accepted.
   *
   * @return bool
   *   TRUE if accepted. FALSE otherwise.
   */
  public function isAccepted(): bool;

  /**
   * Returns TRUE if the invitation is declined.
   *
   * @return bool
   *   TRUE if declined. FALSE otherwise.
   */
  public function isDeclined(): bool;

}
