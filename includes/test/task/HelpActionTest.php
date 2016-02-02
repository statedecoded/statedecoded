<?php

/*
 * Help Action test suite
 */

require_once '../task/class.HelpAction.inc.php';

class HelpActionTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
    	$this->args = array(
    			'asdf',
    			'jkl;'
    		);
        $this->help_action = new HelpAction();
    }

    public function testExecute()
    {
		$help_action_mock = $this->getMock('HelpAction', array('showDefaultHelp', 'showHelp'));

		$help_action_mock->expects($this->once())
                 ->method('showDefaultHelp')
                 ->will($this->returnValue($this->args[0]));

        $this->assertEquals($help_action_mock->execute(array()),
            $this->args[0]);

        $args = array(1);
		$help_action_mock->expects($this->once())
                 ->method('showHelp')
                 ->with($args)
                 ->will($this->returnValue($this->args[1]));

        $this->assertEquals($help_action_mock->execute($args),
            $this->args[1]);
    }

    public function testShowDefaultHelp()
    {
        /*
         * We only care that it returns /something/, but let's look
         * specifically for the definition of /this/ action.
         */

        $this->assertNotEmpty($this->help_action->showDefaultHelp());

        $this->assertContains(HelpAction::$summary,
            $this->help_action->showDefaultHelp());
    }

    public function testShowHelp()
    {
        /*
         * We only care that it returns /something/, but let's look
         * specifically for the definition of /this/ action.
         */
        $this->assertNotEmpty($this->help_action->showHelp(array('help')));

        $this->assertEquals(HelpAction::getHelp(),
            $this->help_action->showHelp(array('help')));

    }

    public function testGetHelp()
    {
        /*
         * We only care that it returns /something/.
         */

     $this->assertNotEmpty(HelpAction::getHelp());
    }
}
