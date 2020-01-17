<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * This api exposes CiviCRM PCP records.
 *
 *
 * @package CiviCRM_APIv3
 */

/**
 * Create or update a survey.
 *
 * @param array $params
 *          Array per getfields metadata.
 *
 * @return array api result array
 */
function civicrm_api3_pcp_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params, 'Pcp');
}

/**
 * Adjust Metadata for Create action.
 *
 * The metadata is used for setting defaults, documentation & validation.
 *
 * @param array $params
 *          Array of parameters determined by getfields.
 */
function _civicrm_api3_pcp_create_spec(&$params) {
  $params['title']['api.required'] = 1;
  $params['contact_id']['api.required'] = 1;
  $params['page_id']['api.required'] = 1;
  $params['pcp_block_id']['api.required'] = 1;
}

/**
 * Returns array of pcps matching a set of one or more properties.
 *
 * @param array $params
 *          Array per getfields
 *
 * @return array Array of matching pcps
 */
function civicrm_api3_pcp_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params, TRUE, 'Pcp');
}

/**
 * Delete an existing PCP.
 *
 * This method is used to delete any existing PCP given its id.
 *
 * @param array $params
 *          [id]
 *
 * @return array api result array
 */
function civicrm_api3_pcp_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust metadata for clone spec action.
 *
 * @param array $spec
 */
function _civicrm_api3_pcp_clone_spec(&$spec) {
  $spec['id']['title'] = 'PCP ID to clone';
  $spec['id']['type'] = CRM_Utils_Type::T_INT;
  $spec['id']['api.required'] = 1;
  $spec['is_active']['title'] = 'PCP page is Active (Default FALSE)?';
  $spec['is_active']['type'] = CRM_Utils_Type::T_BOOLEAN;
  $spec['is_active']['api.default'] = FALSE;
}

/**
 * Clone Job.
 *
 * @param array $params
 *
 * @return array
 * @throws \API_Exception
 * @throws \CiviCRM_API3_Exception
 */
function civicrm_api3_pcp_clone($params) {
  $params['is_active'] ?? $params['is_active'] = FALSE;
  if (empty($params['id'])) {
    throw new API_Exception("Mandatory key(s) missing from params array: id field is required");
  }
  $id = $params['id'];
  unset($params['id']);
  $newDAO = CRM_PCP_BAO_PCP::copy($id, $params);
  return civicrm_api3('Pcp', 'getsingle', ['id' => $newDAO->id]);
}
