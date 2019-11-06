<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2020                                |
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
 * @copyright CiviCRM LLC (c) 2004-2020
 */

/**
 * This class generates form components for Payment-Instrument.
 */
class CRM_Contribute_Form_ContributionView extends CRM_Core_Form {

  use CRM_Core_Form_EntityFormTrait;

  public function getDefaultEntity() {
    return 'Contribution';
  }

  /**
   * Fields for the entity to be assigned to the template.
   *
   * Fields may have keys
   *  - name (required to show in tpl from the array)
   *  - description (optional, will appear below the field)
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
   * Set variables up before form is built.
   */
  public function preProcess() {
    $this->setEntityId($this->get('id'));
    $context = CRM_Utils_Request::retrieve('context', 'Alphanumeric', $this);
    $this->assign('context', $context);

    //$values = CRM_Contribute_BAO_Contribution::getValuesWithMappings($params);
    $values = civicrm_api3('Contribution', 'getsingle', ['id' => $this->getEntityId()]);

    if (CRM_Financial_BAO_FinancialType::isACLFinancialTypeStatus() && $this->isViewContext()) {
      $financialTypeID = CRM_Contribute_PseudoConstant::financialType($values['financial_type_id']);
      CRM_Financial_BAO_FinancialType::checkPermissionedLineItems($this->getEntityId(), 'view');
      if (CRM_Financial_BAO_FinancialType::checkPermissionedLineItems($this->getEntityId(), 'edit', FALSE)) {
        $this->assign('canEdit', TRUE);
      }
      if (CRM_Financial_BAO_FinancialType::checkPermissionedLineItems($this->getEntityId(), 'delete', FALSE)) {
        $this->assign('canDelete', TRUE);
      }
      if (!CRM_Core_Permission::check('view contributions of type ' . $financialTypeID)) {
        CRM_Core_Error::statusBounce(ts('You do not have permission to access this page.'));
      }
    }
    elseif ($this->isViewContext()) {
      $this->assign('noACL', TRUE);
    }

    if (!empty($values['contribution_page_id'])) {
      $contribPages = CRM_Contribute_PseudoConstant::contributionPage(NULL, TRUE);
      $values['contribution_page_title'] = CRM_Utils_Array::value(CRM_Utils_Array::value('contribution_page_id', $values), $contribPages);
    }

    // get received into i.e to_financial_account_id from last trxn
    $financialTrxnId = CRM_Core_BAO_FinancialTrxn::getFinancialTrxnId($values['contribution_id'], 'DESC');
    $values['to_financial_account'] = '';
    if (!empty($financialTrxnId['financialTrxnId'])) {
      $values['to_financial_account_id'] = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialTrxn', $financialTrxnId['financialTrxnId'], 'to_financial_account_id');
      if ($values['to_financial_account_id']) {
        $values['to_financial_account'] = CRM_Contribute_PseudoConstant::financialAccount($values['to_financial_account_id']);
      }
      $values['payment_processor_id'] = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialTrxn', $financialTrxnId['financialTrxnId'], 'payment_processor_id');
      if ($values['payment_processor_id']) {
        $values['payment_processor_name'] = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_PaymentProcessor', $values['payment_processor_id'], 'name');
      }
    }

    if (!empty($values['contribution_recur_id'])) {
      $recurDetails = civicrm_api3('ContributionRecur', 'getsingle', [
        'return' => ["installments", "frequency_unit", "frequency_interval"],
        'id' => $values['contribution_recur_id'],
      ]);
      $values['recur_installments'] = $recurDetails['installments'];
      $values['recur_frequency_unit'] = $recurDetails['frequency_unit'];
      $values['recur_frequency_interval'] = $recurDetails['frequency_interval'];
    }

    // This sets the subtype so entity form can load custom data for a specific financial type
    $this->_entitySubTypeId = CRM_Utils_Array::value('financial_type_id', $values);

    $premiumId = NULL;
    try {
      $premium = civicrm_api3('ContributionProduct', 'getsingle', ['contribution_id' => $this->getEntityId(), 'options' => ['limit' => 1]]);
      $product = civicrm_api3('Product', 'getsingle', ['id' => $premium['product_id'], 'options' => ['limit' => 1]]);
      $this->assign('premium', $product['name']);
      $this->assign('option', $premium['product_option']);
      $this->assign('fulfilled', $premium['fulfilled_date']);
    }
    catch (Exception $e) {
      // No products
    }

    // Get Note
    $noteValue = CRM_Core_BAO_Note::getNote(CRM_Utils_Array::value('id', $values), 'civicrm_contribution');
    $values['note'] = array_values($noteValue);

    // show billing address location details, if exists
    if (!empty($values['address_id'])) {
      $addressParams = ['id' => CRM_Utils_Array::value('address_id', $values)];
      $addressDetails = CRM_Core_BAO_Address::getValues($addressParams, FALSE, 'id');
      $addressDetails = array_values($addressDetails);
      $values['billing_address'] = $addressDetails[0]['display'];
    }

    //assign soft credit record if exists.
    $SCRecords = CRM_Contribute_BAO_ContributionSoft::getSoftContribution($values['contribution_id'], TRUE);
    if (!empty($SCRecords['soft_credit'])) {
      $this->assign('softContributions', $SCRecords['soft_credit']);
      unset($SCRecords['soft_credit']);
    }

    //assign pcp record if exists
    foreach ($SCRecords as $name => $value) {
      $this->assign($name, $value);
    }

    $lineItems = [];
    $displayLineItems = FALSE;
    if ($this->getEntityId()) {
      $lineItems = [CRM_Price_BAO_LineItem::getLineItemsByContributionID(($this->getEntityId()))];
      $firstLineItem = reset($lineItems[0]);
      if (empty($firstLineItem['price_set_id'])) {
        // CRM-20297 All we care is that it's not QuickConfig, so no price set
        // is no problem.
        $displayLineItems = TRUE;
      }
      else {
        try {
          $priceSet = civicrm_api3('PriceSet', 'getsingle', [
            'id' => $firstLineItem['price_set_id'],
            'return' => 'is_quick_config, id',
          ]);
          $displayLineItems = !$priceSet['is_quick_config'];
        }
        catch (CiviCRM_API3_Exception $e) {
          throw new CRM_Core_Exception('Cannot find price set by ID');
        }
      }
    }
    $this->assign('lineItem', $lineItems);
    $this->assign('displayLineItems', $displayLineItems);
    $values['totalAmount'] = $values['total_amount'];
    $this->assign('displayLineItemFinancialType', TRUE);

    //do check for campaigns
    if ($campaignId = CRM_Utils_Array::value('campaign_id', $values)) {
      $campaigns = CRM_Campaign_BAO_Campaign::getCampaigns($campaignId);
      $values['campaign'] = $campaigns[$campaignId];
    }
    if ($values['contribution_status'] == 'Refunded') {
      $this->assign('refund_trxn_id', CRM_Core_BAO_FinancialTrxn::getRefundTransactionTrxnID($this->getEntityId()));
    }

    // assign values to the template
    $this->assign($values);
    $invoiceSettings = Civi::settings()->get('contribution_invoice_settings');
    $invoicing = CRM_Invoicing_Utils::isInvoicingEnabled();
    $this->assign('invoicing', $invoicing);
    $this->assign('isDeferred', CRM_Utils_Array::value('deferred_revenue_enabled', $invoiceSettings));
    if ($invoicing && isset($values['tax_amount'])) {
      $this->assign('totalTaxAmount', $values['tax_amount']);
    }

    $displayName = $values['display_name'];
    $this->assign('displayName', $displayName);

    // Check if this is default domain contact CRM-10482
    if (CRM_Contact_BAO_Contact::checkDomainContact($values['contact_id'])) {
      $displayName .= ' (' . ts('default organization') . ')';
    }

    // omitting contactImage from title for now since the summary overlay css doesn't work outside of our crm-container
    CRM_Utils_System::setTitle(ts('View Contribution from') . ' ' . $displayName);

    // add viewed contribution to recent items list
    $url = CRM_Utils_System::url('civicrm/contact/view/contribution',
      "action=view&reset=1&id={$values['id']}&cid={$values['contact_id']}&context=home"
    );

    $title = $displayName . ' - (' . CRM_Utils_Money::format($values['total_amount'], $values['currency']) . ' ' . ' - ' . $values['financial_type'] . ')';

    $recentOther = [];
    if (CRM_Core_Permission::checkActionPermission('CiviContribute', CRM_Core_Action::UPDATE)) {
      $recentOther['editUrl'] = CRM_Utils_System::url('civicrm/contact/view/contribution',
        "action=update&reset=1&id={$values['id']}&cid={$values['contact_id']}&context=home"
      );
    }
    if (CRM_Core_Permission::checkActionPermission('CiviContribute', CRM_Core_Action::DELETE)) {
      $recentOther['deleteUrl'] = CRM_Utils_System::url('civicrm/contact/view/contribution',
        "action=delete&reset=1&id={$values['id']}&cid={$values['contact_id']}&context=home"
      );
    }
    CRM_Utils_Recent::add($title,
      $url,
      $values['id'],
      'Contribution',
      $values['contact_id'],
      NULL,
      $recentOther
    );
    $contributionStatus = CRM_Core_PseudoConstant::getName('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $values['contribution_status_id']);
    if (in_array($contributionStatus, ['Partially paid', 'Pending refund'])
        || ($contributionStatus === 'Pending' && $values['is_pay_later'])
        ) {
      if ($contributionStatus === 'Pending refund') {
        $this->assign('paymentButtonName', ts('Record Refund'));
      }
      else {
        $this->assign('paymentButtonName', ts('Record Payment'));
      }
      $this->assign('addRecordPayment', TRUE);
      $this->assign('contactId', $values['contact_id']);
      $this->assign('componentId', $this->getEntityId());
      $this->assign('component', 'contribution');
    }
    $this->assignPaymentInfoBlock($this->getEntityId());
  }

  /**
   * Build the form object.
   */
  public function buildQuickForm() {
    $this->buildQuickEntityForm();
  }

  /**
   * Assign the values to build the payment info block.
   *
   * @todo - this is a bit too much copy & paste from AbstractEditPayment
   * (justifying on the basis it's 'pretty short' and in a different inheritance
   * tree. I feel like traits are probably the longer term answer).
   *
   * @param int $id
   *
   * @return string
   *   Block title.
   */
  protected function assignPaymentInfoBlock($id) {
    // component is used in getPaymentInfo primarily to retrieve the contribution id, we
    // already have that.
    $paymentInfo = CRM_Contribute_BAO_Contribution::getPaymentInfo($id, 'contribution', TRUE);
    $title = ts('View Payment');
    $this->assign('transaction', TRUE);
    $this->assign('payments', $paymentInfo['transaction']);
    $this->assign('paymentLinks', $paymentInfo['payment_links']);
    return $title;
  }

}
