<?php

/**
 * Class CRM_Core_LodashRatchetTest
 * @group headless
 */
class CRM_Core_LodashRatchetTest extends \PHPUnit\Framework\TestCase {

  /**
   * Assert that lodash (`_.`) usage in this repo's JS does not grow.
   *
   * CiviCRM core is moving away from lodash but has hundreds of pre-existing call
   * sites (see CLAUDE.md). `tests/lodash-ratchet-baseline.json` records the current
   * count per file; a file may keep or reduce its count, but exceeding it, or adding
   * usage to a file with no baseline entry, fails this test. When removing lodash
   * from a file, update its baseline entry (or delete it) in the same commit by
   * re-running `php tests/scripts/check-lodash-ratchet.php --generate`.
   */
  public function testUsageDoesNotGrow(): void {
    $script = Civi::paths()->getPath('[civicrm.root]/tests/scripts/check-lodash-ratchet.php');
    exec("php $script", $output, $exit);
    $this->assertEquals(0, $exit, implode("\n", $output));
  }

}
