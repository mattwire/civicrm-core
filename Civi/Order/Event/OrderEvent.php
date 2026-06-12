<?php

namespace Civi\Order\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class OrderEvent extends Event {

  /**
   * Array of submitted and calculated Contribution values
   *
   * @var array
   */
  private array $contributionValues;

  /**
   *  Array of submitted and calculated ContributionRecur values
   *
   * @var array
   */
  private array $contributionRecurValues;

  /**
   * Array of submitted and calculated lineItem values
   *
   * @var array
   */
  private array $lineItems;

  /**
   * OrderEvent constructor.
   *
   * @param array $contributionValues
   * @param array $contributionRecurValues
   * @param array $lineItems
   */
  public function __construct(array $contributionValues, array $contributionRecurValues, array $lineItems) {
    $this->contributionValues = $contributionValues;
    $this->contributionRecurValues = $contributionRecurValues;
    $this->lineItems = $lineItems;
  }

  /**
   * @return array
   */
  public function getContributionValues(): array {
    return $this->contributionValues;
  }

  /**
   * @return array
   */
  public function getContributionRecurValues(): array {
    return $this->contributionRecurValues;
  }

  /**
   * @return array
   */
  public function getLineItems(): array {
    return $this->lineItems;
  }

}
