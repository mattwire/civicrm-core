{if $customDataType}
{literal}
  <script type="text/javascript">
    CRM.$(function($) {
      $( document ).ajaxStart(function() {
        CRM.$('div.crm-form-block').closest('form').block();
      });

      $( document ).ajaxStop(function() {
        CRM.$('div.crm-form-block').closest('form').unblock();
      });
    });
  </script>
{/literal}
  <div id="customData"></div>
  {*include custom data js file*}
  {include file="CRM/common/customData.tpl"}
  {literal}
  <script type="text/javascript">
    CRM.$(function($) {
      {/literal}
      {if $customDataSubType}
        CRM.buildCustomData('{$customDataType}', {$customDataSubType});
      {else}
        CRM.buildCustomData('{$customDataType}');
      {/if}
      {literal}
    });
  </script>
  {/literal}
{/if}
