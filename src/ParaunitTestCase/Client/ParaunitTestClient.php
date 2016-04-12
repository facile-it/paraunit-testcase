<?php

namespace ParaunitTestCase\Client;

use Doctrine\Common\Persistence\AbstractManagerRegistry;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Client;

/**
 * Class ParaunitTestClient
 * @package Facile\Cbr\CoreBundle\Tests
 */
class ParaunitTestClient extends Client
{
    /** @var  bool */
    private $reboot = false;

    public function enableKernelRebootBeforeRequest()
    {
        $this->reboot = true;
    }

    /**
     * The EM is cleared every time to avoid inconsistencies in the test environment.
     *
     * This method avoids rebooting the kernel until explicitly requested, to reduce test execution time.
     * If something broke the EntityManager, or if some stateful, Doctrine-related service is breaking your test,
     * you should use the enableKernelRebootBeforeRequest() method to request a kernel reboot.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function doRequest($request)
    {
        if ($this->reboot) {
            $managers = $this->getDoctrineManagers();

            $this->kernel->shutdown();
            $this->kernel->boot();

            $this->reinjectDoctrineManagers($managers);
        }

        $this->clearAllManagers();

        $result = $this->kernel->handle($request);

        $this->checkAllManagersForDeadlocks();

        return $result;
    }

    private function clearAllManagers()
    {
        /** @var EntityManager $manager */
        foreach ($this->getDoctrine()->getManagers() as $manager) {
            $manager->clear();

            if ( ! $manager->isOpen()) {
                throw new \RuntimeException(
                    'The EntityManager was closed before the request. Check if a previous request broke it. You can also try to explicitly reboot the kernel before the request'
                );
            }
        }
    }

    private function checkAllManagersForDeadlocks()
    {
        /** @var EntityManager $manager */
        foreach ($this->getDoctrine()->getManagers() as $manager) {
            $errorMessage = $manager->getConnection()->errorInfo();
            if (count($errorMessage) > 1 && $errorMessage[1]) {
                throw new \RuntimeException(
                    'The EntityManager encountered a probable deadlock: ' . $errorMessage[1]
                );
            }
        }
    }

    /**
     * @return array | EntityManager[]
     */
    private function getDoctrineManagers()
    {
        $managers = [];

        foreach ($this->getDoctrine()->getConnectionNames() as $connectionServiceName) {
            $managerServiceName = 'doctrine.orm.' . $this->extractConnectionName($connectionServiceName) . '_entity_manager';
            $managers[$managerServiceName] = $this->getContainer()->get($managerServiceName);
        }

        return $managers;
    }

    /**
     * @param array | EntityManager[] $entityManagers
     */
    private function reinjectDoctrineManagers(array $entityManagers)
    {
        $container = $this->getContainer();

        foreach ($entityManagers as $name => $entityManager) {
            $container->set($name, $entityManager);
        }
    }

    /**
     * @return AbstractManagerRegistry
     */
    private function getDoctrine()
    {
        return $this->getContainer()->get('doctrine');
    }

    /**
     * @param string $connectionServiceName
     * @return string
     */
    private function extractConnectionName($connectionServiceName)
    {
        $matches = array();
        if ( ! preg_match('/^doctrine\.dbal\.(.+)_connection$/', $connectionServiceName, $matches)) {
            throw new \InvalidArgumentException('Non-standard Doctrine connection name: ' . $connectionServiceName);
        }

        return $matches[1];
    }
}
