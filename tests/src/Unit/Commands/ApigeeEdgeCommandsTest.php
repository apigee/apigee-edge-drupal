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

namespace Drupal\Tests\apigee_edge\Unit {

  use Consolidation\AnnotatedCommand\CommandData;
  use Drupal\apigee_edge\CliServiceInterface;
  use Drupal\apigee_edge\Commands\ApigeeEdgeCommands;
  use Drupal\apigee_edge\Util\EdgeConnectionUtilServiceInterface;
  use Drupal\Tests\UnitTestCase;
  use Drush\Style\DrushStyle;
  use Prophecy\Argument;
  use ReflectionClass;
  use Symfony\Component\Console\Input\InputInterface;

  /**
   * Test ApigeeEdgeCommands class.
   *
   * @group apigee_edge
   */
  class ApigeeEdgeCommandsTest extends UnitTestCase {

    protected $apigeeEdgeCommands;

    protected $edgeConnectionUtilService;

    protected $cliService;

    protected $io;

    /**
     * {@inheritdoc}
     */
    protected function setUp() {
      parent::setUp();
      $this->edgeConnectionUtilService = $this->prophesize(EdgeConnectionUtilServiceInterface::class);
      $this->cliService = $this->prophesize(CliServiceInterface::class);
      $this->apigeeEdgeCommands = new ApigeeEdgeCommands($this->cliService->reveal(), $this->edgeConnectionUtilService->reveal());

      // Set io in DrushCommands to a mock.
      $apigee_edge_commands_reflection = new ReflectionClass($this->apigeeEdgeCommands);
      $reflection_io_property = $apigee_edge_commands_reflection->getProperty('io');
      $reflection_io_property->setAccessible(TRUE);
      $this->io = $this->prophesize(DrushStyle::class);
      $reflection_io_property->setValue($this->apigeeEdgeCommands, $this->io->reveal());

      $this->io->askHidden(Argument::type('string'), Argument::any())
        ->willReturn('I<3APIS!');
    }

    /**
     * Calls to Drush command should pass through to CLI service.
     */
    public function testCreateEdgeRole() {

      $drush_options = [
        'password' => 'opensesame',
        'base-url' => ApigeeEdgeCommands::DEFAULT_BASE_URL,
        'role-name' => 'portalRole',
      ];

      $this->apigeeEdgeCommands->createEdgeRole('orgA', 'emailA', $drush_options);

      $this->edgeConnectionUtilService->createEdgeRoleForDrupal(
        Argument::type(DrushStyle::class),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string')
        )
        ->shouldHaveBeenCalledTimes(1);

    }

    /**
     * Test validateCreateEdgeRole function does not prompt for password.
     *
     * When password option is set, do not prompt for password.
     *
     * @throws \ReflectionException
     */
    public function testValidatePasswordParam() {

      $command_data_input = $this->prophesize(InputInterface::class);
      $command_data_input->getOption('password')->willReturn('secret');
      $command_data = $this->prophesize(CommandData::class);
      $command_data->input()->willReturn($command_data_input->reveal());

      $this->apigeeEdgeCommands->validateCreateEdgeRole($command_data->reveal());

      // Make sure password was not prompted to user.
      $command_data_input->getOption('password')->shouldHaveBeenCalled();
      $this->io->askHidden(Argument::type('string'), Argument::any())
        ->shouldNotBeCalled();
      $command_data_input->setOption()->shouldNotHaveBeenCalled();
    }

    /**
     * Test validateCreateEdgeRole prompts for password.
     *
     * When password option not set, password should be inputted by user.
     *
     * @throws \ReflectionException
     */
    public function testValidatePasswordParamEmpty() {

      $command_data_input = $this->prophesize(InputInterface::class);
      $command_data_input->getOption('password')->willReturn(NULL);
      $command_data_input->setOption(Argument::type('string'), Argument::type('string'))->willReturn();
      $command_data = $this->prophesize(CommandData::class);
      $command_data->input()->willReturn($command_data_input->reveal());

      $this->apigeeEdgeCommands->validateCreateEdgeRole($command_data->reveal());

      // Make sure password not requested.
      $command_data_input->getOption('password')->shouldHaveBeenCalled();
      $this->io->askHidden(Argument::type('string'), Argument::any())
        ->shouldBeCalled();
      $command_data_input->setOption('password', 'I<3APIS!')
        ->shouldHaveBeenCalled();

    }

  }
}

namespace {

  /**
   * Mock out dt() so function exists for tests.
   *
   * @param string $message
   *   The string with placeholders to be interpolated.
   * @param array $context
   *   An associative array of values to be inserted into the message.
   *
   * @return string
   *   The resulting string with all placeholders filled in.
   */
  function dt(string $message, array $context = []): string {
    return StringUtils::interpolate($message, $context);
  }

}
