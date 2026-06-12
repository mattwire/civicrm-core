<?php

namespace Civi\Order\Event;

class OrderCreateEvent extends OrderEvent {

  /**
   * The Contribution ID created for the Order.
   *
   * @var int|null
   */
  private ?int $contributionID;

  /**
   * The ContributionRecur ID created for the Order.
   *
   * @var int|null
   */
  private ?int $contributionRecurID;

  /**
   * OrderCreateEvent constructor.
   *
   * @param int|null $contributionID
   * @param array $contributionValues
   * @param array $contributionRecurValues
   * @param array $lineItems
   */
  public function __construct(?int $contributionID, ?int $contributionRecurID, array $contributionValues, array $contributionRecurValues, array $lineItems) {
    parent::__construct($contributionValues, $contributionRecurValues, $lineItems);
    $this->contributionID = $contributionID;
    $this->contributionRecurID = $contributionRecurID;
  }

  public function getContributionID(): ?int {
    return $this->contributionID;
  }

  public function setContributionID(?int $contributionID): void {
    $this->contributionID = $contributionID;
  }

  public function getContributionRecurID(): ?int {
    return $this->contributionRecurID;
  }

  public function setContributionRecurID(?int $contributionRecurID): void {
    $this->contributionRecurID = $contributionRecurID;
  }

}
