<?php

namespace Drupal\acsf;

use Drupal\Component\Utility\UrlHelper;

/**
 * This class is an implementation of our XML-RPC service.
 */
class AcsfMessageRest extends AcsfMessage {

  /**
   * Maximum amount of retries before giving up sending a message.
   *
   * @var int
   */
  protected $retryMax = 3;

  /**
   * Number of seconds to wait before trying again after sending failed.
   *
   * @var int
   */
  protected $retryWait = 5;

  /**
   * {@inheritdoc}
   */
  public function __construct($method, $endpoint, array $parameters = [], AcsfConfig $config = NULL, $ah_site = NULL, $ah_env = NULL, Closure $callback = NULL) {
    if (empty($config)) {
      $config = new AcsfConfigRest($ah_site, $ah_env);
    }
    parent::__construct($method, $endpoint, $parameters, $config, $ah_site, $ah_env, $callback);
  }

  /**
   * Implements AcsfMessage::sendMessage().
   */
  protected function sendMessage($url, $method, $endpoint, array $parameters, $username, $password) {

    $error = null;
    $useragent = sprintf('%s.%s %s', $this->ahSite, $this->ahEnv, gethostname());
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_USERPWD, $username . ":" . $password);
    // @todo can we remove this in prod?
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

    // If it is not a GET request, set the method here.
    if ($method != 'GET') {
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    }

    // If we are sending parameters, set the query string or POST fields here.
    $query_string = '';
    if ($method == 'GET' && !empty($parameters)) {
      $query_string = '?' . UrlHelper::buildQuery($parameters);
    }
    elseif (!empty($parameters)) {
      $data_string = json_encode($parameters, JSON_THROW_ON_ERROR);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
      curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data_string),
      ]);
    }

    $full_url = sprintf('%s/%s%s', $url, $endpoint, $query_string);
    curl_setopt($curl, CURLOPT_URL, $full_url);

    $attempts = 0;
    $response = FALSE;

    while (!$response && ++$attempts <= $this->retryMax) {
      $response = curl_exec($curl);
      if (!$response) {
        $error = curl_error($curl);
        \Drupal::logger('AcsfMessageRest')->error($error);
        sleep($this->retryWait);
      }
    }

    if (!$response) {
      throw new AcsfMessageFailureException(sprintf('Error reaching url "%s" with method "%s." Returned error "%s."', $full_url, $method, $error));
    }

    $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $response_body = json_decode($response, TRUE, 512, JSON_THROW_ON_ERROR);

    return new AcsfMessageResponseRest($endpoint, $response_code, $response_body);
  }

}
