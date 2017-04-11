<?php


namespace ParaunitTestCase\Client;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface KernelRebootHandler
 * @package ParaunitTestCase\Client
 * 
 * This interface is useful to handle container isolation with the test client.
 * Its two methods get called before and after the client's kernel gets rebooted.
 */
interface KernelRebootHandler
{
    public function beforeKernelReboot(ContainerInterface $container);
    
    public function afterKernelReboot(ContainerInterface $container);
}
