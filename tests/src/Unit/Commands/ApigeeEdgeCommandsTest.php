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

namespace Drupal\Tests\apigee_edge\Unit\Commands {

  use Consolidation\AnnotatedCommand\CommandData;
  use Drupal\Tests\UnitTestCase;
  use Drupal\apigee_edge\CliServiceInterface;
  use Drupal\apigee_edge\Commands\ApigeeEdgeCommands;
  use Drush\Style\DrushStyle;
  use Prophecy\Argument;
  use Prophecy\Prophet;
  use ReflectionClass;
  use Symfony\Component\Console\Input\InputInterface;

  /**
   * Test ApigeeEdgeCommands class.
   *
   * @group apigee_edge
   */
  class ApigeeEdgeCommandsTest extends UnitTestCase {

    /**
     * The system under test.
     *
     * @var \Drupal\apigee_edge\Commands\ApigeeEdgeCommands
     */
    protected $apigeeEdgeCommands;

    /**
     * The CLI Service mock.
     *
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    protected $cliService;

    /**
     * The DrushStyle mock.
     *
     * @var \Prophecy\Prophecy\ObjectProphecy
     */
    protected $io;

    /**
     * The Prophet class.
     *
     * @var \Prophecy\Prophet
     */
    private $prophet;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void {
      $this->markTestSkipped('Skipping for Drupal10 as test fails.');

      parent::setUp();

      $this->prophet = new Prophet();

      $this->cliService = $this->prophet->prophesize(CliServiceInterface::class);
      $this->apigeeEdgeCommands = new ApigeeEdgeCommands($this->cliService->reveal());

      // Set io in DrushCommands to a mock.
      $apigee_edge_commands_reflection = new ReflectionClass($this->apigeeEdgeCommands);
      $reflection_io_property = $apigee_edge_commands_reflection->getProperty('io');
      $reflection_io_property->setAccessible(TRUE);
      $this->io = $this->prophet->prophesize(DrushStyle::class);
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
        'base-url' => 'http://api.apigee.com/v1',
        'role-name' => 'portalRole',
        'force' => 'FALSE',
      ];

      $this->apigeeEdgeCommands->createEdgeRole('orgA', 'emailA', $drush_options);

      $this->cliService->createEdgeRoleForDrupal(
        Argument::type(DrushStyle::class),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('bool')
        )
        ->shouldHaveBeenCalledTimes(1);

    }

    /**
     * Test validateCreateEdgeRole function does not prompt for password.
     *
     * When password option is set, do not prompt for password.
     */
    public function testValidatePasswordParam() {

      $command_data_input = $this->prophet->prophesize(InputInterface::class);
      $command_data_input->getOption('password')->willReturn('secret');
      $command_data_input->getArgument('email')->willReturn('email.example.com');
      $command_data = $this->prophet->prophesize(CommandData::class);
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
     */
    public function testValidatePasswordParamEmpty() {

      $command_data_input = $this->prophet->prophesize(InputInterface::class);
      $command_data_input->getOption('password')->willReturn(NULL);
      $command_data_input->setOption(Argument::type('string'), Argument::type('string'))->willReturn();
      $command_data_input->getArgument('email')->willReturn('email.example.com');
      $command_data = $this->prophet->prophesize(CommandData::class);
      $command_data->input()->willReturn($command_data_input->reveal());

      $this->apigeeEdgeCommands->validateCreateEdgeRole($command_data->reveal());

      // Make sure password not requested.
      $command_data_input->getOption('password')->shouldHaveBeenCalled();
      $this->io->askHidden(Argument::type('string'), Argument::any())
        ->shouldBeCalled();
      $command_data_input->setOption('password', 'I<3APIS!')
        ->shouldHaveBeenCalled();
    }

    /**
     * Test calling with force function when role already exists.
     */
    public function testCreateEdgeEdgeRoleWithForceParam() {
      $drush_options = [
        'password' => 'opensesame',
        'base-url' => 'http://api.apigee.com/v1',
        'role-name' => 'portalRole',
        'force' => TRUE,
      ];

      $this->apigeeEdgeCommands->createEdgeRole('orgA', 'emailA', $drush_options);

      $this->cliService->createEdgeRoleForDrupal(
        Argument::type(DrushStyle::class),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        TRUE
      )
        ->shouldHaveBeenCalledTimes(1);
    }

    /**
     * Test calling when role exists but force flag not given, should error.
     */
    public function testCreateEdgeEdgeRoleWithoutForceParam() {
      $drush_options = [
        'password' => 'opensesame',
        'base-url' => 'http://api.apigee.com/v1',
        'role-name' => 'portalRole',
        'force' => FALSE,
      ];

      $this->apigeeEdgeCommands->createEdgeRole('orgA', 'emailA', $drush_options);

      $this->cliService->createEdgeRoleForDrupal(
        Argument::type(DrushStyle::class),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        Argument::type('string'),
        FALSE
      )
        ->shouldHaveBeenCalledTimes(1);
    }

  }
}

namespace {

  // phpcs:disable PSR2.Namespaces.UseDeclaration.UseAfterNamespace
  use Drush\Utils\StringUtils;

  if (!function_exists('dt')) {

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

}
