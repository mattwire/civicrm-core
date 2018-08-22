<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2018                                |
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
 * @copyright CiviCRM LLC (c) 2004-2018
 */

/**
 * This class generates form components for the display preferences.
 */
class CRM_Admin_Form_Preferences_Display extends CRM_Admin_Form_Preferences {
  public function preProcess() {
    CRM_Utils_System::setTitle(ts('Settings - Display Preferences'));
    $optionValues = CRM_Activity_BAO_Activity::buildOptions('activity_type_id');

    $settings = civicrm_api3('Setting', 'getfields', [
      'filters' => ['group' => 'core'],
    ]);
    $settings = CRM_Utils_Array::value('values', $settings, []);

    foreach ($settings as $settingKey => $settingValues) {
      if (empty($settingValues['weight'])) {
        // Weight is not used for display preferences form
        $settingValues['weight'] = 1;
      }
      switch ($settingKey) {
        case 'contact_ajax_check_similar':
          // This is overridden in the template
          $settingValues['html_type'] = NULL;
          break;

        case 'do_not_notify_assignees_for':
          // TODO: Replace with pseudoconstant handling
          $settingValues['option_values'] = $optionValues;
          break;
      }
      $this->_varNames[$settingValues['group_name']][$settingKey] = $settingValues;
    }

    parent::preProcess();
  }

  /**
   * @return array
   */
  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();
    parent::cbsDefaultValues($defaults);

    if ($this->_config->display_name_format) {
      $defaults['display_name_format'] = $this->_config->display_name_format;
    }
    if ($this->_config->sort_name_format) {
      $defaults['sort_name_format'] = $this->_config->sort_name_format;
    }

    return $defaults;
  }

  /**
   * Build the form object.
   */
  public function buildQuickForm() {
    $wysiwyg_options = CRM_Core_OptionGroup::values('wysiwyg_editor', FALSE, FALSE, FALSE, NULL, 'label', TRUE, FALSE, 'name');

    //changes for freezing the invoices/credit notes checkbox if invoicing is uncheck
    $invoiceSettings = Civi::settings()->get('contribution_invoice_settings');
    $invoicing = CRM_Utils_Array::value('invoicing', $invoiceSettings);
    $this->assign('invoicing', $invoicing);
    $extra = array();

    $this->addElement('select', 'editor_id', ts('WYSIWYG Editor'), $wysiwyg_options, $extra);
    $this->addElement('submit', 'ckeditor_config', ts('Configure CKEditor'));

    $this->addRadio('contact_ajax_check_similar', ts('Check for Similar Contacts'), array(
      '1' => ts('While Typing'),
      '0' => ts('When Saving'),
      '2' => ts('Never'),
    ));

    $editOptions = CRM_Core_OptionGroup::values('contact_edit_options', FALSE, FALSE, FALSE, 'AND v.filter = 0');
    $this->assign('editOptions', $editOptions);

    $contactBlocks = CRM_Core_OptionGroup::values('contact_edit_options', FALSE, FALSE, FALSE, 'AND v.filter = 1');
    $this->assign('contactBlocks', $contactBlocks);

    $nameFields = CRM_Core_OptionGroup::values('contact_edit_options', FALSE, FALSE, FALSE, 'AND v.filter = 2');
    $this->assign('nameFields', $nameFields);

    $this->addElement('hidden', 'contact_edit_preferences', NULL, array('id' => 'contact_edit_preferences'));

    $optionValues = CRM_Core_OptionGroup::values('user_dashboard_options', FALSE, FALSE, FALSE, NULL, 'name');
    $invoicesKey = array_search('Invoices / Credit Notes', $optionValues);
    $this->assign('invoicesKey', $invoicesKey);
    parent::buildQuickForm();
  }

  /**
   * Process the form submission.
   */
  public function postProcess() {
    if ($this->_action == CRM_Core_Action::VIEW) {
      return;
    }

    $this->_params = $this->controller->exportValues($this->_name);

    if (!empty($this->_params['contact_edit_preferences'])) {
      $preferenceWeights = explode(',', $this->_params['contact_edit_preferences']);
      foreach ($preferenceWeights as $key => $val) {
        if (!$val) {
          unset($preferenceWeights[$key]);
        }
      }
      $opGroupId = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_OptionGroup', 'contact_edit_options', 'id', 'name');
      CRM_Core_BAO_OptionValue::updateOptionWeights($opGroupId, array_flip($preferenceWeights));
    }

    $this->_config->editor_id = $this->_params['editor_id'];

    $this->postProcessCommon();

    // Fixme - shouldn't be needed
    Civi::settings()->set('contact_ajax_check_similar', $this->_params['contact_ajax_check_similar']);

    // If "Configure CKEditor" button was clicked
    if (!empty($this->_params['ckeditor_config'])) {
      // Suppress the "Saved" status message and redirect to the CKEditor Config page
      $session = CRM_Core_Session::singleton();
      $session->getStatus(TRUE);
      $url = CRM_Utils_System::url('civicrm/admin/ckeditor', 'reset=1');
      $session->pushUserContext($url);
    }
  }

}
