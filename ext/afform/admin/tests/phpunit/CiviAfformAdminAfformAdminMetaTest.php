<?php

use CRM_AfformAdmin_ExtensionUtil as E;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CiviAfformAdminAfformAdminMetaTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface {

  /**
   * Setup used when HeadlessInterface is implemented.
   *
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   *
   * @link https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   *
   * @return \Civi\Test\CiviEnvBuilder
   *
   * @throws \CRM_Extension_Exception_ParseException
   */
  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp():void {
    parent::setUp();
  }

  public function tearDown():void {
    parent::tearDown();
  }

  /**
   * Verify that getLocales works without php error.
   */
  public function testAdminSettings():void {
    $adminSettings = \Civi\AfformAdmin\AfformAdminMeta::getAdminSettings();
    $this->assertSame([], $adminSettings['locales']);
  }

  /**
   * A dynamic foreign key needs its table-to-entity map and the name of the field
   * that controls it, so the form builder can resolve which entity it points to.
   */
  public function testDynamicForeignKeyMetadata():void {
    $fields = \Civi\AfformAdmin\AfformAdminMeta::getFields('Note');

    $this->assertEquals('entity_table', $fields['entity_id']['input_attrs']['control_field']);
    $this->assertEquals('Contact', $fields['entity_id']['dfk_entities']['civicrm_contact']);
    // A plain foreign key has no map to resolve
    $this->assertEquals('Contact', $fields['contact_id']['fk_entity']);
    $this->assertArrayNotHasKey('dfk_entities', array_filter($fields['contact_id']));
  }

  /**
   * A form or search Afform can be embedded whole in another form, so the editor
   * needs them listed separately from blocks, which have different semantics.
   */
  public function testOnlyFormsUsedInTheLayoutAreLoadedWithIt():void {
    $this->createEmbedFixtures('<div class="af-container"><afform-test-embed-guest></afform-test-embed-guest></div>');
    try {
      $info = \Civi\Api4\Afform::loadAdminData(FALSE)
        ->setDefinition(['name' => 'afformTestEmbedHost'])
        ->execute()->single();

      $directives = array_column($info['embeddedForms'], 'directive_name');
      // The form this one embeds has to come with it, or the canvas cannot draw it.
      $this->assertSame(['afform-test-embed-guest'], $directives);
      // Everything else is found through the autocomplete, so a site with hundreds of
      // forms does not send all of them to the editor.
      $this->assertNotContains('afform-test-embed-bystander', $directives);
    }
    finally {
      $this->revertEmbedFixtures();
    }
  }

  public function testEmbeddableFormAutocompleteOffersOnlyStandaloneForms():void {
    $this->createEmbedFixtures('<div class="af-container"></div>');
    try {
      $searchOnly = array_column((array) \Civi\Api4\Afform::autocomplete(FALSE)
        ->setFormName('afformAdmin')
        ->setFieldName('autocompleteEmbeddedForm')
        ->setFilters(['type' => 'search'])
        ->setInput('afformTestEmbed')
        ->execute(), 'id');
      $this->assertContains('afformTestEmbedGuest', $searchOnly);
      $this->assertNotContains('afformTestEmbedHost', $searchOnly);

      // With no type named, both standalone types are offered and nothing else. A block
      // is inlined into its parent's entities and a dashboard is a page in its own right,
      // so neither can be embedded.
      $anyType = array_column((array) \Civi\Api4\Afform::autocomplete(FALSE)
        ->setFormName('afformAdmin')
        ->setFieldName('autocompleteEmbeddedForm')
        ->setInput('afformTestEmbed')
        ->execute(), 'id');
      $this->assertContains('afformTestEmbedGuest', $anyType);
      $this->assertContains('afformTestEmbedHost', $anyType);
      $this->assertNotContains('afformTestEmbedBlock', $anyType);
    }
    finally {
      $this->revertEmbedFixtures();
    }
  }

  private function createEmbedFixtures(string $hostLayout):void {
    $forms = [
      'afformTestEmbedHost' => ['form', $hostLayout],
      'afformTestEmbedGuest' => ['search', '<div class="af-container"></div>'],
      'afformTestEmbedBystander' => ['form', '<div class="af-container"></div>'],
      'afformTestEmbedBlock' => ['block', '<div class="af-container"></div>'],
    ];
    foreach ($forms as $name => [$type, $layout]) {
      $create = \Civi\Api4\Afform::create(FALSE)
        ->addValue('name', $name)
        ->addValue('title', $name)
        ->addValue('type', $type)
        ->addValue('layout', $layout);
      if ($type === 'block') {
        $create->addValue('entity_type', 'Individual');
      }
      $create->execute();
    }
  }

  private function revertEmbedFixtures():void {
    \Civi\Api4\Afform::revert(FALSE)
      ->addWhere('name', 'IN', [
        'afformTestEmbedHost',
        'afformTestEmbedGuest',
        'afformTestEmbedBystander',
        'afformTestEmbedBlock',
      ])
      ->execute();
  }

}
