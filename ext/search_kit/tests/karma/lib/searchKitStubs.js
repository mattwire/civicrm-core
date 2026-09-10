// Page variables and module shims so search_kit's angular modules can load under karma.
(function(angular, CRM) {
  'use strict';

  CRM.config = CRM.config || {};
  CRM.config.resourceBase = CRM.config.resourceBase || '/core/';

  CRM.crmSearchAdmin = CRM.crmSearchAdmin || {
    schema: [],
    functions: [],
    pseudoFields: [],
    operators: [],
    displayTypes: [],
    styles: [],
    tags: [],
    joins: {},
    modules: {},
  };
  CRM.crmSearchActions = CRM.crmSearchActions || {tasks: {}, fields: {}, modules: {}};
  CRM.crmSearchDisplay = CRM.crmSearchDisplay || {defaultPagerSize: 25};

  // CRM.angular.requires is generated from the developer's own site, so it names whatever
  // display-type modules the extensions enabled there contribute (crmChartKit, etc.).
  // Karma only loads core and a few bundled extensions, so define the rest as empty.
  CRM.stubMissingAngularModules = function() {
    angular.forEach(CRM.angular.requires, function(requires) {
      requires.forEach(function(name) {
        try {
          angular.module(name);
        }
        catch (e) {
          angular.module(name, []);
        }
      });
    });
  };

})(angular, CRM);
