<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

use Civi\ActionSchedule\Event\MailingQueryEvent;
use Civi\Token\Event\TokenValueEvent;
use Civi\Token\TokenRow;

/**
 * Generate "activity.*" tokens.
 */
class CRM_Activity_Tokens extends CRM_Core_EntityTokens {

  /**
   * Get the entity name for api v4 calls.
   *
   * @return string
   */
  protected function getApiEntityName(): string {
    return 'Activity';
  }

  /**
   * @inheritDoc
   */
  public function alterActionScheduleQuery(MailingQueryEvent $e): void {
    if ($e->mapping->getEntityTable($e->actionSchedule) !== $this->getExtendableTableName()) {
      return;
    }

    // The joint expression for activities needs some extra nuance to handle.
    // Multiple revisions of the activity.
    // Q: Could we simplify & move the extra AND clauses into `where(...)`?
    $e->query->param('casEntityJoinExpr', 'e.id = reminder.entity_id AND e.is_current_revision = 1 AND e.is_deleted = 0');
    parent::alterActionScheduleQuery($e);
  }

  /**
   * @inheritDoc
   */
  public function prefetch(TokenValueEvent $e): ?array {
    $entityIDs = $e->getTokenProcessor()->getContextValues($this->getEntityIDField());
    if (empty($entityIDs)) {
      return [];
    }
    $prefetch = parent::prefetch($e);

    // Get data for special tokens
    $activityContacts = $contacts = [];
    // See if we need activity contacts
    $needContacts = FALSE;
    foreach ($this->activeTokens as $token) {
      if (preg_match('/^source|target|assignee/', $token)) {
        $needContacts = TRUE;
        break;
      }
    }

    // If we need ActivityContacts, load them
    if ($needContacts) {
      $activityContactsResult = \Civi\Api4\ActivityContact::get(FALSE)
        ->addWhere('activity_id', 'IN', $entityIDs)
        ->execute();
      $contactIds = [];
      $types = ['1' => 'assignee', '2' => 'source', '3' => 'target'];
      foreach ($activityContactsResult as $ac) {
        if ($ac['record_type_id'] == 2) {
          $activityContacts[$ac['activity_id']][$types[$ac['record_type_id']]] = $ac['contact_id'];
        }
        else {
          $activityContacts[$ac['activity_id']][$types[$ac['record_type_id']]][] = $ac['contact_id'];
        }
        $contactIds[$ac['contact_id']] = 1;
      }
      // @TODO only return the wanted fields
      // maybe use CRM_Contact_Tokens::prefetch() ?
      $contacts = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('*', 'email_primary.email', 'phone_primary.phone')
        ->addWhere('id', 'IN', array_keys($contactIds))
        ->execute()
        ->indexBy('id');
    }

    $prefetch['activityContact'] = $activityContacts;
    $prefetch['contact'] = $contacts;

    return $prefetch;
  }

  /**
   * Evaluate the content of a single token.
   *
   * @param \Civi\Token\TokenRow $row
   *   The record for which we want token values.
   * @param string $entity
   *   The name of the token entity.
   * @param string $field
   *   The name of the token field.
   * @param mixed $prefetch
   *   Any data that was returned by the prefetch().
   *
   * @throws \CRM_Core_Exception
   */
  public function evaluateToken(TokenRow $row, $entity, $field, $prefetch = NULL) {
    $activityId = $this->getFieldValue($row, 'id');

    if (!empty($this->getDeprecatedTokens()[$field])) {
      $realField = $this->getDeprecatedTokens()[$field];
      parent::evaluateToken($row, $entity, $realField, $prefetch);
      $row->format('text/plain')->tokens($entity, $field, $row->tokens['activity'][$realField]);
    }
    elseif ($field === 'case_id') {
      // An activity can be linked to multiple cases so case_id is always an array.
      // We just return the first case ID for the token.
      // this weird hack might exist because apiv3 is weird &
      $caseID = CRM_Core_DAO::singleValueQuery('SELECT case_id FROM civicrm_case_activity WHERE activity_id = %1 LIMIT 1', [1 => [$activityId, 'Integer']]);
      $row->tokens($entity, $field, $caseID ?? '');
    }
    elseif (preg_match('/^(target|assignee)_count/', $field, $match)) {
      $row->tokens($entity, $field, count($prefetch['activityContact'][$activityId][$match[1]] ?? []));
    }
    elseif (preg_match('/^(target|assignee|source)_/', $field, $match)) {
      if ($match[1] == 'source') {
        // There is only one source_contact for an activity
        [$activityContactType, $contactFieldName] = explode('_', $field, 2);
        $contactId = $prefetch['activityContact'][$activityId][$activityContactType] ?? NULL;
      }
      else {
        // There can be multiple assignee/target contacts
        // Can be used eg. {activity.target_N_display_name} to retrieve the "first" target contact display_name.
        // Or the N can be replaced with a number between 1 and count of assignees/targets to return a specific contact
        //   (but note that the order of assignee/target contacts is not guaranteed)
        [$activityContactType, $activityContactIndex, $contactFieldName] = explode('_', $field, 3);
        $contactIds = $prefetch['activityContact'][$activityId][$activityContactType] ?? NULL;
        $selectedId = (int) $activityContactIndex > 0 ? $activityContactIndex - 1 : 0;
        $contactId = $contactIds[$selectedId] ?? NULL;
      }
      $contact = $prefetch['contact'][$contactId] ?? NULL;
      if (!$contact) {
        $row->tokens($entity, $field, '');
      }
      else {
        // This is OK for simple tokens, but would be better for this to be handled by
        // CRM_Contact_Tokens ... but that doesn't exist yet.
        if (array_key_exists($contactFieldName, $contact)) {
          $row->tokens($entity, $field, $contact[$contactFieldName]);
        }
        else {
          \Civi::log()->warning('CRM_Activity_Tokens: Contact token "' . $contactFieldName . '" not found. Token: ' . $field);
        }
      }
    }
    else {
      parent::evaluateToken($row, $entity, $field, $prefetch);
    }
  }

