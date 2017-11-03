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
 */

/**
 * Class to represent the actions that can be performed on a group of contacts used by the search forms.
 */
abstract class CRM_Core_Task {
  const
    REMOVE_CONTACTS = 2,
    TAG_CONTACTS = 3,
    REMOVE_TAGS = 4,
    EXPORT_CONTACTS = 5,
    EMAIL_CONTACTS = 6,
    SMS_CONTACTS = 7,
    DELETE_CONTACTS = 8,
    HOUSEHOLD_CONTACTS = 9,
    ORGANIZATION_CONTACTS = 10,
    RECORD_CONTACTS = 11,
    MAP_CONTACTS = 12,
    SAVE_SEARCH = 13,
    SAVE_SEARCH_UPDATE = 14,
    PRINT_CONTACTS = 15,
    LABEL_CONTACTS = 16,
    BATCH_UPDATE = 17,
    ADD_EVENT = 18,
    PRINT_FOR_CONTACTS = 19,
    CREATE_MAILING = 20,
    MERGE_CONTACTS = 21,
    EMAIL_UNHOLD = 22,
    RESTORE = 23,
    DELETE_PERMANENTLY = 24,
    COMMUNICATION_PREFS = 25,
    INDIVIDUAL_CONTACTS = 26,
    GROUP_CONTACTS = 27,
    DELETE_MEMBERS = 28,
    PRINT_MEMBERS = 29,
    EXPORT_MEMBERS = 30,
    EMAIL_MEMBERS = 31,
    BATCH_MEMBERS = 32,
    LABEL_MEMBERS = 33,
    PRINT_FOR_MEMBERS = 34,
    DELETE_EVENTS = 35,
    PRINT_EVENTS = 36,
    EXPORT_EVENTS = 37,
    BATCH_EVENTS = 38,
    CANCEL_REGISTRATION = 39,
    PARTICIPANT_STATUS = 40;

  /**
   * The task array
   *
   * @var array
   */
  static $_tasks = NULL;

  /**
   * The optional task array
   *
   * @var array
   */
  static $_optionalTasks = NULL;

  abstract public static function tasks();

  /**
   * These tasks are the core set of tasks that the user can perform
   * on a contact / group of contacts
   *
   * @return array
   *   the set of tasks for a group of contacts
   */
  public static function taskTitles() {
    static::tasks();

    $titles = array();
    foreach (self::$_tasks as $id => $value) {
      $titles[$id] = $value['title'];
    }

    // hack unset update saved search
    unset($titles[self::SAVE_SEARCH_UPDATE]);

    if (!CRM_Utils_Mail::validOutBoundMail()) {
      unset($titles[self::EMAIL_CONTACTS]);
      unset($titles[self::CREATE_MAILING]);
    }

    // CRM-6806
    if (!CRM_Core_Permission::check('access deleted contacts') ||
      !CRM_Core_Permission::check('delete contacts')
    ) {
      unset($titles[self::DELETE_PERMANENTLY]);
    }
    return $titles;
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
  abstract public static function permissionedTaskTitles($permission, $deletedContacts = FALSE);

  /**
   * These tasks get added based on the context the user is in.
   *
   * @return array
   *   the set of optional tasks for a group of contacts
   */
  public static function optionalTaskTitle() {
    $tasks = array(
      self::SAVE_SEARCH_UPDATE => self::$_tasks[self::SAVE_SEARCH_UPDATE]['title'],
    );
    return $tasks;
  }

  /**
   * @param $value
   *
   * @return array
   */
  public static function getTask($value) {
    static::tasks();

    if (!CRM_Utils_Array::value($value, self::$_tasks)) {
      // Children can specify a default task (eg. print), we don't here
      return array();
    }
    return array(
      CRM_Utils_Array::value('class', self::$_tasks[$value]),
      CRM_Utils_Array::value('result', self::$_tasks[$value]),
    );
  }

  /**
   * Function to return the task information on basis of provided task's form name
   *
   * @param string $className
   *
   * @return array
   */
  public static function getTaskAndTitleByClass($className) {
    static::tasks();

    foreach (self::$_tasks as $task => $value) {
      if (!empty($value['url']) && (
          (is_array($value['class']) && in_array($className, $value['class'])) ||
          ($value['class'] == $className)
        )
      ) {
        return array(
          $task,
          CRM_Utils_Array::value('title', $value),
        );
      }
    }
  }

}
