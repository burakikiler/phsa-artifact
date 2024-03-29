<?php

use Drupal\acsf\AcsfMessage;

/**
 * AcsfMessageUnitTestFailure.
 */
class AcsfMessageUnitTestFailure extends AcsfMessage {

  /**
   * Implements AcsfMessage::sendMessage().
   */
  protected function sendMessage($url, $method, $endpoint, array $parameters, $username, $password) {
    return new AcsfMessageResponseUnitTest('unit.test.endpoint', 1, 'response body');
  }

}
