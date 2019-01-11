<script type='text/javascript'>
  var selectedTab = '{$defaultTab}';
  var tabContainer = '#mainTabContainer';
  {if $tabContainer}tabContainer = '{$tabContainer}';{/if}
  {literal}
  CRM.$(function($) {
    {/literal}
    {if $tabContainer eq '#secondaryTabContainer'}
      {if $selectedChild2}
        selectedTab = '{$selectedChild2}';
      {else}
        {literal}var tabSettings2 = CRM.tabSettings2 || {};{/literal}
      {/if}
    {else}
      {if $selectedChild}selectedTab = '{$selectedChild}';{/if}
    {/if}
    {literal}
    var tabIndex = $('#tab_' + selectedTab).prevAll().length;
    $(tabContainer).tabs({active: tabIndex});
  });

  {/literal}
</script>
