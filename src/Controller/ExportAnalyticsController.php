<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Defines a controller for exporting and downloading the requested analytics data.
 */
class ExportAnalyticsController extends ControllerBase {

  /**
   * The PrivateTempStore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $store;

  /**
   * {@inheritdoc}
   */
  public function __construct(PrivateTempStoreFactory $tempstore_private) {
    $this->store = $tempstore_private->get('apigee_edge.analytics');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private')
    );
  }

  /**
   * Exports as CSV and downloads the requested analytics data.
   *
   * @param int $data_id
   *   The ID of the stored analytics data.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function exportAsCSV($data_id) {
    $analytics = $this->store->get($data_id);

    $file_name = $this->t('@app_@metric_analytics.csv', [
      '@app' => $analytics['stats']['data'][0]['identifier']['values'][0],
      '@metric' => $analytics['stats']['data'][0]['metric'][0]['name'],
    ]);

    // Do not create a file, attempt to use memory instead.
    $fh = fopen('php://temp', 'rw');

    // Write out the headers.
    fputcsv($fh, [
      $this->t('Date'),
      $analytics['metric'],
    ]);

    // Write out the data.
    for ($i = 0; $i < count($analytics['TimeUnit']); $i++) {
      fputcsv($fh, [
        new DrupalDateTime('@' . $analytics['TimeUnit'][$i] / 1000),
        $analytics['stats']['data'][0]['metric'][0]['values'][$i],
      ]);
    }

    // Rewind the position of the file pointer and get the data.
    rewind($fh);
    $file_content = stream_get_contents($fh);
    fclose($fh);

    $response = new Response($file_content);
    $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file_name);
    $response->headers->set('Content-Disposition', $disposition);

    return $response;
  }

}
