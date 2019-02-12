{*
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
*}

<div class="crm-block crm-form-block crm-auto-renew-membership-cancellation">
  <div class="help">
    <div class="icon inform-icon"></div>&nbsp;
    {if $mode eq 'auto_renew'}
      {ts}One or more memberships are linked to this recurring contribution - this will NOT cancel the membership(s). However the membership(s) will no longer auto-renew.{/ts}
      <br />
    {/if}
    {ts}Click the button below to cancel this commitment and stop future transactions. This does not affect contributions which have already been completed.{/ts}

    {if !$cancelSupported}
      <div class="status-warning">
        {ts}Automatic cancellation is not supported for this payment processor. You or the contributor will need to manually cancel this recurring contribution using the payment processor website.{/ts}
      </div>
    {/if}
  </div>
  {if !$self_service}
    <table class="form-layout">
      <tr>
        <td class="label"><label for="payment_processor">{ts}Details{/ts}</label></td>
        <td>
          <strong>{ts 1=$recur.amount|crmMoney:$recur.currency 2=$recur.frequency_interval 3=$recur.frequency_unit}%1 every %2 %3{/ts}
            {if $recur.installments}
              {ts 1=$recur.installments}for %1 installments{/ts}.
            {/if}
          </strong>
        </td>
      </tr>
      <tr>
        <td class="label"><label for="payment_processor">{ts}Payment Processor{/ts}</label></td>
        <td>{$recur.payment_processor}</td>
      </tr>
      {if $recur.processor_id}
        <tr>
          <td class="label"><label for="payment_processor">{ts}Processor ID{/ts}</label></td>
          <td>{$recur.processor_id}</td>
        </tr>
      {elseif $recur.trxn_id}
        <tr>
          <td class="label"><label for="payment_processor">{ts}Transaction ID{/ts}</label></td>
          <td>{$recur.trxn_id}</td>
        </tr>
      {/if}
      {if $cancelSupported}
      <tr>
        <td class="label">{$form.send_cancel_request.label}</td>
        <td>{$form.send_cancel_request.html}</td>
      </tr>
      {/if}
      <tr>
        <td class="label">{$form.is_notify.label}</td>
        <td>{$form.is_notify.html}</td>
      </tr>
    </table>
  {/if}
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="top"}</div>
</div>
