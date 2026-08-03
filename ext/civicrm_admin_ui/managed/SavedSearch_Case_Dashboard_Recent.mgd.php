<?php
use CRM_CivicrmAdminUi_ExtensionUtil as E;

if (!CRM_Core_Component::isEnabled('CiviCase')) {
  return [];
}

return [
  [
    'name' => 'SavedSearch_Case_Dashboard_Recent',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Case_Dashboard_Recent',
        'label' => E::ts('Cases With Recently Performed Activities'),
        'form_values' => [
          'join' => [
            'Case_CaseContact_Contact_01' => 'Client',
            'Case_case_manager_id_Contact_01' => 'Case Manager',
            'Case_CaseActivity_Activity_02' => 'Most Recent Activity',
          ],
        ],
        'api_entity' => 'Case',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'subject',
            'case_type_id:label',
            'case_type_id:icon',
            'status_id:label',
            'GROUP_CONCAT(DISTINCT Case_CaseContact_Contact_01.sort_name) AS GROUP_CONCAT_Case_CaseContact_Contact_01_sort_name',
            'GROUP_CONCAT(DISTINCT Case_CaseContact_Contact_01.id) AS GROUP_CONCAT_Case_CaseContact_Contact_01_id',
            'GROUP_CONCAT(DISTINCT Case_CaseContact_Contact_01.email_primary.email) AS GROUP_CONCAT_Case_CaseContact_Contact_01_email_primary_email',
            'GROUP_CONCAT(DISTINCT Case_CaseContact_Contact_01.phone_primary.phone) AS GROUP_CONCAT_Case_CaseContact_Contact_01_phone_primary_phone',
            'GROUP_CONCAT(DISTINCT Case_case_manager_id_Contact_01.sort_name) AS GROUP_CONCAT_Case_case_manager_id_Contact_01_sort_name',
            'GROUP_CONCAT(DISTINCT my_case_role) AS GROUP_CONCAT_my_case_role',
            'GROUP_FIRST(Case_CaseActivity_Activity_02.activity_type_id:label ORDER BY Case_CaseActivity_Activity_02.activity_date_time DESC) AS GROUP_FIRST_Case_CaseActivity_Activity_02_activity_type_id_label',
            'GROUP_FIRST(Case_CaseActivity_Activity_02.activity_date_time ORDER BY Case_CaseActivity_Activity_02.activity_date_time DESC) AS GROUP_FIRST_Case_CaseActivity_Activity_02_activity_date_time',
          ],
          'orderBy' => [],
          'where' => [
            ['is_deleted', '=', FALSE],
            // See SavedSearch_Case_Dashboard_Upcoming.mgd.php for why: turns
            // the LEFT JOIN below into an effective INNER JOIN, so only
            // cases with a matching completed activity appear.
            ['Case_CaseActivity_Activity_02.id', 'IS NOT NULL'],
          ],
          'groupBy' => ['id'],
          'join' => [
            [
              'Contact AS Case_CaseContact_Contact_01',
              'LEFT',
              'CaseContact',
              ['id', '=', 'Case_CaseContact_Contact_01.case_id'],
            ],
            [
              'Contact AS Case_case_manager_id_Contact_01',
              'LEFT',
              ['case_manager_id', '=', 'Case_case_manager_id_Contact_01.id'],
            ],
            [
              'Activity AS Case_CaseActivity_Activity_02',
              'LEFT',
              'CaseActivity',
              ['id', '=', 'Case_CaseActivity_Activity_02.case_id'],
              ['Case_CaseActivity_Activity_02.status_id:name', '=', '"Completed"'],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'SavedSearch_Case_Dashboard_Recent_SearchDisplay_Case_Dashboard_Recent_Table',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Case_Dashboard_Recent_Table',
        'label' => E::ts('Cases With Recently Performed Activities Table'),
        'saved_search_id.name' => 'Case_Dashboard_Recent',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'sort' => [
            ['GROUP_FIRST_Case_CaseActivity_Activity_02_activity_date_time', 'DESC'],
          ],
          'limit' => 25,
          'pager' => [
            'show_count' => TRUE,
            'hide_single' => TRUE,
          ],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'GROUP_CONCAT_Case_CaseContact_Contact_01_sort_name',
              'label' => 'Client',
              'sortable' => TRUE,
              'link' => [
                'entity' => 'Contact',
                'action' => 'view',
                'join' => 'Case_CaseContact_Contact_01',
                'target' => '',
              ],
            ],
            [
              'type' => 'field',
              'key' => 'subject',
              'label' => 'Subject',
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'case_type_id:label',
              'label' => 'Case Type',
              'sortable' => TRUE,
              'icons' => [
                [
                  'field' => 'case_type_id:icon',
                  'side' => 'left',
                ],
              ],
            ],
            [
              'type' => 'field',
              'key' => 'status_id:label',
              'label' => 'Status',
              'sortable' => TRUE,
              'colors' => [
                ['field' => 'status_id:color'],
              ],
            ],
            [
              'type' => 'field',
              'key' => 'GROUP_CONCAT_my_case_role',
              'label' => 'My Role',
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'GROUP_CONCAT_Case_case_manager_id_Contact_01_sort_name',
              'label' => 'Case Manager',
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'GROUP_CONCAT_Case_CaseContact_Contact_01_email_primary_email',
              'label' => 'Client Email',
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'GROUP_CONCAT_Case_CaseContact_Contact_01_phone_primary_phone',
              'label' => 'Client Phone',
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'GROUP_FIRST_Case_CaseActivity_Activity_02_activity_date_time',
              'label' => 'Most Recent',
              'sortable' => TRUE,
              'rewrite' => '[GROUP_FIRST_Case_CaseActivity_Activity_02_activity_type_id_label]: [GROUP_FIRST_Case_CaseActivity_Activity_02_activity_date_time]',
            ],
            [
              'label' => 'Activities',
              'rewrite' => '',
              'alignment' => '',
              'type' => 'subsearch',
              'icons' => [
                [
                  'icon' => 'fa-list-check',
                  'side' => 'left',
                  'if' => [],
                ],
              ],
              'subsearch' => [
                'search' => 'Contact_Summary_Case_Activities',
                'filters' => [
                  [
                    'subsearch_field' => 'Activity_CaseActivity_Case_01.id',
                    'parent_field' => 'id',
                  ],
                ],
                'display' => 'Contact_Summary_Case_Activities_Table',
              ],
            ],
            [
              'text' => '',
              'style' => 'default',
              'size' => 'btn-xs',
              'icon' => 'fa-bars',
              'links' => [
                [
                  'entity' => 'Case',
                  'action' => 'view',
                  'join' => '',
                  'target' => '',
                  'icon' => 'fa-external-link',
                  'text' => 'Manage',
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'conditions' => [],
                ],
                [
                  'path' => 'civicrm/contact/view/case/editClient?reset=1&action=update&id=[id]&cid=[GROUP_CONCAT_Case_CaseContact_Contact_01_id]&context=case',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-random',
                  'text' => 'Assign to Another Client',
                  'style' => 'default',
                  'task' => '',
                  'conditions' => [],
                ],
                [
                  'task' => 'delete',
                  'entity' => 'Case',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-trash',
                  'text' => 'Delete',
                  'style' => 'danger',
                  'path' => '',
                  'action' => '',
                  'conditions' => [
                    [
                      'check user permission',
                      '=',
                      ['delete in CiviCase'],
                    ],
                  ],
                ],
              ],
              'type' => 'menu',
              'alignment' => 'text-right',
            ],
          ],
          'actions' => TRUE,
          'classes' => ['table', 'table-striped'],
          'columnMode' => 'custom',
          'cssRules' => [],
        ],
      ],
      'match' => ['saved_search_id', 'name'],
    ],
  ],
];
