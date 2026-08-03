<?php
use CRM_CivicrmAdminUi_ExtensionUtil as E;

if (!CRM_Core_Component::isEnabled('CiviCase')) {
  return [];
}

return [
  [
    'name' => 'SavedSearch_Case_Dashboard_Summary',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Case_Dashboard_Summary',
        'label' => E::ts('Case Dashboard Summary'),
        'api_entity' => 'Case',
        'api_params' => [
          'version' => 4,
          'select' => [
            'case_type_id:label',
            'COUNT(DISTINCT Case_Ongoing.id) AS count_ongoing',
            'COUNT(DISTINCT Case_Resolved.id) AS count_resolved',
            'COUNT(DISTINCT Case_Urgent.id) AS count_urgent',
          ],
          'orderBy' => [],
          'where' => [
            ['is_deleted', '=', FALSE],
          ],
          'groupBy' => ['case_type_id'],
          // Per-status breakdown, one self-join + COUNT per known status
          // value. API4's SUM()/COUNT() only accept a plain field argument
          // (SqlFunctionSUM/COUNT restrict 'must_be' to ['SqlField'], no
          // nested SqlFunction/SqlEquation), so a single conditional
          // aggregate expression like SUM(IF(status_id = 1, 1, 0)) can't be
          // expressed - this self-join is the workaround. Matched on each
          // status's machine `name` (Open/Closed/Urgent), not its `label`
          // (Ongoing/Resolved/Urgent) - the two diverge on a default
          // install. Hardcoded to this site's 3 default statuses; a site
          // with custom case statuses would need this list extended.
          'join' => [
            [
              'Case AS Case_Ongoing',
              'LEFT',
              ['id', '=', 'Case_Ongoing.id'],
              ['Case_Ongoing.status_id:name', '=', '"Open"'],
            ],
            [
              'Case AS Case_Resolved',
              'LEFT',
              ['id', '=', 'Case_Resolved.id'],
              ['Case_Resolved.status_id:name', '=', '"Closed"'],
            ],
            [
              'Case AS Case_Urgent',
              'LEFT',
              ['id', '=', 'Case_Urgent.id'],
              ['Case_Urgent.status_id:name', '=', '"Urgent"'],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'SavedSearch_Case_Dashboard_Summary_SearchDisplay_Case_Dashboard_Summary_Chart',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Case_Dashboard_Summary_Chart',
        'label' => E::ts('Case Dashboard Summary Chart'),
        'saved_search_id.name' => 'Case_Dashboard_Summary',
        'type' => 'chart-kit',
        'settings' => [
          'columns' => [
            [
              'axis' => 'w',
              'key' => 'case_type_id:label',
              'index' => 0,
              'name' => 'w_0',
              'label' => 'Case Type',
              'sourceDataType' => 'Option',
              'scaleType' => 'categorical',
              'datePrecision' => NULL,
              'reduceType' => 'list',
              'seriesType' => NULL,
              'dataLabelType' => 'none',
              'dataLabelFormatter' => 'none',
            ],
            [
              'axis' => 'y',
              'key' => 'count_ongoing',
              'index' => 1,
              'name' => 'y_0',
              'label' => 'Ongoing',
              'sourceDataType' => 'Integer',
              'scaleType' => 'numeric',
              'datePrecision' => NULL,
              'reduceType' => 'sum',
              'seriesType' => NULL,
              'dataLabelType' => 'none',
              'dataLabelFormatter' => 'none',
              'dataLabelColumnPrefix' => FALSE,
              // Matches the Ongoing case status's configured color, if any
              // (this site has none set, so it falls back to the default
              // palette) - see chartKitRowStack.js's note re: buildColumnColorScale
              // only supporting a static color, not a live :color lookup.
              'color' => NULL,
            ],
            [
              'axis' => 'y',
              'key' => 'count_resolved',
              'index' => 2,
              'name' => 'y_1',
              'label' => 'Resolved',
              'sourceDataType' => 'Integer',
              'scaleType' => 'numeric',
              'datePrecision' => NULL,
              'reduceType' => 'sum',
              'seriesType' => NULL,
              'dataLabelType' => 'none',
              'dataLabelFormatter' => 'none',
              'dataLabelColumnPrefix' => FALSE,
              // Matches the Resolved case status's configured color (set via
              // Administer > Case Statuses).
              'color' => '#5cb85c',
            ],
            [
              'axis' => 'y',
              'key' => 'count_urgent',
              'index' => 3,
              'name' => 'y_2',
              'label' => 'Urgent',
              'sourceDataType' => 'Integer',
              'scaleType' => 'numeric',
              'datePrecision' => NULL,
              'reduceType' => 'sum',
              'seriesType' => NULL,
              'dataLabelType' => 'none',
              'dataLabelFormatter' => 'none',
              'dataLabelColumnPrefix' => FALSE,
              // Matches the Urgent case status's configured color.
              'color' => '#d9534f',
            ],
          ],
          'format' => [
            // backgroundColor gets assigned straight through as a real CSS
            // `background-color`/`background` property (both support
            // var()), so this tracks the site's light/dark theme
            // automatically. labelColor is deliberately left unset here,
            // not given the analogous var(--crm-text-color) - see
            // chartKitRowStack.js's _doRender() for why: this exact site
            // config setting is what's fed into a
            // chartCanvas.style.setProperty('--crm-text-color', labelColor)
            // call elsewhere, and setting a custom property's value to a
            // var() reference to *itself* is invalid at computed-value-time
            // per spec - not a harmless no-op fallback to the inherited
            // value, as it might look - it broke --crm-text-color
            // inheritance for this chart's entire subtree. Text color is
            // instead handled entirely by this chart's own injected
            // stylesheet rule.
            'backgroundColor' => 'var(--crm-container-bg-color)',
            'height' => 300,
            'width' => 700,
            'padding' => [
              'outer' => 10,
              'clip' => 20,
              // Extra top padding makes room for the horizontal legend row
              // (chartKitRowStack.js positions it just below y=0).
              'top' => 35,
              'bottom' => 30,
              // Room for the case type labels, now to the left of the bars
              // (like the reference chart layout) rather than overlaid on
              // them. Long labels wrap onto multiple lines (see
              // chartKitRowStack.js's _wrapLabel) rather than needing a
              // margin wide enough for the longest one on a single line.
              'left' => 110,
              'right' => 10,
            ],
            'title' => NULL,
          ],
          // 'top'/'bottom' aren't in the stock SearchKit admin dropdown
          // (only none/left/right) - chartKitRowStack.js adds handling for
          // them specifically (a centered, horizontal legend row).
          'showLegend' => 'top',
          'maxBarHeight' => 22,
          'maxSegments' => 10,
          'chartType' => 'row_stacked',
        ],
      ],
      'match' => ['saved_search_id', 'name'],
    ],
  ],
];
