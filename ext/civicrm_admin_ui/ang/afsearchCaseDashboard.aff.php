<?php
use CRM_CivicrmAdminUi_ExtensionUtil as E;

if (!CRM_Core_Component::isEnabled('CiviCase')) {
  return [];
}

return [
  'type' => 'search',
  'title' => E::ts('Case Dashboard'),
  'icon' => 'fa-folder-open',
  'server_route' => 'civicrm/case/dashboard',
  'permission' => [
    'access my cases and activities',
  ],
];
