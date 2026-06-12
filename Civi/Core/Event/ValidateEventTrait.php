<?php

namespace Civi\Core\Event;

use Psr\Log\LogLevel;

trait ValidateEventTrait {

  /**
   * @var array
   */
  private array $errors = [];

  /**
   * Alias for addError()
   *
   * @param string $errorMsg
   *
   * @return void
   *
   * @deprecated in 6.16
   */
  public function setError(string $errorMsg): void {
    \CRM_Core_Error::deprecatedFunctionWarning('addError');
    $this->errors[] = $errorMsg;
  }

  /**
   * Replace all existing errors with the specified array
   *
   * @param array $errors
   *
   * @return void
   */
  public function setErrors(array $errors): void {
    $this->errors = $errors;
  }

  /**
   * Add an error
   *
   * @param string $errorMsg
   * @param string $errorCode
   * @param string $level
   *
   * @return void
   */
  public function addError(string $errorMsg, string $errorCode = 'error', string $level = LogLevel::ERROR): void {
    $this->errors[] = ['message' => $errorMsg, 'code' => $errorCode, 'level' => $level];
  }

  /**
   * Get all errors that have been set by other callers
   *
   * @return array
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * Helper function to check if any errors were defined
   *
   * @return bool
   */
  public function hasErrors(): bool {
    return count($this->errors) > 0;
  }

  /**
   * Helper function for callers that just want to display the error string
   *
   * @param string $separator
   *
   * @return string
   */
  public function getErrorsAsString(string $separator = "\n"): string {
    $errorStrings = array_column($this->errors, 'message');
    return implode($separator, $errorStrings);
  }

  /**
   * Ordered by most serious first. These are the levels that are treated as an "error".
   *
   * @var array
   */
  private array $errorLevels = [
    LogLevel::EMERGENCY,
    LogLevel::ALERT,
    LogLevel::CRITICAL,
    LogLevel::ERROR,
  ];

  /**
   * Helper function to get the maximum severity of error
   *
   * @return string|null
   */
  public function getMaxErrorLevel(): ?string {
    $levels = array_column($this->errors, 'level');
    // Returns the first match (ie. the most severe)
    return current(array_filter(
      $this->errorLevels,
      fn($level) => in_array($level, $levels)
    )) ?: NULL;
  }

  /**
   * We might have defined "errors" which are level info, warning and should be shown to the user but won't "fail" validation.
   * If we return TRUE, assume we have something that needs resolving / is invalid.
   *
   * @return bool
   */
  public function isError(): bool {
    return in_array($this->getMaxErrorLevel(), $this->errorLevels);
  }

}
