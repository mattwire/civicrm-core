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

namespace Civi\Api4\Action\MessageTemplate;

/**
 * Resolve the effective TokenProcessor schema for a message template:
 * its own stored `usage`, else defaults derived from its `workflow_name`,
 * else a minimal fallback -- merged with any extra schema keys the calling
 * screen already knows about.
 *
 * Returns a single row `{schema: [...], allSchema: [...]}` -- not a list of
 * MessageTemplate entity records -- so this extends AbstractAction rather
 * than BasicGetAction/BasicGetFieldsAction, which would otherwise filter
 * the result down to recognised MessageTemplate field names.
 *
 * @method $this setId(int $id)
 * @method int getId()
 * @method $this setWorkflowName(string $workflowName)
 * @method string getWorkflowName()
 * @method $this setExtraSchema(array $extraSchema)
 * @method array getExtraSchema()
 */
class GetTokenSchema extends \Civi\Api4\Generic\AbstractAction {

  /**
   * The message template to resolve a schema for. Omit when resolving
   * purely from $workflowName/$extraSchema (e.g. a template not yet saved).
   *
   * @var int|null
   */
  protected $id;

  /**
   * Used only when the template referenced by $id (if any) has no
   * 'workflow_name' of its own, or when resolving without an $id.
   *
   * @var string|null
   */
  protected $workflowName;

  /**
   * Additional schema keys already known by the calling screen, e.g. a
   * pre-selected ScheduleReminders mapping, or a Case ID already on hand.
   *
   * @var array
   */
  protected $extraSchema = [];

  public function _run(\Civi\Api4\Generic\Result $result) {
    $result[] = [
      'schema' => \CRM_Core_BAO_MessageTemplate::resolveTokenSchema($this->id, $this->workflowName, $this->extraSchema),
      'allSchema' => \CRM_Core_BAO_MessageTemplate::getAllTokenSchemaKeys(),
    ];
  }

}
