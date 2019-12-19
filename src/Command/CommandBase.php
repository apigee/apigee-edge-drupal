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

namespace Drupal\apigee_edge\Command;

use Drupal\apigee_edge\CliServiceInterface;
use Drupal\Console\Core\Command\Shared\CommandTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Console\Core\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Class CommandBase for shared functionality.
 */
abstract class CommandBase extends Command {

  use CommandTrait;

  /**
   * The interoperability cli service.
   *
   * @var \Drupal\apigee_edge\CliServiceInterface
   */
  protected $cliService;

  /**
   * The IO interface composed of a commands input and output.
   *
   * @var \Symfony\Component\Console\Style\StyleInterface
   */
  protected $io;

  /**
   * The parser object.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $logMessageParser;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerChannelFactory;

  /**
   * Constructor with cli service injection.
   *
   * @param \Drupal\apigee_edge\CliServiceInterface $cli_service
   *   The cli service to delegate all actions to.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $log_message_parser
   *   The parser to use when extracting message variables.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   The logger channel factory.
   */
  public function __construct(CliServiceInterface $cli_service, LogMessageParserInterface $log_message_parser, LoggerChannelFactoryInterface $logger_channel_factory) {
    parent::__construct();
    $this->cliService = $cli_service;
    $this->logMessageParser = $log_message_parser;
    $this->loggerChannelFactory = $logger_channel_factory;
  }

  /**
   * Sets up the IO interface.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The input interface.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The output interface.
   */
  protected function setupIo(InputInterface $input, OutputInterface $output) {
    $this->io = new DrupalStyle($input, $output);
    $this->setupLogger($output);
  }

  /**
   * Sets up the logger service.
   *
   * The service redirects Drupal logging messages to the console output.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The output interface.
   */
  protected function setupLogger(OutputInterface $output) {
    $drupal_console_logger = new ConsoleLogger($output);
    $logger = new DrupalConsoleLog($this->logMessageParser, $drupal_console_logger);
    $this->loggerChannelFactory->addLogger($logger);
  }

  /**
   * Gets the IO interface.
   *
   * @return \Symfony\Component\Console\Style\StyleInterface
   *   The IO interface.
   */
  public function getIo(): StyleInterface {
    return $this->io;
  }

}
