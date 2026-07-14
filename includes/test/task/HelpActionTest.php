<?php

require_once __DIR__ . '/../../task/class.HelpAction.inc.php';

class HelpActionTest extends PHPUnit\Framework\TestCase
{
    private array $args;
    private HelpAction $help_action;

    protected function setUp(): void
    {
        $this->args = ['asdf', 'jkl;'];
        $this->help_action = new HelpAction();
    }

    public function testExecute(): void
    {
        $mock = $this->getMockBuilder(HelpAction::class)
            ->onlyMethods(['showDefaultHelp', 'showHelp'])
            ->getMock();

        $mock->expects($this->once())
            ->method('showDefaultHelp')
            ->will($this->returnValue($this->args[0]));

        $this->assertEquals($this->args[0], $mock->execute([]));

        $args = [1];
        $mock->expects($this->once())
            ->method('showHelp')
            ->with($args)
            ->will($this->returnValue($this->args[1]));

        $this->assertEquals($this->args[1], $mock->execute($args));
    }

    public function testShowDefaultHelp(): void
    {
        $this->assertNotEmpty($this->help_action->showDefaultHelp());
        $this->assertStringContainsString(
            HelpAction::$summary,
            $this->help_action->showDefaultHelp());
    }

    public function testShowHelp(): void
    {
        $this->assertNotEmpty($this->help_action->showHelp(['help']));
        $this->assertEquals(
            HelpAction::getHelp(),
            $this->help_action->showHelp(['help']));
    }

    public function testGetHelp(): void
    {
        $this->assertNotEmpty(HelpAction::getHelp());
    }
}
