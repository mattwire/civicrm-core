(function(angular, $, _) {
  "use strict";

  let tabNumber = 0;

  angular.module('af').component('afTabset', {
    templateUrl: '~/af/afTabset.html',
    transclude: true,
    require: {
      afFormCtrl: '?^^afForm',
    },
    bindings: {
      urlArg: '@',
      orientation: '@',
      selectedTab: '=?',
      rememberSelection: '<',
      pageNavButtons: '<',
      pageNavSubmitText: '@',
    },
    controller: function($scope, $element, $location, $timeout) {
      const ts = $scope.ts = CRM.ts('org.civicrm.afform');

      this.tabs = [];

      this.$onInit = function() {
        $element.addClass('crm-tabset');
        this.isVertical = this.orientation === 'vertical';
        if (this.isVertical) {
          $element.addClass('crm-tabset-vertical');
        }

        if (this.urlArg) {
          // Afforms are not routed (@see afCore.js), so `$bindToRoute` is unavailable
          // outside the Angular SPA. Bind to the location's search params directly.
          this.selectedTab = $location.search()[this.urlArg] || this.selectedTab;
          $scope.$watch(() => $location.search()[this.urlArg], (tabName) => {
            if (tabName && tabName !== this.selectedTab) {
              this.selectTab(tabName);
            }
          });
          $scope.$watch('$ctrl.selectedTab', (tabName) => {
            if (tabName) {
              $location.search(this.urlArg, tabName);
            }
          });
        }

        $timeout(() => {
          // A url or cached selection may name a tab that no longer exists.
          if (this.selectedTab && this.findTabIndex(this.selectedTab) < 0) {
            this.selectedTab = null;
          }
          if (!this.selectedTab && this.rememberSelection) {
            const rememberedName = CRM.cache.get(this.getCacheKey());
            if (rememberedName) {
              this.selectTab(rememberedName);
            }
          }
          const firstTab = this.tabs.find((tab) => this.isSelectable(tab));
          if (!this.selectedTab && firstTab) {
            this.selectTab(firstTab.name);
          }
        });

        if (this.rememberSelection) {
          // Watch for tab changes and remember the selection name
          $scope.$watch('$ctrl.selectedTab', (newTab) => {
            if (newTab) {
              CRM.cache.set(this.getCacheKey(), newTab);
            }
          });
        }
      };

      this.addTab = (tab) => {
        this.tabs.push(tab);
      };

      this.selectTab = (tabName) => {
        const newIndex = this.findTabIndex(tabName);
        // Ignore a tab that doesn't exist, e.g. a stale url or remembered selection
        if (newIndex < 0 || !this.isSelectable(this.tabs[newIndex])) {
          return;
        }
        const currentIndex = this.findTabIndex(this.selectedTab);

        // validate before moving forward
        if (newIndex > currentIndex) {
          const currentInvalid = this.tabs[currentIndex]?.findInvalid();
          if (currentInvalid && currentInvalid.length) {
            return;
          }
        }
        this.selectedTab = tabName;
      };

      this.findTabIndex = (tabName) => this.tabs.findIndex((tab) => tab.name === tabName);

      // Headings, dividers and links sit in the nav but cannot be navigated to,
      // and neither can a tab the user lacks the permission for.
      this.isSelectable = (tab) => tab.isTab() && tab.isVisible();

      // Whether an item is worth a row in the nav. Permission-gated items drop out, and
      // once they have, the decoration around them can be left labelling or separating
      // nothing, which reads as a mistake in the form rather than as a hidden item.
      this.isNavItemVisible = (index) => {
        const tab = this.tabs[index];
        if (!tab.isVisible()) {
          return false;
        }
        // A heading labels the items below it, as far as the next heading or divider.
        if (tab.type === 'heading') {
          for (let i = index + 1; i < this.tabs.length; i++) {
            if (this.tabs[i].type === 'heading' || this.tabs[i].type === 'divider') {
              return false;
            }
            if (this.tabs[i].isVisible()) {
              return true;
            }
          }
          return false;
        }
        // A divider needs something left to separate on either side of it.
        if (tab.type === 'divider') {
          return this.hasContentBeside(index, -1) && this.hasContentBeside(index, 1);
        }
        return true;
      };

      this.hasContentBeside = (index, step) => {
        for (let i = index + step; i >= 0 && i < this.tabs.length; i += step) {
          // Another divider is not content; skip past it.
          if (this.tabs[i].type !== 'divider' && this.isNavItemVisible(i)) {
            return true;
          }
        }
        return false;
      };

      this.findAdjacentTab = (step) => {
        let index = this.findTabIndex(this.selectedTab) + step;
        while (this.tabs[index] && !this.isSelectable(this.tabs[index])) {
          index += step;
        }
        return this.tabs[index];
      };

      this.hasPrevious = () => !!this.findAdjacentTab(-1);

      this.hasNext = () => !!this.findAdjacentTab(1);

      this.goToNext = () => {
        const nextTab = this.findAdjacentTab(1);
        if (nextTab) {
          this.selectTab(nextTab.name);
        }
      };

      this.goToPrevious = () => {
        const previousTab = this.findAdjacentTab(-1);
        if (previousTab) {
          this.selectTab(previousTab.name);
        }
      };

      // Deferring a tab's contents until it is first shown avoids running every embedded
      // search and prefill on page load. Two cases must stay eager:
      // - a submission form, because an unrendered `af-fieldset` never registers with
      //   `afForm` and its values would be silently dropped from the submission;
      // - page-nav mode, because `findInvalid()` gates forward navigation on the DOM.
      this.isLazy = () => !this.afFormCtrl && !this.pageNavButtons;

      this.getFormName = () => this.afFormCtrl?.getFormMeta().name ?? $scope.$parent.meta.name;

      this.getCacheKey = () => this.getFormName() + 'SelectedTab';
    }
  });

  angular.module('af').directive('afTab', function() {
    return {
      restrict: 'E',
      require: '^afTabset',
      scope: {
        title: '@',
        icon: '@',
        count: '@',
        name: '@',
        type: '@',
        path: '@',
        href: '@',
        target: '@',
        permission: '@',
      },
      // Transclude allows the tab scope to be accessed from the inner html as $parent
      transclude: true,
      // ngShow will toggle the class `ng-hide`; also adding it to the markup avoids initial flash
      template: '<div ng-transclude role="tabpanel" ng-if="rendered" ng-show="afTabsetCtrl.selectedTab === name" class="ng-hide"></div>',
      link: function (scope, element, attrs, afTabsetCtrl) {
        scope.name = scope.name || 'tab' + tabNumber++;
        scope.afTabsetCtrl = afTabsetCtrl;
        scope.findInvalid = () => element.find('.ng-invalid');
        // Only a plain tab owns a panel; the other types are nav decoration or navigate away.
        scope.isTab = () => !scope.type || scope.type === 'tab';
        // Hiding an item the user has no use for. Not a security boundary: whatever the
        // tab contains enforces its own permissions server-side.
        scope.isVisible = () => !scope.permission || CRM.checkPerm(scope.permission);
        // `path` is internal and must go through CRM.url() because the CMS, not CiviCRM,
        // decides what a CiviCRM path looks like. `href` is used verbatim.
        scope.getUrl = () => scope.href || (scope.path ? CRM.url(scope.path) : '');
        // Render on first selection, then keep the contents alive so that tab state
        // and unsaved input survive switching away and back.
        scope.rendered = scope.isTab() && scope.isVisible() && !afTabsetCtrl.isLazy();
        if (scope.isTab() && !scope.rendered) {
          const stopWatching = scope.$watch(() => afTabsetCtrl.selectedTab === scope.name, (isSelected) => {
            if (isSelected) {
              scope.rendered = true;
              stopWatching();
            }
          });
        }
        afTabsetCtrl.addTab(scope);
      }
    };
  });
})(angular, CRM.$, CRM._);
