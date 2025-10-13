(function(angular, $, _) {
  "use strict";

  angular.module('crmSearchTasks').component('crmSearchBatchRunner', {
    bindings: {
      entity: '<',
      action: '@',
      ids: '<',
      idField: '@',
      params: '<',
      displayCtrl: '<',
      isLink: '<',
      success: '&',
      error: '&',
      task: '<'
    },
    // templateUrl: '~/crmSearchTasks/crmSearchBatchRunner.html',
    controller: function($scope, $timeout, $interval, crmApi4, searchTaskBaseTrait) {
      const ts = $scope.ts = CRM.ts('org.civicrm.search_kit');

      // const ctrl = this

      const ctrl = angular.extend(this, $scope.model, searchTaskBaseTrait);

      let currentBatch = 0;
      let totalBatches;
      let processedCount = 0;
      let countMatched = 0;
      let incrementer;
      let batchResult;

      this.progress = 0;

      this.task = $scope.task;

      // Number of records to process in each batch
      const BATCH_SIZE = 500;
      // Estimated number of seconds each batch will take (for auto-incrementing the progress bar)
      const EST_BATCH_TIME = 5;

      this.$onInit = function() {
        if (ctrl.action === 'create') {
          ctrl.ids = [0];
        }
        totalBatches = Math.ceil(ctrl.ids.length / BATCH_SIZE);
        runBatch();
      };

      this.$onDestroy = function() {
        stopIncrementer();
      };

      function runBatch() {
        CRM.payment.swalFire({
          title:  ts(ctrl.task.apiBatch.runTitle || ctrl.task.title || '', {1: ctrl.ids.length, 2: ctrl.entityTitle}),
          text: ts(ctrl.task.apiBatch.runMsg || '', {1: ctrl.ids.length, 2: ctrl.entityTitle}),
          allowOutsideClick: false,
          willOpen: function () {
            Swal.showLoading(Swal.getConfirmButton());
          }
        }, '', false);
        let entityName = ctrl.entity;
        let actionName = ctrl.action;
        ctrl.first = currentBatch * BATCH_SIZE;
        ctrl.last = (currentBatch + 1) * BATCH_SIZE;
        if (ctrl.last > ctrl.ids.length) {
          ctrl.last = ctrl.ids.length;
        }
        var params = _.cloneDeep(ctrl.params);
        if (ctrl.action === 'save') {
          // For the save action, take each record from params and copy it with each supplied id
          params.records = _.transform(ctrl.ids.slice(ctrl.first, ctrl.last), function(records, id) {
            _.each(_.cloneDeep(ctrl.params.records || [{}]), function(record) {
              record[ctrl.idField || 'id'] = id;
              records.push(record);
            });
          });
        } else if (ctrl.isLink && ctrl.action === 'update' && ctrl.ids.length === 1 && ctrl.displayCtrl) {
          // When updating a single record from a link, use the inlineEdit action
          entityName = 'SearchDisplay';
          actionName = 'inlineEdit';
          angular.extend(params, ctrl.displayCtrl.getApiParams(null));
          // Where clause is only relevant to updating > 1 record
          delete params.where;
          params.rowKey = ctrl.ids[0];
        } else if (ctrl.action !== 'create') {
          // For other batch actions (update, delete), add supplied ids to the where clause
          params.where = params.where || [];
          params.where.push([ctrl.idField || 'id', 'IN', ctrl.ids.slice(ctrl.first, ctrl.last)]);
        }
        crmApi4(entityName, actionName, params).then(
          function(result) {
            stopIncrementer();
            ctrl.progress = Math.floor(100 * ++currentBatch / totalBatches);
            processedCount += result.countFetched;
            countMatched += (result.countMatched || result.count);
            // Gather all results into one super collection
            if (batchResult) {
              batchResult.push(...result);
            } else {
              batchResult = result;
            }
            if (ctrl.last >= ctrl.ids.length) {
              $timeout(function() {
                // Return a complete record of all batches
                batchResult.batchCount = processedCount;
                batchResult.countMatched = countMatched;
                CRM.payment.swalClose();
                ctrl.success({result: batchResult});
              }, 500);
            } else {
              runBatch();
            }
          }, function(error) {
            // CRM.alert(error.error_message, ts('Error'), 'error');
            debugger;
            Swal.fire({
              icon: 'error',
              title: ts('Error'),
              text: error.error_message
            });
            ctrl.error();
          });
        // Move the bar every second to simulate progress between batches
        incrementer = $interval(function(i) {
          var est = Math.floor(100 * (currentBatch + (i / EST_BATCH_TIME)) / totalBatches);
          ctrl.progress = est > 100 ? 100 : est;
        }, 1000, EST_BATCH_TIME);
      }

      function stopIncrementer() {
        if (angular.isDefined(incrementer)) {
          $interval.cancel(incrementer);
          incrementer = undefined;
        }
      }

    }
  });

})(angular, CRM.$, CRM._);
