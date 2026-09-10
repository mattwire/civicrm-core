'use strict';

describe('crmSearchFunction', function() {
  var $componentController, $rootScope;

  // Real metadata as returned by Civi\Search\Admin::getSqlFunctions()
  var sqlFunctions = [
    {
      name: 'SUM', title: 'Sum', description: 'The sum of all values in the grouping.', category: 'aggregate', data_type: null, options: false,
      params: [{flag_before: {'': null, DISTINCT: 'Distinct'}, must_be: ['SqlField'], label: 'Select', min_expr: 1, max_expr: 1}]
    },
    {
      name: 'RAND', title: 'Random number', description: 'Generates a random number between 0 and 1.', category: 'math', data_type: 'Float', options: false,
      params: [{optional: true, must_be: ['SqlNumber'], label: 'Select', min_expr: 1, max_expr: 1}]
    },
    {
      name: 'NOW', title: 'Now', description: 'The current date and time.', category: 'date', data_type: 'Timestamp', options: false,
      params: []
    },
    {
      name: 'EXTRACT', title: 'Partial Date', description: 'Extract part(s) of a date.', category: 'partial_date', data_type: null, options: false,
      params: [
        {label: 'Unit', flag_before: {DAY: 'Days', YEAR: 'Years'}},
        {name: 'FROM', must_be: ['SqlField', 'SqlString', 'SqlFunction'], label: 'Select', min_expr: 1, max_expr: 1}
      ]
    }
  ];

  beforeEach(function() {
    CRM.crmSearchAdmin.functions = sqlFunctions;
    CRM.crmSearchAdmin.pseudoFields = [];
    CRM.crmSearchAdmin.schema = [{
      name: 'Contribution',
      title: 'Contribution',
      title_plural: 'Contributions',
      params: ['select', 'where', 'groupBy'],
      fields: [
        {name: 'total_amount', label: 'Total Amount', data_type: 'Money'},
        {name: 'receive_date', label: 'Contribution Date', data_type: 'Timestamp'}
      ]
    }];
    CRM.stubMissingAngularModules();
    module('crmResource');
    module('crmSearchAdmin');
  });

  beforeEach(inject(function(_$componentController_, _$rootScope_) {
    $componentController = _$componentController_;
    $rootScope = _$rootScope_;
  }));

  // The parent controller the component requires; only mustAggregate() is reached here
  function makeCtrl(expr) {
    var ctrl = $componentController('crmSearchFunction', {$scope: $rootScope.$new()}, {
      mode: 'select',
      expr: expr,
      apiEntity: 'Contribution',
      apiParams: {select: [expr], groupBy: []},
      crmSearchAdmin: {mustAggregate: function() { return false; }}
    });
    ctrl.$onInit();
    return ctrl;
  }

  function offeredFunctions(ctrl) {
    return ctrl.getFunctions().results.reduce(function(names, group) {
      return names.concat(group.children.map(function(option) { return option.id; }));
    }, []);
  }

  describe('getFunctions', function() {
    it('offers functions that accept a field argument', function() {
      expect(offeredFunctions(makeCtrl('total_amount'))).toContain('SUM');
      expect(offeredFunctions(makeCtrl('receive_date'))).toContain('EXTRACT');
    });

    it('omits functions with no field argument to wrap', function() {
      var offered = offeredFunctions(makeCtrl('total_amount'));
      // RAND takes only a numeric seed, NOW takes nothing at all
      expect(offered).not.toContain('RAND');
      expect(offered).not.toContain('NOW');
    });
  });

  describe('canAddArg', function() {
    it('is false when the function takes no arguments', function() {
      expect(makeCtrl('NOW()').canAddArg()).toBe(false);
    });

    it('is false once a single-argument function has its argument', function() {
      expect(makeCtrl('SUM(total_amount)').canAddArg()).toBe(false);
    });
  });
});
