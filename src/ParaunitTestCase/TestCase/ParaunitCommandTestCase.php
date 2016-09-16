<?php

namespace ParaunitTestCase\TestCase;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class ParaunitCommandTestCase
 * @package ParaunitTestCase\TestCase
 */
abstract class ParaunitCommandTestCase extends ParaunitWebTestCase
{
    /**
     * Runs a command and returns it output
     * 
     * @param ContainerAwareCommand $command
     * @param array $input
     * @return string
     */
    public function runCommandTesterAndReturnOutput(ContainerAwareCommand $command, array $input = [])
    {
        $kernel = self::createKernel();
        $kernel->boot();

        $application = new Application($kernel);
        $application->add($command);

        $container = $kernel->getContainer();
        $this->injectManagersInContainer($container);
        $command->setContainer($container);

        $input['command'] = $command->getName();
        $input['--env'] = isset($input['--env']) ? $input['--env'] : 'test';

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester->getDisplay();
    }
}
