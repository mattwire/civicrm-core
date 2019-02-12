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
 * This class provides support for canceling recurring subscriptions.
 */
class CRM_Contribute_Form_CancelSubscription extends CRM_Core_Form {
  protected $_paymentProcessorObj = NULL;

  protected $_userContext = NULL;

  /**
   * @var int Membership ID
   */
  protected $_mid = NULL;

  /**
   * @var int Contribution Recur ID
   */
  protected $_crid = NULL;

  /**
   * @var int Contribution ID
   */
  protected $_coid = NULL;

  protected $_mode = NULL;

  protected $_selfService = FALSE;

  /**
   * @var string Display name of recurring contribution contact ID
   */
  protected $_donorDisplayName = NULL;

  /**
   * @var string Email address for recurring contribution contact ID
   */
  protected $_donorEmail = NULL;

  /**
   * Contribution Recur details from api3 ContributionRecur.getsingle
   * @var array
   */
  protected $_contributionRecur = NULL;

  /**
   * Set variables up before form is built.
   */
  public function preProcess() {
    $this->_mid = CRM_Utils_Request::retrieve('mid', 'Positive', $this, FALSE);
    $this->_crid = CRM_Utils_Request::retrieve('crid', 'Positive', $this, FALSE);
    $this->_coid = CRM_Utils_Request::retrieve('coid', 'Integer', $this, FALSE);

    if (!$this->_crid && !$this->_coid && !$this->_mid) {
      Throw new CRM_Core_Exception('Missing Mandatory parameters for CancelSubscription');
    }

    if ($this->_crid) {
      // Are we cancelling a recurring contribution that is linked to one or more memberships?
      $recurMemberships = civicrm_api3('Membership', 'get', ['contribution_recur_id' => $this->_crid]);
      if (!empty($recurMemberships['id'])) {
        $this->_mid = $recurMemberships['id'];
      }
    }

    if ($this->_mid) {
      $this->_mode = 'auto_renew';
      // CRM-18468: crid is more accurate than mid for getting subscriptionDetails so don't get them again.
      if (!$this->_crid) {
        $this->_crid = civicrm_api3('Membership', 'getvalue', ['id' => $this->_mid, 'return' => 'contribution_recur_id']);
      }
    }

    if ($this->_coid && (!$this->_crid)) {
      $contribution = civicrm_api3('Contribution', 'getsingle', ['id' => $this->_coid, 'return' => ['contribution_recur_id', 'contribution_page_id']]);
      $this->_crid = $contribution['contribution_recur_id'];
    }

    $recurDetail = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $this->_crid]);
    if (!empty($contribution['contribution_page_id'])) {
      $recurDetail['contribution_page_id'] = $contribution['contribution_page_id'];
    }
    $this->_paymentProcessorObj = \Civi\Payment\System::singleton()->getById($recurDetail['payment_processor_id']);

    if ($recurDetail['contribution_status_id'] == CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Cancelled')) {
      CRM_Core_Error::statusBounce(ts('The recurring contribution has already been cancelled.'));
    }

    if (!CRM_Core_Permission::check('edit contributions')) {
      $userChecksum = CRM_Utils_Request::retrieve('cs', 'String', $this, FALSE);
      if (!CRM_Contact_BAO_Contact_Utils::validChecksum($recurDetail['contact_id'], $userChecksum)) {
        CRM_Core_Error::statusBounce(ts('You do not have permission to cancel this recurring contribution.'));
      }
      $this->_selfService = TRUE;
    }
    $this->assign('self_service', $this->_selfService);

    // handle context redirection
    CRM_Contribute_BAO_ContributionRecur::setSubscriptionContext();

    CRM_Utils_System::setTitle(ts('Cancel Recurring Contribution'));
    $this->assign('mode', $this->_mode);

    if ($recurDetail['contact_id']) {
      list($this->_donorDisplayName, $this->_donorEmail) = CRM_Contact_BAO_Contact::getContactDetails($recurDetail['contact_id']);
    }

    $recurDetail['payment_processor'] = CRM_Financial_BAO_PaymentProcessor::getPaymentProcessorName(
      CRM_Utils_Array::value('payment_processor_id', $recurDetail)
    );

    $this->_contributionRecur = $recurDetail;
    $this->assign('recur', $recurDetail);
  }

  /**
   * Build the form object.
   */
  public function buildQuickForm() {
    // Determine if we can cancel recurring contribution via API with this processor
    $cancelSupported = $this->_paymentProcessorObj->supports('CancelRecurring');
    if ($cancelSupported) {
      $searchRange = array();
      $searchRange[] = $this->createElement('radio', NULL, NULL, ts('Yes'), '1');
      $searchRange[] = $this->createElement('radio', NULL, NULL, ts('No'), '0');

      $this->addGroup(
        $searchRange,
        'send_cancel_request',
        ts('Send cancellation request to %1?',
          array(1 => $this->_paymentProcessorObj->_processorName))
      );
    }
    $this->assign('cancelSupported', $cancelSupported);

    if ($this->_donorEmail) {
      $this->add('checkbox', 'is_notify', ts('Notify Contributor?'));
    }
    $cancelButton = ts('Cancel Recurring Contribution');

    $type = 'next';
    if ($this->_selfService) {
      $type = 'submit';
    }

    $this->addButtons(array(
        array(
          'type' => $type,
          'name' => $cancelButton,
          'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
          'isDefault' => TRUE,
        ),
        array(
          'type' => 'cancel',
          'name' => ts('Not Now'),
        ),
      )
    );
  }

  /**
   * Set default values for the form.
   *
   * @return array
   *   array of default values
   */
  public function setDefaultValues() {
    return array(
      'is_notify' => 1,
      'send_cancel_request' => 1,
    );
  }

  /**
   * Process the form submission.
   */
  public function postProcess() {
    $status = $message = NULL;
    $cancelSubscription = TRUE;
    $params = $this->controller->exportValues($this->_name);

    if ($this->_selfService) {
      // for self service force sending-request & notify
      if ($this->_paymentProcessorObj->supports('cancelRecurring')) {
        $params['send_cancel_request'] = 1;
      }

      if ($this->_donorEmail) {
        $params['is_notify'] = 1;
      }
    }

    if (CRM_Utils_Array::value('send_cancel_request', $params) == 1) {
      $cancelParams = [
        'recur_id' => $this->_contributionRecur['id'],
        'subscriptionId' => $this->_contributionRecur['processor_id'],
      ];
      $cancelSubscription = $this->_paymentProcessorObj->cancelSubscription($message, $cancelParams);
    }

    if (is_a($cancelSubscription, 'CRM_Core_Error')) {
      CRM_Core_Error::displaySessionError($cancelSubscription);
    }
    elseif ($cancelSubscription) {
      $activityParams
        = array(
          'subject' => ts('Recurring contribution cancelled'),
          'details' => $message,
        );
      $cancelStatus = CRM_Contribute_BAO_ContributionRecur::cancelRecurContribution(
        $this->_contributionRecur['id'],
        $activityParams
      );

      if ($cancelStatus) {
        $tplParams = array();
        if ($this->_mid) {
          $inputParams = array('id' => $this->_mid);
          CRM_Member_BAO_Membership::getValues($inputParams, $tplParams);
          $tplParams = $tplParams[$this->_mid];
          $tplParams['membership_status']
            = CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipStatus', $tplParams['status_id']);
          $tplParams['membershipType']
            = CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipType', $tplParams['membership_type_id']);
          $status = ts('The automatic renewal of your %1 membership has been cancelled as requested. This does not affect the status of your membership - you will receive a separate notification when your membership is up for renewal.', array(1 => $tplParams['membershipType']));
          $msgTitle = 'Membership Renewal Cancelled';
          $msgType = 'info';
        }
        else {
          $tplParams['recur_frequency_interval'] = $this->_contributionRecur['frequency_interval'];
          $tplParams['recur_frequency_unit'] = $this->_contributionRecur['frequency_unit'];
          $tplParams['amount'] = $this->_contributionRecur['amount'];
          $tplParams['contact'] = array('display_name' => $this->_donorDisplayName);
          $status = ts('The recurring contribution of %1, every %2 %3 has been cancelled.',
            array(
              1 => $this->_contributionRecur['amount'],
              2 => $this->_contributionRecur['frequency_interval'],
              3 => $this->_contributionRecur['frequency_unit'],
            )
          );
          $msgTitle = 'Contribution Cancelled';
          $msgType = 'success';
        }

        if (CRM_Utils_Array::value('is_notify', $params) == 1) {
          if ($this->_contributionRecur['contribution_page_id']) {
            CRM_Core_DAO::commonRetrieveAll(
              'CRM_Contribute_DAO_ContributionPage',
              'id',
              $this->_contributionRecur['contribution_page_id'],
              $value,
              array('title', 'receipt_from_name', 'receipt_from_email')
            );
            $receiptFrom
              = '"' . CRM_Utils_Array::value('receipt_from_name', $value[$this->_contributionRecur['contribution_page_id']]) .
              '" <' .
              $value[$this->_contributionRecur['contribution_page_id']]['receipt_from_email'] .
              '>';
          }
          else {
            $domainValues = CRM_Core_BAO_Domain::getNameAndEmail();
            $receiptFrom = "$domainValues[0] <$domainValues[1]>";
          }

          // send notification
          $sendTemplateParams
            = array(
              'groupName' => $this->_mode == 'auto_renew' ? 'msg_tpl_workflow_membership' : 'msg_tpl_workflow_contribution',
              'valueName' => $this->_mode == 'auto_renew' ? 'membership_autorenew_cancelled' : 'contribution_recurring_cancelled',
              'contactId' => $this->_contributionRecur['contact_id'],
              'tplParams' => $tplParams,
              //'isTest'    => $isTest, set this from _objects
              'PDFFilename' => 'receipt.pdf',
              'from' => $receiptFrom,
              'toName' => $this->_donorDisplayName,
              'toEmail' => $this->_donorEmail,
            );
          list($sent) = CRM_Core_BAO_MessageTemplate::sendTemplate($sendTemplateParams);
        }
      }
      else {
        $msgType = 'error';
        $msgTitle = ts('Error');
        if ($params['send_cancel_request'] == 1) {
          $status = ts('Recurring contribution was cancelled successfully by the processor, but could not be marked as cancelled in the database.');
        }
        else {
          $status = ts('Recurring contribution could not be cancelled in the database.');
        }
      }
    }
    else {
      $status = ts('The recurring contribution could not be cancelled.');
      $msgTitle = 'Error Cancelling Contribution';
      $msgType = 'error';
    }

    $session = CRM_Core_Session::singleton();
    $userID = $session->get('userID');
    if ($userID && $status) {
      $session->setStatus($status, $msgTitle, $msgType);
    }
    elseif (!$userID) {
      if ($status) {
        CRM_Utils_System::setUFMessage($status);
        // keep result as 1, since we not displaying anything on the redirected page anyway
        return CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contribute/subscriptionstatus',
        "reset=1&task=cancel&result=1"));
      }
    }
  }

}
