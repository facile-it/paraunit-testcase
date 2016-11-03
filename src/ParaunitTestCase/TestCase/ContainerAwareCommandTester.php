<?php

namespace ParaunitTestCase\TestCase;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
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
     * @param ContainerAwareCommand $command A ContainerAwareCommand instance to test
     * @param ContainerInterface $container The container to be injected in the command to test
     */
    public function __construct(ContainerAwareCommand $command, ContainerInterface $container)
    {
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
