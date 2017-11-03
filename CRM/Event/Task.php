<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2017
 * $Id$
 *
 */

/**
 * class to represent the actions that can be performed on a group of contacts
 * used by the search forms
 *
 */
class CRM_Event_Task extends CRM_Core_Task {

  /**
   * These tasks are the core set of tasks that the user can perform
   * on a contact / group of contacts
   *
   * @return array
   *   the set of tasks for a group of contacts
   */
  public static function tasks() {
    self::$_tasks = array(
      self::DELETE_EVENTS => array(
        'title' => ts('Delete participants from event'),
        'class' => 'CRM_Event_Form_Task_Delete',
        'result' => FALSE,
      ),
      self::PRINT_EVENTS => array(
        'title' => ts('Print selected rows'),
        'class' => 'CRM_Event_Form_Task_Print',
        'result' => FALSE,
      ),
      self::EXPORT_EVENTS => array(
        'title' => ts('Export participants'),
        'class' => array(
          'CRM_Export_Form_Select',
          'CRM_Export_Form_Map',
        ),
        'result' => FALSE,
      ),
      self::BATCH_EVENTS => array(
        'title' => ts('Update multiple participants'),
        'class' => array(
          'CRM_Event_Form_Task_PickProfile',
          'CRM_Event_Form_Task_Batch',
        ),
        'result' => TRUE,
      ),
      self::CANCEL_REGISTRATION => array(
        'title' => ts('Cancel registration'),
        'class' => 'CRM_Event_Form_Task_Cancel',
        'result' => FALSE,
      ),
      self::EMAIL_CONTACTS => array(
        'title' => ts('Email - send now'),
        'class' => 'CRM_Event_Form_Task_Email',
        'result' => TRUE,
      ),
      self::SAVE_SEARCH => array(
        'title' => ts('Group - create smart group'),
        'class' => 'CRM_Event_Form_Task_SaveSearch',
        'result' => TRUE,
      ),
      self::SAVE_SEARCH_UPDATE => array(
        'title' => ts('Group - update smart group'),
        'class' => 'CRM_Event_Form_Task_SaveSearch_Update',
        'result' => TRUE,
      ),
      self::PARTICIPANT_STATUS => array(
        'title' => ts('Participant status - change'),
        'class' => 'CRM_Event_Form_Task_ParticipantStatus',
        'result' => TRUE,
      ),
      self::LABEL_CONTACTS => array(
        'title' => ts('Name badges - print'),
        'class' => 'CRM_Event_Form_Task_Badge',
        'result' => FALSE,
      ),
      self::PRINT_FOR_CONTACTS => array(
        'title' => ts('PDF letter - print for participants'),
        'class' => 'CRM_Event_Form_Task_PDF',
        'result' => TRUE,
      ),
      self::GROUP_CONTACTS => array(
        'title' => ts('Group - add contacts'),
        'class' => 'CRM_Event_Form_Task_AddToGroup',
        'result' => FALSE,
      ),
    );

    //CRM-4418, check for delete
    if (!CRM_Core_Permission::check('delete in CiviEvent')) {
      unset(self::$_tasks[self::DELETE_EVENTS]);
    }
    //CRM-12920 - check for edit permission
    if (!CRM_Core_Permission::check('edit event participants')) {
      unset(self::$_tasks[self::BATCH_EVENTS], self::$_tasks[self::CANCEL_REGISTRATION], self::$_tasks[self::PARTICIPANT_STATUS]);
    }

    CRM_Utils_Hook::searchTasks('event', self::$_tasks);
    asort(self::$_tasks);

    return self::$_tasks;
  }

  /**
   * Show tasks selectively based on the permission level
   * of the user
   *
   * @param int $permission
   * @param bool $deletedContacts
   *   Are these tasks for operating on deleted contacts?.
   *
   * @return array
   *   set of tasks that are valid for the user
   */
  public static function permissionedTaskTitles($permission, $deletedContacts = FALSE) {
    $tasks = array();
    if (($permission == CRM_Core_Permission::EDIT)
      || CRM_Core_Permission::check('edit event participants')
    ) {
      $tasks = self::taskTitles();
    }
    else {
      $tasks = array(
        self::EXPORT_EVENTS => self::$_tasks[self::EXPORT_EVENTS]['title'],
        self::EMAIL_CONTACTS => self::$_tasks[self::EMAIL_CONTACTS]['title'],
      );

      //CRM-4418,
      if (CRM_Core_Permission::check('delete in CiviEvent')) {
        $tasks[self::DELETE_EVENTS] = self::$_tasks[self::DELETE_EVENTS]['title'];
      }
    }
    return $tasks;
  }

  /**
   * These tasks are the core set of tasks that the user can perform
   * on participants
   *
   * @param int $value
   *
   * @return array
   *   the set of tasks for a group of participants
   */
  public static function getTask($value) {
    self::tasks();
    if (!$value || !CRM_Utils_Array::value($value, self::$_tasks)) {
      // make the print task by default
      $value = self::PRINT_EVENTS;
    }
    return parent::getTask($value);
  }

}
