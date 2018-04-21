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

use Civi\Token\AbstractTokenSubscriber;
use Civi\Token\Event\TokenValueEvent;
use Civi\Token\TokenRow;

/**
 * Class CRM_Member_Tokens
 *
 * Generate "activity.*" tokens.
 *
 * This TokenSubscriber was originally produced by refactoring the code from the
 * scheduled-reminder system with the goal of making that system
 * more flexible. The current implementation is still coupled to
 * scheduled-reminders. It would be good to figure out a more generic
 * implementation which is not tied to scheduled reminders, although
 * that is outside the current scope.
 *
 * This has been enhanced to work with PDF/letter merge
 */
class CRM_Activity_Tokens extends AbstractTokenSubscriber {

  use CRM_Core_TokenTrait;

  /**
   * @return string
   */
  private function getEntityName(): string {
    return 'activity';
  }

  /**
   * @return string
   */
  private function getEntityTableName(): string {
    return 'civicrm_activity';
  }

  /**
   * @return string
   */
  private function getEntityContextSchema(): string {
    return 'activityId';
  }

  /**
   * Mapping from tokenName to api return field
   * Using arrays allows more complex tokens to be handled that require more than one API field.
   * For example, an address token might want ['street_address', 'city', 'postal_code']
   *
   * @var array
   */
  private static $fieldMapping = [
    'activity_id' => ['id'],
    'activity_type' => ['activity_type_id'],
    'status' => ['status_id'],
    'campaign' => ['campaign_id'],
  ];

  /**
   * @inheritDoc
   */
  public function alterActionScheduleQuery(\Civi\ActionSchedule\Event\MailingQueryEvent $e) {
    if ($e->mapping->getEntity() !== $this->getEntityTableName()) {
      return;
    }

    // The joint expression for activities needs some extra nuance to handle.
    // Multiple revisions of the activity.
    // Q: Could we simplify & move the extra AND clauses into `where(...)`?
    $e->query->param('casEntityJoinExpr', 'e.id = reminder.entity_id AND e.is_current_revision = 1 AND e.is_deleted = 0');
  }

  /**
   * @inheritDoc
   */
  public function prefetch(TokenValueEvent $e) {
    // Find all the entity IDs
    $entityIds
      = $e->getTokenProcessor()->getContextValues('actionSearchResult', 'entityID')
      + $e->getTokenProcessor()->getContextValues($this->getEntityContextSchema());

    if (!$entityIds) {
      return NULL;
    }

    // Get data on all activities for basic and customfield tokens
    $prefetch['activity'] = civicrm_api3('Activity', 'get', [
      'id' => ['IN' => $entityIds],
      'options' => ['limit' => 0],
      'return' => self::getReturnFields($this->activeTokens),
    ])['values'];

    // Get data for special tokens
    list($prefetch['activityContact'], $prefetch['contact'])
      = self::prefetchSpecialTokens($this->activeTokens, $entityIds);

    // Store the activity types if needed
    if (in_array('activity_type', $this->activeTokens, TRUE)) {
      $this->activityTypes = \CRM_Core_OptionGroup::values('activity_type');
    }

    // Store the activity statuses if needed
    if (in_array('status', $this->activeTokens, TRUE)) {
      $this->activityStatuses = \CRM_Core_OptionGroup::values('activity_status');
    }

    // Store the campaigns if needed
    if (in_array('campaign', $this->activeTokens, TRUE)) {
      $this->campaigns = \CRM_Campaign_BAO_Campaign::getCampaigns();
    }

    return $prefetch;
  }

