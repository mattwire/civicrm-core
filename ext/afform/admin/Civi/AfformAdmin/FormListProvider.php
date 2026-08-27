<?php

namespace Civi\AfformAdmin;

use Civi\Api4\OptionValue;
use Civi\Api4\SearchDisplay;
use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoSubscriber;
use CRM_AfformAdmin_ExtensionUtil as E;

/**
 * Builds the Form Builder list page, one tab per afform type.
 *
 * The tabs used to be written out by hand, one per built-in type, so a type added by an
 * extension appeared in none of them and its forms could not be found here at all.
 *
 * @service afform_admin.form_list
 * @internal
 */
class FormListProvider extends AutoSubscriber {

  const FORM_NAME = 'afAdminFormList';

  /**
   * Displays for the built-in types, whose names predate any convention.
   */
  const BUILTIN_DISPLAYS = [
    'form' => 'Afform_Forms_Table',
    'search' => 'Afform_Searches_Table',
    'block' => 'Afform_Blocks_Table',
    'system' => 'Afform_System_Table',
  ];

  /**
   * Tabs for the built-in types keep the icons they have always had, which are not
   * always the type's own.
   */
  const BUILTIN_ICONS = [
    'system' => 'fa-suitcase',
  ];

  public static function getSubscribedEvents(): array {
    return [
      'civi.afform.get' => ['addFormListLayout', -100],
    ];
  }

  public function addFormListLayout(GenericHookEvent $event): void {
    if (!$event->getLayout ||
      ($event->getTypes && !in_array('system', $event->getTypes, TRUE)) ||
      (!empty($event->getNames['name']) && !in_array(self::FORM_NAME, $event->getNames['name'], TRUE))
    ) {
      return;
    }
    $layout = '';
    foreach (self::getTabs() as $tab) {
      $layout .= self::renderTab($tab);
    }
    $event->afforms[] = [
      'name' => self::FORM_NAME,
      'layout' => '<af-tabset url-arg="tab" remember-selection="true">' . $layout . '</af-tabset>',
    ];
  }

  /**
   * One tab per afform type that has a table to show in it.
   *
   * A type with no display would give an empty tab, which is worse than no tab, so it
   * is left out. An extension supplies one by naming it after the type.
   *
   * @return array[]
   */
  private static function getTabs(): array {
    $displays = SearchDisplay::get(FALSE)
      ->addSelect('name', 'label')
      ->addWhere('saved_search_id.name', '=', 'Afform_Admin')
      ->execute()->indexBy('name')->column('label');
    $types = OptionValue::get(FALSE)
      ->addSelect('name', 'label', 'icon')
      ->addWhere('option_group_id.name', '=', 'afform_type')
      ->addWhere('is_active', '=', TRUE)
      ->addOrderBy('weight')
      ->execute();
    $tabs = [];
    foreach ($types as $type) {
      $display = self::BUILTIN_DISPLAYS[$type['name']] ?? 'Afform_' . ucfirst($type['name']) . '_Table';
      if (isset($displays[$display])) {
        $tabs[] = $type + ['display' => $display, 'display_label' => $displays[$display]];
      }
    }
    // Packaged forms are every type at once rather than a peer of the others, so that
    // tab belongs at the end however the types are weighted.
    usort($tabs, fn($a, $b) => ($a['name'] === 'system') <=> ($b['name'] === 'system'));
    return $tabs;
  }

  private static function renderTab(array $tab): string {
    $name = htmlspecialchars($tab['name'], ENT_QUOTES);
    $icon = htmlspecialchars(self::BUILTIN_ICONS[$tab['name']] ?? $tab['icon'] ?? '', ENT_QUOTES);
    $title = htmlspecialchars(self::getTabTitle($tab), ENT_QUOTES);
    $display = htmlspecialchars($tab['display'], ENT_QUOTES);
    $placeholder = htmlspecialchars(E::ts('Search by name...'), ENT_QUOTES);
    // Packaged forms are all packaged, so the custom/packaged filter means nothing there.
    $packagedFilter = $tab['name'] === 'system' ? '' : <<<HTML
      <af-field class="form-group" name="has_base" defn="{input_type: 'Radio', options: [{id: false, label: 'Custom'}, {id: true, label: 'Packaged'}], label: false, afform_default: false}"></af-field>
      HTML;
    $createMenu = $tab['name'] === 'system' ? '' : <<<HTML
      <af-admin-list-menu class="form-group pull-right" tab="$name"></af-admin-list-menu>
      HTML;
    // Braces belong to the value, not to the heredoc: `{$filter}` would interpolate.
    $filter = $tab['name'] === 'system' ? '{has_base: true}' : "{type: '$name'}";

    return <<<HTML
      <af-tab name="$name" title="$title" icon="$icon">
        <div af-fieldset="">
          <div class="form-inline crm-formbuilder-search-filters">
            $createMenu
            <af-field class="form-group" name="title,name" defn="{name: 'title', label: false, input_attrs: {placeholder: '$placeholder'}}"></af-field>
            $packagedFilter
          </div>
          <crm-search-display-table search-name="Afform_Admin" display-name="$display" filters="$filter" total-count="\$parent.count"></crm-search-display-table>
        </div>
      </af-tab>
      HTML;
  }

  /**
   * The tab lists every form of a type, so it wants a plural title, which the type's
   * own singular label cannot give. For anything but the built-ins the display's label
   * is used, that being the one piece of text written per tab rather than per type.
   */
  private static function getTabTitle(array $tab): string {
    $titles = [
      'form' => E::ts('Submission Forms'),
      'search' => E::ts('Search Forms'),
      'block' => E::ts('Field Blocks'),
      'system' => E::ts('Packaged Forms'),
    ];
    return $titles[$tab['name']] ?? $tab['display_label'] ?: $tab['label'];
  }

}
