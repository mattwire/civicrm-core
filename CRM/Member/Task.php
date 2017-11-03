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
 * class to represent the actions that can be performed on a group of
 * contacts (CiviMember)
 * used by the search forms
 *
 */
class CRM_Member_Task extends CRM_Core_Task {

  /**
   * These tasks are the core set of tasks that the user can perform
   * on a contact / group of contacts
   *
   * @return array
   *   the set of tasks for a group of contacts
   */
  public static function tasks() {
    self::$_tasks = array(
      self::DELETE_MEMBERS => array(
        'title' => ts('Delete memberships'),
        'class' => 'CRM_Member_Form_Task_Delete',
        'result' => FALSE,
      ),
      self::PRINT_MEMBERS => array(
        'title' => ts('Print selected rows'),
        'class' => 'CRM_Member_Form_Task_Print',
        'result' => FALSE,
      ),
      self::EXPORT_MEMBERS => array(
        'title' => ts('Export members'),
        'class' => array(
          'CRM_Export_Form_Select',
          'CRM_Export_Form_Map',
        ),
        'result' => FALSE,
      ),
      self::EMAIL_MEMBERS => array(
        'title' => ts('Email - send now'),
        'class' => 'CRM_Member_Form_Task_Email',
        'result' => TRUE,
      ),
      self::BATCH_MEMBERS => array(
        'title' => ts('Update multiple memberships'),
        'class' => array(
          'CRM_Member_Form_Task_PickProfile',
          'CRM_Member_Form_Task_Batch',
        ),
        'result' => TRUE,
      ),
      self::LABEL_MEMBERS => array(
        'title' => ts('Mailing labels - print'),
        'class' => array(
          'CRM_Member_Form_Task_Label',
        ),
        'result' => TRUE,
      ),
      self::PRINT_FOR_MEMBERS => array(
        'title' => ts('Print/merge document for memberships'),
        'class' => 'CRM_Member_Form_Task_PDFLetter',
        'result' => FALSE,
      ),
      self::SAVE_SEARCH => array(
        'title' => ts('Group - create smart group'),
        'class' => 'CRM_Contact_Form_Task_SaveSearch',
        'result' => TRUE,
      ),
      self::SAVE_SEARCH_UPDATE => array(
        'title' => ts('Group - update smart group'),
        'class' => 'CRM_Contact_Form_Task_SaveSearch_Update',
        'result' => TRUE,
      ),
    );

    //CRM-4418, check for delete
    if (!CRM_Core_Permission::check('delete in CiviMember')) {
      unset(self::$_tasks[self::DELETE_MEMBERS]);
    }
    //CRM-12920 - check for edit permission
    if (!CRM_Core_Permission::check('edit memberships')) {
      unset(self::$_tasks[self::BATCH_MEMBERS]);
    }

    CRM_Utils_Hook::searchTasks('membership', self::$_tasks);
    asort(self::$_tasks);

    return self::$_tasks;
  }

  /**
   * These tasks are the core set of task titles
   * on members
   *
   * @return array
   *   the set of task titles
   */
  public static function taskTitles() {
    return parent::taskTitles();
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
      || CRM_Core_Permission::check('edit memberships')
    ) {
      $tasks = self::taskTitles();
    }
    else {
      $tasks = array(
        self::EXPORT_MEMBERS => self::$_tasks[self::EXPORT_MEMBERS]['title'],
        self::EMAIL_MEMBERS => self::$_tasks[self::EMAIL_MEMBERS]['title'],
      );
      //CRM-4418,
      if (CRM_Core_Permission::check('delete in CiviMember')) {
        $tasks[self::DELETE_MEMBERS] = self::$_tasks[self::DELETE_MEMBERS]['title'];
      }
    }
    return $tasks;
  }

  /**
   * These tasks are the core set of tasks that the user can perform
   * on members
   *
   * @param int $value
   *
   * @return array
   *   the set of tasks for a group of members
   */
  public static function getTask($value) {
    self::tasks();
    if (!$value || !CRM_Utils_Array::value($value, self::$_tasks)) {
      // Make the print task the default
      $value = self::PRINT_MEMBERS;
    }
    return parent::getTask($value);
  }

}
