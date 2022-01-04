<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\Tests\apigee_edge\Unit\Command {

  use Drupal\apigee_edge\CliServiceInterface;
  use Drupal\apigee_edge\Command\CreateEdgeRoleCommand;
  use Drupal\Console\Core\Style\DrupalStyle;
  use Drupal\Core\Logger\LoggerChannelFactoryInterface;
  use Drupal\Core\Logger\LogMessageParserInterface;
  use Drupal\Tests\UnitTestCase;
  use Prophecy\Argument;
  use Symfony\Component\Console\Formatter\OutputFormatterInterface;
  use Symfony\Component\Console\Input\InputInterface;
  use Symfony\Component\Console\Output\OutputInterface;

  /**
   * Test ApigeeEdgeCommands class.
   *
   * @group apigee_edge
   */
  class CreateEdgeRoleCommandTest extends UnitTestCase {

    /**
     * The system under test.
     *
     * @var \Drupal\apigee_edge\Command\CreateEdgeRoleCommand
     */
    protected $createEdgeRoleCommand;

    /**
     * The CLI Service mock.
     *
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    protected $cliService;

    /**
     * The IO mock.
     *
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    protected $io;

    /**
     * The LogMessageParser mock.
     *
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    protected $logMessageParser;

    /**
     * The LoggerChannelFactory mock.
     *
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    protected $loggerChannelFactory;

    /**
     * The InputInterface mock.
     *
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $input;

    /**
     * The OutputInterface mock.
     *
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $output;

    /**
     * OutputFormatterInterface mock.
     *
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    private $outputFormatter;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void {
      if (!class_exists('Drupal\Console\Core\Command\Command')) {
        $this->markTestSkipped('Skipping because Drupal Console is not installed.');
      }

      parent::setUp();
      $this->cliService = $this->prophesize(CliServiceInterface::class);
      $this->logMessageParser = $this->prophesize(LogMessageParserInterface::class);
      $this->loggerChannelFactory = $this->prophesize(LoggerChannelFactoryInterface::class);
      $this->createEdgeRoleCommand = new CreateEdgeRoleCommand($this->cliService->reveal(),
        $this->logMessageParser->reveal(), $this->loggerChannelFactory->reveal());

      $this->input = $this->prophesize(InputInterface::class);
      $this->output = $this->prophesize(OutputInterface::class);
      $this->io = $this->prophesize(DrupalStyle::class);

      $this->outputFormatter = $this->prophesize(OutputFormatterInterface::class)->reveal();
      $this->output->getFormatter()->willReturn($this->outputFormatter);
      $this->output->getVerbosity()->willReturn(OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * Calls to Drush command should pass through to CLI service.
     */
    public function testCreateEdgeRole() {
      $this->input->getArgument(Argument::type('string'))->willReturn('XXX');
      $this->input->getOption(Argument::type('string'))->willReturn('XXX');

      $this->createEdgeRoleCommand->execute($this->input->reveal(), $this->output->reveal());

      $this->cliService->createEdgeRoleForDrupal(
        Argument::type(DrupalStyle::class),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('bool')
      )->shouldHaveBeenCalledTimes(1);
    }

    /**
     * Calls to Drush command should pass through to CLI service.
     */
    public function testCreateEdgeRoleForceParam() {
      $this->input->getArgument(Argument::is('org'))->willReturn('myorg');
      $this->input->getArgument(Argument::is('email'))->willReturn('email@example.com');
      $this->input->getOption(Argument::is('password'))->willReturn('secret');
      $this->input->getOption(Argument::is('base-url'))->willReturn('http://base-url');
      $this->input->getOption(Argument::is('role-name'))->willReturn('custom_drupal_role');
      $this->input->getOption(Argument::is('force'))->willReturn('true');

      $this->createEdgeRoleCommand->execute($this->input->reveal(), $this->output->reveal());

      $this->cliService->createEdgeRoleForDrupal(
        Argument::type(DrupalStyle::class),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('bool')
      )->shouldHaveBeenCalledTimes(1);
    }

    /**
     * Test validateCreateEdgeRole function does not prompt for password.
     *
     * When password option is set, do not prompt for password.
     */
    public function testInteractWithPasswordParam() {

      $this->input->getArgument(Argument::type('string'))->willReturn('XXX');
      $this->input->getOption('password')->willReturn('secret');
      $this->input->getOption(Argument::type('string'))->willReturn('XXX');
      $this->input->isInteractive()->willReturn(FALSE);

      $this->createEdgeRoleCommand->interact($this->input->reveal(), $this->output->reveal());

      // Interact should not change password since it was passed in.
      $this->input->getOption('password')->shouldHaveBeenCalled();
      $this->input->setOption('password')->shouldNotHaveBeenCalled();
    }

    /**
     * Test validateCreateEdgeRole prompts for password.
     *
     * When password option not set, password should be inputted by user.
     */
    public function testInteractPasswordParamEmpty() {

      $this->input->getArgument(Argument::type('string'))->willReturn('XXX');
      $this->input->getOption('password')->willReturn(NULL);
      $this->input->getOption(Argument::type('string'))->willReturn('XXX');
      $this->input->setOption(Argument::type('string'), NULL)->willReturn(NULL);
      $this->input->isInteractive()->willReturn(FALSE);

      $this->createEdgeRoleCommand->interact($this->input->reveal(), $this->output->reveal());

      // Interact should not change password since it was passed in.
      $this->input->getOption('password')->shouldHaveBeenCalled();
      $this->input->setOption('password', NULL)->shouldHaveBeenCalled();
    }

  }
}

namespace {

  // phpcs:disable PSR2.Namespaces.UseDeclaration.UseAfterNamespace
  use Drush\Utils\StringUtils;

  if (!function_exists('t')) {

    /**
     * Mock out t() so function exists for tests.
     *
     * @param string $message
     *   The string with placeholders to be interpolated.
     * @param array $context
     *   An associative array of values to be inserted into the message.
     *
     * @return string
     *   The resulting string with all placeholders filled in.
     */
    function t(string $message, array $context = []): string {
      return StringUtils::interpolate($message, $context);
    }

  }

}
