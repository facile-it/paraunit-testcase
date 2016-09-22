<?php

namespace ParaunitTestCase\TestCase;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class ParaunitCommandTestCase
 * @package ParaunitTestCase\TestCase
 * 
 * @deprecated Use ParaunitFunctionalTestCase; this class will be removed in the 1.0 release
 */
abstract class ParaunitCommandTestCase extends ParaunitFunctionalTestCase
{
}
