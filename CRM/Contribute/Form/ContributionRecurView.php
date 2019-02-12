<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2019                                |
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
 * @copyright CiviCRM LLC (c) 2004-2019
 */

/**
 * Main form for viewing Recurring Contributions.
 */
class CRM_Contribute_Form_ContributionRecurView extends CRM_Core_Form {

  use CRM_Core_Form_EntityFormTrait;

  /**
   * Fields for the entity to be assigned to the template.
   *
   * Fields may have keys
   *  - name (required to show in tpl from the array)
   *  - description (optional, will appear below the field)
   *     Auto-added by setEntityFieldsMetadata unless specified here (use description => '' to hide)
   *  - not-auto-addable - this class will not attempt to add the field using addField.
   *    (this will be automatically set if the field does not have html in it's metadata
   *    or is not a core field on the form's entity).
   *  - help (option) add help to the field - e.g ['id' => 'id-source', 'file' => 'CRM/Contact/Form/Contact']]
   *  - template - use a field specific template to render this field
   *  - required
   *  - is_freeze (field should be frozen).
   *
   * @var array
   */
  protected $entityFields = [];

  /**
   * Set entity fields to be assigned to the form.
   */
  protected function setEntityFields() {
    $this->entityFields = [
      'contact_id' => [
        'name' => 'contact_id',
        'description' => '',
      ],
      'amount' => [
        'name' => 'amount',
        'description' => '',
        'formatter' => 'crmMoney',
      ],
/*      'frequency' => [
        'name' => 'frequency',
        'description' => '',
      ],*/
      'installments' => [
        'name' => 'installments',
        'description' => '',
      ],
      'contribution_status_id' => [
        'name' => 'contribution_status_id',
        'description' => '',
      ],
      'start_date' => [
        'name' => 'start_date',
        'description' => '',
      ],
      'create_date' => [
        'name' => 'create_date',
        'description' => '',
      ],
      'modified_date' => [
        'name' => 'modified_date',
        'description' => '',
      ],
      'cancel_date' => [
        'name' => 'cancel_date',
        'description' => '',
      ],
      'processor_id' => [
        'name' => 'processor_id',
        'description' => '',
      ],
      'trxn_id' => [
        'name' => 'trxn_id',
        'description' => '',
      ],
      'invoice_id' => [
        'name' => 'invoice_id',
        'description' => '',
      ],
      'cycle_day' => [
        'name' => 'cycle_day',
        'description' => '',
      ],
      'next_sched_contribution_date' => [
        'name' => 'next_sched_contribution_date',
        'description' => '',
      ],
      'failure_count' => [
        'name' => 'failure_count',
        'description' => '',
      ],
      'failure_retry_date' => [
        'name' => 'failure_retry_date',
        'description' => '',
      ],
      'auto_renew' => [
        'name' => 'auto_renew',
        'description' => '',
      ],
      'payment_processor_id' => [
        'name' => 'payment_processor_id',
        'description' => '',
      ],
      'financial_type_id' => [
        'name' => 'financial_type_id',
        'description' => '',
      ],

    ];

    if ($this->getAction() == CRM_Core_Action::NONE) {
      $this->setAction(CRM_Core_Action::VIEW);
    }
    self::setEntityFieldsMetadata();
  }

  /**
   * Deletion message to be assigned to the form.
   *
   * @var string
   */
  protected $deleteMessage;

  /**
   * Explicitly declare the entity api name.
   */
  public function getDefaultEntity() {
    return 'ContributionRecur';
  }

  /**
   * Set the delete message.
   *
   * We do this from the constructor in order to do a translation.
   */
  public function setDeleteMessage() {
    $this->deleteMessage = '';
  }

  static $_links = NULL;

  /**
   * The permission we have on this contact
   *
   * @var string
   */
  public $_permission = NULL;

  /**
   * The id of the contact associated with this contribution.
   *
   * @var int
   */
  public $_contactId = NULL;