  /**
   * Do the prefetch for the special tokens
   *
   * @param  array $activeTokens The list of active tokens
   * @param  array $entityIds  list of activity ids
   *
   * @return array               the prefetched data for these tokens
   * @throws \CiviCRM_API3_Exception
   */
  public function prefetchSpecialTokens($activeTokens, $entityIds) {
    $activityContacts = $contacts = [];
    // See if we need activity contacts
    $needContacts = FALSE;
    foreach ($activeTokens as $token) {
      if (preg_match('/^source|target|assignee/', $token)) {
        $needContacts = TRUE;
        break;
      }
    }

    // If we need ActivityContacts, load them
    if ($needContacts) {
      $result = civicrm_api3('ActivityContact', 'get', [
        'sequential' => 1,
        'activity_id' => ['IN' => $entityIds],
        'options' => ['limit' => 0],
      ]);
      $contactIds = [];
      $types = ['1' => 'assignee', '2' => 'source', '3' => 'target'];
      foreach ($result['values'] as $ac) {
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
      $result = civicrm_api3('Contact', 'get', [
        'id' => ['IN' => array_keys($contactIds)],
        'options' => ['limit' => 0],
      ]);
      $contacts = $result['values'];
    }
    return [$activityContacts, $contacts];
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
    // maps token name to api field
    $mapping = [
      'activity_id' => 'id',
    ];

    // Get ActivityID either from actionSearchResult (for scheduled reminders) if exists
    $activityId = $row->context['actionSearchResult']->entityID ?? $row->context[$this->getEntityContextSchema()];

    $activity = $prefetch['activity'][$activityId];

    if (in_array($field, ['activity_date_time', 'created_date', 'modified_date'])) {
      $row->tokens($entity, $field, \CRM_Utils_Date::customFormat($activity[$field]));
    }
    elseif (isset($mapping[$field]) and (isset($activity[$mapping[$field]]))) {
      $row->tokens($entity, $field, $activity[$mapping[$field]]);
    }
    elseif (in_array($field, ['activity_type'])) {
      $row->tokens($entity, $field, $this->activityTypes[$activity['activity_type_id']]);
    }
    elseif (in_array($field, ['status'])) {
      $row->tokens($entity, $field, $this->activityStatuses[$activity['status_id']]);
    }
    elseif (in_array($field, ['campaign'])) {
      $row->tokens($entity, $field, $this->campaigns[$activity['campaign_id']]);
    }
    elseif (in_array($field, ['case_id'])) {
      // An activity can be linked to multiple cases so case_id is always an array.
      // We just return the first case ID for the token.
      $row->tokens($entity, $field, is_array($activity['case_id']) ? reset($activity['case_id']) : $activity['case_id']);
    }
    elseif (array_key_exists($field, $this->customFieldTokens)) {
      $row->tokens($entity, $field,
        isset($activity[$field])
          ? \CRM_Core_BAO_CustomField::displayValue($activity[$field], $field)
          : ''
      );
    }
    elseif (isset($activity[$field])) {
      $row->tokens($entity, $field, $activity[$field]);
    }
    elseif (preg_match('/^(target|assignee)_count/', $field, $match)) {
      $row->tokens($entity, $field, count($prefetch['activityContact'][$activity['id']][$match[1]] ?? []));
    }
    elseif (preg_match('/^(target|assignee|source)_/', $field, $match)) {
      if ($match[1] == 'source') {
        // There is only one source_contact for an activity
        [$activityContactType, $contactFieldName] = explode('_', $field, 2);
        $contactId = $prefetch['activityContact'][$activity['id']][$activityContactType] ?? NULL;
      }
      else {
        // There can be multiple assignee/target contacts
        // Can be used eg. {activity.target_N_display_name} to retrieve the "first" target contact display_name.
        // Or the N can be replaced with a number between 1 and count of assignees/targets to return a specific contact
        //   (but note that the order of assignee/target contacts is not guaranteed)
        [$activityContactType, $activityContactIndex, $contactFieldName] = explode('_', $field, 3);
        $contactIds = $prefetch['activityContact'][$activity['id']][$activityContactType] ?? NULL;
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
        $row->tokens($entity, $field, $contact[$contactFieldName]);
      }
    }
  }

  /**
   * Get the basic tokens provided.
   *
   * @return array token name => token label
   */
  protected function getBasicTokens(): array {
    if (!isset($this->basicTokens)) {
      $this->basicTokens = [
        'activity_id' => ts('Activity ID'),
        'activity_type' => ts('Activity Type'),
        'subject' => ts('Activity Subject'),
        'details' => ts('Activity Details'),
        'activity_date_time' => ts('Activity Date-Time'),
        'created_date' => ts('Activity Created Date'),
        'modified_date' => ts('Activity Modified Date'),
        'activity_type_id' => ts('Activity Type ID'),
        'status' => ts('Activity Status'),
        'status_id' => ts('Activity Status ID'),
        'location' => ts('Activity Location'),
        'duration' => ts('Activity Duration'),
        'campaign' => ts('Activity Campaign'),
        'campaign_id' => ts('Activity Campaign ID'),
        'target_count' => ts('Count of Activity Targets'),
        'assignee_count' => ts('Count of Activity Assignees'),
      ];
      if (array_key_exists('CiviCase', CRM_Core_Component::getEnabledComponents())) {
        $this->basicTokens['case_id'] = ts('Activity Case ID');
      }
    }
    return $this->basicTokens;
  }

  /**
   * Get the special tokens - ie tokens that need special handling
   * @return array token name => token label
   */
  protected function getSpecialTokens() {
    if (!isset($this->specialTokens)) {
      $this->specialTokens = [];
      foreach (\CRM_Core_SelectValues::contactTokens() as $label => $name) {
        $match = [];
        if (preg_match('/{contact\.(.*)}/', $label, $match)) {
          $this->specialTokens['source_' . $match[1]] = ts('%1 (Added By)', [1 => $name]);
          $this->specialTokens['target_N_' . $match[1]] = ts('%1 (With Contact N)', [1 => $name]);
          $this->specialTokens['assignee_N_' . $match[1]] = ts('%1 (Assignee N)', [1 => $name]);
        }
      }
    }
    return $this->specialTokens;
  }

}
