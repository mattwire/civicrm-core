// https://civicrm.org/licensing
(function(angular, $, _) {
  "use strict";

  // Placeholder for a whole afform embedded in another afform.
  // Unlike a block, an embedded form brings its own entities and its own submit
  // handling, so it is placed as an opaque unit and edited in its own right.
  angular.module('afGuiEditor').component('afGuiEmbeddedForm', {
    templateUrl: '~/afGuiEditor/elements/afGuiEmbeddedForm.html',
    bindings: {
      node: '<',
      deleteThis: '&'
    },
    controller: function($scope, crmApi4, afGui) {
      const ts = $scope.ts = CRM.ts('org.civicrm.afform_admin');
      const ctrl = this;

      // Until a form is chosen the node is a bare placeholder, and its tag names no directive.
      this.isUnchosen = () => ctrl.node['#tag'] === 'af-embed';

      this.getFormType = () => ctrl.node['form-type'] || 'form';

      this.getAutocompleteParams = () => ({
        formName: 'afformAdmin',
        fieldName: 'autocompleteEmbeddedForm',
        filters: {type: ctrl.getFormType()},
      });

      // The autocomplete yields a form's name; the layout needs its directive, and the
      // canvas needs its title, so fetch both and let the node become that directive.
      this.chooseForm = function(name) {
        if (!arguments.length) {
          return null;
        }
        if (!name) {
          return;
        }
        crmApi4('Afform', 'get', {
          select: ['name', 'title', 'type', 'directive_name'],
          where: [['name', '=', name]],
        }, 0).then(function(form) {
          if (!form) {
            return;
          }
          afGui.meta.embeddedForms[form.directive_name] = form;
          delete ctrl.node['form-type'];
          ctrl.node['#tag'] = form.directive_name;
        });
      };

      this.getForm = () => afGui.meta.embeddedForms[ctrl.node['#tag']] || {};

      this.getTitle = () => ctrl.getForm().title || ctrl.node['#tag'];

      this.getEditUrl = () => '#/edit/' + ctrl.getForm().name;
    }
  });

})(angular, CRM.$, CRM._);