  /**
   * The id of the recurring contribution that we are processing.
   *
   * @var int
   */
  public $_id = NULL;

  public function preProcess() {
    $this->_BAOName = 'CRM_Contribute_BAO_ContributionRecur';
    $this->setAction(CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, 'view'));
    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);
    $this->_contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
    $this->assign('contactId', $this->_contactId);

    // check logged in url permission
    CRM_Contact_Page_View::checkUserPermission($this);

    $this->assign('action', $this->_action);

    if ($this->_permission == CRM_Core_Permission::EDIT && !CRM_Core_Permission::check('edit contributions')) {
      // demote to view since user does not have edit contrib rights
      $this->_permission = CRM_Core_Permission::VIEW;
      $this->assign('permission', 'view');
    }

    if (empty($this->getEntityId())) {
      CRM_Core_Error::statusBounce('Recurring contribution not found');
    }

    try {
      $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array(
        'id' => $this->getEntityId(),
      ));
    } catch (Exception $e) {
      CRM_Core_Error::statusBounce('Recurring contribution not found (ID: ' . $this->getEntityId());
    }

    $contributionRecur['payment_processor'] = CRM_Financial_BAO_PaymentProcessor::getPaymentProcessorName(
      CRM_Utils_Array::value('payment_processor_id', $contributionRecur)
    );
    $idFields = array(
      'contribution_status_id',
      'campaign_id',
      'financial_type_id'
    );
    foreach ($idFields as $idField) {
      if (!empty($contributionRecur[$idField])) {
        $contributionRecur[substr($idField, 0, -3)] = CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_ContributionRecur', $idField, $contributionRecur[$idField]);
      }
    }

    // Add linked membership
    $membership = civicrm_api3('Membership', 'get', array(
      'contribution_recur_id' => $contributionRecur['id'],
    ));
    if (!empty($membership['count'])) {
      $membershipDetails = reset($membership['values']);
      $contributionRecur['membership_id'] = $membershipDetails['id'];
      $contributionRecur['membership_name'] = $membershipDetails['membership_name'];
    }

    $this->assign('recur', $contributionRecur);

    $displayName = CRM_Contact_BAO_Contact::displayName($contributionRecur['contact_id']);
    $this->assign('displayName', $displayName);

    // Check if this is default domain contact CRM-10482
    if (CRM_Contact_BAO_Contact::checkDomainContact($contributionRecur['contact_id'])) {
      $displayName .= ' (' . ts('default organization') . ')';
    }

    // Add derived fields
    $this->add('text', 'frequency', ts('Frequency'));

    // Add additional buttons to form
    if (!in_array(CRM_Contribute_PseudoConstant::contributionStatus(
      $contributionRecur['contribution_status_id'], 'name'),
      CRM_Contribute_BAO_ContributionRecur::getInactiveStatuses()
    )) {
      $recurLinks = CRM_Contribute_Page_Tab::recurLinks($this->getEntityId());
      $values = [
        'cid' => $this->_contactId,
        'crid' => $this->getEntityId(),
        'cxt' => 'contribution',
      ];
      foreach ($recurLinks as &$recurLink) {
        CRM_Core_Action::replace($recurLink['url'], $values);
        CRM_Core_Action::replace($recurLink['qs'], $values);
      }
      if ($this->_action & CRM_Core_Action::VIEW) {
        unset($recurLinks[CRM_Core_Action::VIEW]);
      }
      $this->assign('recurLinks', $recurLinks);
    }

    // omitting contactImage from title for now since the summary overlay css doesn't work outside of our crm-container
    CRM_Utils_System::setTitle(ts('View Recurring Contribution from') . ' ' . $displayName);
  }

  public function buildQuickForm() {
    self::buildQuickEntityForm();
  }

  public function setDefaultValues() {
    $defaults = $this->getEntityDefaults();
    $defaults['frequency'] = 'every ' . $defaults['frequency_interval'] . ' ' . $defaults['frequency_unit'];
    return $defaults;
  }

}
