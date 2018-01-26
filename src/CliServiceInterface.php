<?php

namespace Drupal\apigee_edge;

use Symfony\Component\Console\Style\StyleInterface;

/**
 * Defines an interface for CLI service classes.
 */
interface CliServiceInterface {

  /**
   * Handle the sync interaction.
   *
   * @param \Symfony\Component\Console\Style\StyleInterface $io
   *   The IO interface of the CLI tool calling the method.
   * @param callable $t
   *   The translation function akin to t().
   */
  public function sync(StyleInterface $io, callable $t);

}
