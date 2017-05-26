<?php

namespace ParaunitTestCase\TestCase;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContainerAwareCommandTester
 * @package ParaunitTestCase\TestCase
 */
class ContainerAwareCommandTester extends CommandTester
{
    /** @var ContainerInterface */
    private $container;

    /**
     * @param Command|ContainerAwareInterface $command A ContainerAware Command instance to test
     * @param ContainerInterface $container The container to be injected in the command to test
     */
    public function __construct(Command $command, ContainerInterface $container)
    {
        if (! $command instanceof ContainerAwareInterface) {
            throw new \InvalidArgumentException('Was expecting a ContainerAware command, got ' . get_class($command));
        }

        $application = new Application('Paraunit Command Test: ' . $command->getName());
        $application->add($command);

        $this->container = $container;
        $command->setContainer($container);

        parent::__construct($command);
    }

    /**+
     * @return ContainerInterface
     */
    public function getCommandContainer()
    {
        return $this->container;
    }
}
