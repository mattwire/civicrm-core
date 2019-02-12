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

{if $recur.is_test}
  <div class="help">
    <strong>{ts}This is a TEST transaction{/ts}</strong>
  </div>
{/if}

<div class="crm-block crm-content-block crm-{$entityInClassFormat}-view-form-block">
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="top" linkButtons=$recurLinks}</div>

  <table class="crm-info-panel">
    <tr>
      <td class="label">{$form.contact_id.label}</td>
      <td class="bold"><a href="{crmURL p='civicrm/contact/view' q="cid=`$recur.contact_id`"}">{$displayName}</a></td>
    </tr>
    <tr><td class="label">{$form.amount.label}</td><td>{$recur.amount|crmMoney:$recur.currency}{if $is_test} ({ts}test{/ts}){/if}</td></tr>
    <tr><td class="label">{$form.frequency.label}</td><td>{$form.frequency.html}</td></tr>
    {if $recur.installments}<tr><td class="label">{$form.installments.label}</td><td>{$recur.installments}</td></tr>{/if}
    <tr><td class="label">{$form.contribution_status_id.label}</td><td>{$recur.contribution_status}</td></tr>
    <tr><td class="label">{$form.create_date.label}</td><td>{$recur.create_date|crmDate}</td></tr>
    {if $recur.modified_date}<tr><td class="label">{$form.modified_date.label}</td><td>{$recur.modified_date|crmDate}</td></tr>{/if}
    <tr><td class="label">{$form.start_date.label}</td><td>{$recur.start_date|crmDate}</td></tr>
    {if $recur.cancel_date}<tr><td class="label">{$form.cancel_date.label}</td><td>{$recur.cancel_date|crmDate}</td></tr>{/if}
    {if $recur.end_date}<tr><td class="label">{$form.end_date.label}</td><td>{$recur.end_date|crmDate}</td></tr>{/if}
    {if $recur.next_sched_contribution_date && $recur.contribution_status_id neq 3}<tr><td class="label">{$form.next_sched_contribution_date.label}</td><td>{$recur.next_sched_contribution_date|crmDate}</td></tr>{/if}
    <tr><td class="label">{$form.cycle_day.label}</td><td>{$recur.cycle_day}</td></tr>
    {if $recur.processor_id}<tr><td class="label">{$form.processor_id.label}</td><td>{$recur.processor_id}</td></tr>{/if}
    <tr><td class="label">{$form.trxn_id.label}</td><td>{$recur.trxn_id}</td></tr>
    {if $recur.invoice_id}<tr><td class="label">{$form.invoice_id.label}</td><td>{$recur.invoice_id}</td></tr>{/if}
    {if $recur.failure_count}<tr><td class="label">{$form.failure_count.label}</td><td>{$recur.failure_count}</td></tr>{/if}
    <tr><td class="label">{$form.auto_renew.label}</td><td>{if $recur.auto_renew}{ts}Yes{/ts}{else}{ts}No{/ts}{/if}</td></tr>
    {if $recur.payment_processor}<tr><td class="label">{$form.payment_processor_id.label}</td><td>{$recur.payment_processor}</td></tr>{/if}
    {if $recur.financial_type}<tr><td class="label">{$form.financial_type_id.label}</td><td>{$recur.financial_type}</td></tr>{/if}
    {if $recur.campaign}<tr><td class="label">{$form.campaign_id.label}</td><td>{$recur.campaign}</td></tr>{/if}
    {if $recur.membership_id}<tr>
      <td class="label">{ts}Membership{/ts}</td>
      <td><a class="crm-hover-button action-item" href='{crmURL p="civicrm/contact/view/membership" q="action=view&reset=1&cid=`$contactId`&id=`$recur.membership_id`&context=membership&selectedChild=member"}'>{$recur.membership_name}</a></td>
      </tr>
    {/if}
    {include file="CRM/Custom/Page/CustomDataView.tpl"}
  </table>

  <div id="recurring-contribution-payments"></div>
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom" linkButtons=$recurLinks}</div>
</div>

<script type="text/javascript">
  var recurContribID = {$recur.id};
  var contactID = {$contactId};
  {literal}
  CRM.$(function($) {
    CRM.loadPage(
      CRM.url(
        'civicrm/contribute/contributionrecur-payments',
        {
          reset: 1,
          id: recurContribID,
          cid: contactID
        },
        'back'
      ),
      {
        target : '#recurring-contribution-payments',
        dialog : false
      }
    );
  });
  {/literal}
</script>
