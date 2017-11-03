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
 * Class to represent the actions that can be performed on a group of contacts.
 *
 * Used by the search forms
 */
class CRM_Case_Task extends CRM_Core_Task {

  /**
   * These tasks are the core set of tasks that the user can perform
   * on a contact / group of contacts
   *
   * @return array
   *   the set of tasks for a group of contacts
   */
  public static function tasks() {
    if (!self::$_tasks) {
      self::$_tasks = array(
        self::DELETE_CASES => array(
          'title' => ts('Delete cases'),
          'class' => 'CRM_Case_Form_Task_Delete',
          'result' => FALSE,
        ),
        self::PRINT_CASES => array(
          'title' => ts('Print selected rows'),
          'class' => 'CRM_Case_Form_Task_Print',
          'result' => FALSE,
        ),
        self::EXPORT_CASES => array(
          'title' => ts('Export cases'),
          'class' => array(
            'CRM_Export_Form_Select',
            'CRM_Export_Form_Map',
          ),
          'result' => FALSE,
        ),
        self::RESTORE_CASES => array(
          'title' => ts('Restore cases'),
          'class' => 'CRM_Case_Form_Task_Restore',
          'result' => FALSE,
        ),
        self::PDF_LETTER => array(
          'title' => ts('Print/merge Document'),
          'class' => 'CRM_Case_Form_Task_PDF',
          'result' => FALSE,
        ),
      );

      //CRM-4418, check for delete
      if (!CRM_Core_Permission::check('delete in CiviCase')) {
        unset(self::$_tasks[self::DELETE_CASES]);
      }

      CRM_Utils_Hook::searchTasks('case', self::$_tasks);
      asort(self::$_tasks);
    }

    return self::$_tasks;
  }

  /**
   * Show tasks selectively based on the permission level.
   * of the user
   *
   * @param int $permission
   * @param array $params
   *
   * @return array
   *   set of tasks that are valid for the user
   */
  public static function permissionedTaskTitles($permission, $params = array()) {
    if (($permission == CRM_Core_Permission::EDIT)
      || CRM_Core_Permission::check('access all cases and activities')
      || CRM_Core_Permission::check('access my cases and activities')
    ) {
      $tasks = self::taskTitles();
    }
    else {
      $tasks = array(
        self::EXPORT_CASES => self::$_tasks[self::EXPORT_CASES]['title'],
      );
      //CRM-4418,
      if (CRM_Core_Permission::check('delete in CiviCase')) {
        $tasks[self::DELETE_CASES] = self::$_tasks[self::DELETE_CASES]['title'];
      }
    }

    $tasks = parent::corePermissionedTaskTitles($tasks, $permission, $params);
    return $tasks;
  }

  /**
   * These tasks are the core set of tasks.
   *
   * @param int $value
   *
   * @return array
   *   the set of tasks for a group of contacts
   */
  public static function getTask($value) {
    self::tasks();
    if (!$value || !CRM_Utils_Array::value($value, self::$_tasks)) {
      // make the print task by default
      $value = self::PRINT_CASES;
    }

    return array(
      self::$_tasks[$value]['class'],
      self::$_tasks[$value]['result'],
    );
  }

}
