<?php

namespace Civi\Order\Event;

class OrderCreateEvent extends OrderEvent {

  /**
   * The Contribution ID created for the Order. Will be NULL for preSave.
   *
   * @var int|null
   */
  private ?int $contributionID;

  /**
   * OrderCreateEvent constructor.
   *
   * @param int|null $contributionID
   * @param array $contributionValues
   * @param array $contributionRecurValues
   * @param array $lineItems
   */
  public function __construct(?int $contributionID, array $contributionValues, array $contributionRecurValues, array $lineItems) {
    parent::__construct($contributionValues, $contributionRecurValues, $lineItems);
    $this->contributionID = $contributionID;
  }

  public function getContributionID(): ?int {
    return $this->contributionID;
  }

}
