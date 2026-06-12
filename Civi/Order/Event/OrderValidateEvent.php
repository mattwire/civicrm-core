<?php

namespace Civi\Order\Event;

use Civi\Core\Event\ValidateEventTrait;

/**
 * OrderValidateEvent: Triggered when validating an Order.
 *
 * The Order is "readonly". You have access to all values that would be submitted/calculated
 *   via getContributionValues(), getContributionRecurValues() and getLineItems().
 * To check if other listeners have already added errors call getErrors().
 * To add errors call addError() or setErrors().
 */
class OrderValidateEvent extends OrderEvent {

  use ValidateEventTrait;

}
