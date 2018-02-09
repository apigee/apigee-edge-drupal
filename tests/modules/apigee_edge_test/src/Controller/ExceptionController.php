<?php

namespace Drupal\apigee_edge_test\Controller;

use Apigee\Edge\Exception\ApiException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use GuzzleHttp\Psr7\Request;

class ExceptionController extends ControllerBase {

  public function entityStorage() {
    try {
      $this->api();
    }
    catch (ApiException $ex) {
      throw new EntityStorageException('', 0, $ex);
    }
  }

  public function api() {
    throw new ApiException(new Request('GET', ''));
  }

}
