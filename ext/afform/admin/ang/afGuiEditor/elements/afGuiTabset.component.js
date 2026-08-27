// https://civicrm.org/licensing
(function(angular, $, _) {
  "use strict";

  angular.module('afGuiEditor').component('afGuiTabset', {
    templateUrl: '~/afGuiEditor/elements/afGuiTabset.html',
    bindings: {
      node: '<',
      entityName: '<',
      deleteThis: '&'
    },
    require: {
      editor: '^^afGuiEditor',
    },
    controller: function($scope, $element, afGui) {
      const ts = $scope.ts = CRM.ts('org.civicrm.afform_admin'),
        ctrl = this;

      this.orientations = [
        {id: 'horizontal', label: ts('Horizontal')},
        {id: 'vertical', label: ts('Vertical')},
      ];

      // Horizontal is the default, so it's stored as the absence of the attribute.
      this.getSetOrientation = function(value) {
        if (arguments.length) {
          if (value === 'vertical') {
            ctrl.node.orientation = 'vertical';
          }
          else {
            delete ctrl.node.orientation;
          }
        }
        return ctrl.node.orientation || 'horizontal';
      };

      this.itemTypes = [
        {id: 'tab', label: ts('Tab'), icon: 'fa-folder-o'},
        {id: 'link', label: ts('Link'), icon: 'fa-external-link'},
        {id: 'heading', label: ts('Section heading'), icon: 'fa-header'},
        {id: 'divider', label: ts('Divider'), icon: 'fa-minus'},
      ];

      this.linkTargets = [
        {id: 'path', label: ts('CiviCRM page')},
        {id: 'href', label: ts('External url')},
      ];

      // Only a tab holds content; the other types are nav-only.
      this.isTab = (item) => !item.type || item.type === 'tab';

      this.$onInit = function() {
        this.selectedTab = 0;
        this.searchDisplays = [];

        this.node['#children'].forEach((tab) => {
          if (ctrl.isTab(tab)) {
            tab['#children'] = tab['#children'] || [];
          }
        });

        // Bootstrap3 doesn't handle the dropdown markup we're using (nesting the dropdown button inside the tabs)
        // So this emulates the bs3 dropdown.js functionality in AngularJS.
        // TODO: This is actually more efficient than bs3 because the menu can be removed from the dom instead of hidden,
        // so this probably ought to be turned into a directive and moved to crmUi.js
        $(document).on('click', function (e) {
          $scope.$evalAsync(() => handleClick(e));
        });

        // Show or hide a tab dropdown
        function handleClick(e) {
          if ($($element).has(e.target).length) {
            const thisTab = $(e.target).closest('button[data-tab-id]');
            if (thisTab.length) {
              const tabIndex = thisTab.data('tabId');
              ctrl.menuOpen = ctrl.menuOpen === tabIndex ? false : tabIndex;
              return;
            }
          }
          ctrl.menuOpen = false;
        }
      };

      this.addTab = () => {
        this.node['#children'].push({
          '#tag': 'af-tab',
          'title': this.isPages() ? ts('New Page') : ts('New Tab'),
          '#children': [],
        });
        this.selectTab(this.node['#children'].length - 1);
      };

      this.addItem = (type) => {
        const item = {'#tag': 'af-tab'};
        if (type !== 'tab') {
          item.type = type;
        }
        if (type === 'tab' || type === 'heading') {
          item.title = type === 'heading' ? ts('New Section') : ts('New Tab');
        }
        if (type === 'link') {
          item.title = ts('New Link');
          item.path = '';
        }
        if (type === 'tab') {
          item['#children'] = [];
        }
        this.node['#children'].push(item);
        this.selectTab(this.node['#children'].length - 1);
      };

      // Changing type has to clear the attributes that no longer apply, or they would
      // be silently carried along and re-appear if the type is switched back.
      this.getSetItemType = function(item) {
        return function(type) {
          if (!arguments.length) {
            return item.type || 'tab';
          }
          ['type', 'path', 'href', 'target', 'icon', 'name', '#children'].forEach((key) => delete item[key]);
          if (type !== 'tab') {
            item.type = type;
          }
          if (type === 'tab') {
            item['#children'] = [];
          }
          if (type === 'link') {
            item.path = '';
          }
          if (type === 'divider') {
            delete item.title;
          }
          else if (!item.title) {
            item.title = ts('Untitled');
          }
        };
      };

      // A link is either an internal CiviCRM path or an external url, never both.
      this.getLinkTarget = (item) => ('href' in item ? 'href' : 'path');

      this.getSetLinkTarget = function(item) {
        return function(kind) {
          if (!arguments.length) {
            return ctrl.getLinkTarget(item);
          }
          delete item.path;
          delete item.href;
          item[kind] = '';
        };
      };

      this.isNewWindow = (item) => item.target === '_blank';

      this.toggleNewWindow = (item) => {
        if (ctrl.isNewWindow(item)) {
          delete item.target;
        }
        else {
          item.target = '_blank';
        }
      };

      this.deleteTab = function(tabIndex) {
        this.node['#children'].splice(tabIndex, 1);
        ctrl.editor.onRemoveElement();
        this.selectTab(0);
      };

      this.selectTab = function(tabIndex) {
        if (this.isTab(this.node['#children'][tabIndex])) {
          this.selectedTab = tabIndex;
        }
      };

      this.pickIcon = function(tab) {
        afGui.pickIcon().then((val) => {
          tab.icon = val;
        });
      };

      this.getDataEntity = function() {
        return $element.attr('data-entity') || '';
      };

      // When opening the menu, fetch search displays to show in the `af-gui-tab-count` select
      this.getSearchDisplays = function(tabIndex) {
        const displayTags = afGui.getFormElements(this.node['#children'][tabIndex]['#children'] || [], (item) => (item['#tag'] && afGui.meta.searchDisplayTags.includes(item['#tag']) && item['search-name']));
        this.searchDisplays[tabIndex] = displayTags.map(item => {
          return {
            tag: item,
            defn: afGui.getSearchDisplay(item['search-name'], item['display-name'])
          };
        });
      };

      // Set a search display in the tab to have the `total-count` attribue which will control the count shown in the tab
      function getSetCount(tabIndex, displayIndex) {
        if (arguments.length === 1) {
          return ctrl.searchDisplays[tabIndex].findIndex(item => item.tag['total-count'] === '$parent.count');
        }
        ctrl.searchDisplays[tabIndex].forEach((item, index) => {
          if (index === displayIndex) {
            item.tag['total-count'] = '$parent.count';
          } else {
            delete item.tag['total-count'];
          }
        });
      }

      this.getSetCount = function (tabIndex) {
        return _.wrap(tabIndex, getSetCount);
      };

      this.isPages = () => this.node['page-nav-buttons'];

      this.isVertical = () => this.node.orientation === 'vertical';

    }
  });

})(angular, CRM.$, CRM._);