  /**
   * Get tokens that are special or calculated for this entity.
   *
   * @return array|array[]
   */
  protected function getBespokeTokens(): array {
    $tokens = [];
    if (CRM_Core_Component::isEnabled('CiviCase')) {
      $tokens['case_id'] = [
        'title' => ts('Activity Case ID'),
        'name' => 'case_id',
        'type' => 'calculated',
        'options' => NULL,
        'data_type' => 'Integer',
        'audience' => 'user',
      ];
    }

    $tokenProcessor = new \Civi\Token\TokenProcessor(Civi::dispatcher(), ['schema' => ['contactId']]);
    $allTokens = $tokenProcessor->listTokens();
    foreach (array_keys($allTokens) as $token) {
      if (strpos($token, '{domain.') === 0) {
        unset($allTokens[$token]);
      }
    }
    $contactTokens = $allTokens;

    foreach ($contactTokens as $label => $name) {
      $match = [];
      if (preg_match('/{contact\.(.*)}/', $label, $match)) {
        $tokens['source_' . $match[1]] = [
          'title' => ts('%1 (Added By)', [1 => $name]),
          'name' => 'source_' . $match[1],
          'type' => 'calculated',
          'data_type' => 'String',
          'audience' => 'user',
        ];
        $tokens['target_N_' . $match[1]] = [
          'title' => ts('%1 (With Contact N)', [1 => $name]),
          'name' => 'target_N_' . $match[1],
          'type' => 'calculated',
          'data_type' => 'String',
          'audience' => 'user',
        ];
        $tokens['assignee_N_' . $match[1]] = [
          'title' => ts('%1 (Assignee N)', [1 => $name]),
          'name' => 'assignee_N_' . $match[1],
          'type' => 'calculated',
          'data_type' => 'String',
          'audience' => 'user',
        ];
        $tokens['target_count'] = [
          'title' => ts('Target Count'),
          'name' => 'target_count',
          'type' => 'calculated',
          'data_type' => 'Integer',
          'audience' => 'user',
        ];
        $tokens['assignee_count'] = [
          'title' => ts('Assignee Count'),
          'name' => 'assignee_count',
          'type' => 'calculated',
          'data_type' => 'Integer',
          'audience' => 'user',
        ];
        for ($count = 0; $count<10; $count++) {
          $tokens["target_{$count}_" . $match[1]] = [
            'title' => ts('%1 (With Contact %2)', [1 => $name, 2 => $count]),
            'name' => "target_{$count}_" . $match[1],
            'type' => 'calculated',
            'data_type' => 'String',
            'audience' => 'sysadmin',
          ];
          $tokens["assignee_{$count}_" . $match[1]] = [
            'title' => ts('%1 (Assignee %2)', [1 => $name, 2 => $count]),
            'name' => "assignee_{$count}_" . $match[1],
            'type' => 'calculated',
            'data_type' => 'String',
            'audience' => 'sysadmin',
          ];
        }

      }
    }

    return $tokens;
  }

  /**
   * Get fields historically not advertised for tokens.
   *
   * @return string[]
   */
  protected function getSkippedFields(): array {
    return array_merge(parent::getSkippedFields(), [
      'source_record_id',
      'phone_id',
      'phone_number',
      'priority_id',
      'parent_id',
      'is_test',
      'medium_id',
      'is_auto',
      'relationship_id',
      'is_current_revision',
      'original_id',
      'result',
      'is_deleted',
      'engagement_level',
      'weight',
      'is_star',
    ]);
  }

  /**
   * @inheritDoc
   */
  public function getActiveTokens(TokenValueEvent $e) {
    $messageTokens = $e->getTokenProcessor()->getMessageTokens();
    if (!isset($messageTokens[$this->entity])) {
      return NULL;
    }

    $activeTokens = [];
    foreach ($messageTokens[$this->entity] as $msgToken) {
      if (array_key_exists($msgToken, $this->getTokenMetadata())) {
        $activeTokens[] = $msgToken;
      }
      // case_id is probably set in metadata anyway.
      elseif ($msgToken === 'case_id' || isset($this->getDeprecatedTokens()[$msgToken])) {
        $activeTokens[] = $msgToken;
      }
    }
    return array_unique($activeTokens);
  }

  public function getPrefetchFields(TokenValueEvent $e): array {
    $tokens = parent::getPrefetchFields($e);
    $active = $this->getActiveTokens($e);
    foreach ($this->getDeprecatedTokens() as $old => $new) {
      if (in_array($old, $active, TRUE) && !in_array($new, $active, TRUE)) {
        $tokens[] = $new;
      }
    }
    return $tokens;
  }

  /**
   * These tokens still work but we don't advertise them.
   *
   * We will actively remove from the following places
   * - scheduled reminders
   * - add to 'blocked' on pdf letter & email
   *
   * & then at some point start issuing warnings for them.
   *
   * @return string[]
   */
  protected function getDeprecatedTokens(): array {
    return [
      'activity_id' => 'id',
      'activity_type' => 'activity_type_id:label',
      'status' => 'status_id:label',
      'campaign' => 'campaign_id:label',
    ];
  }

}
