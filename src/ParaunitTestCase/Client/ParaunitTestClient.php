<?php

namespace ParaunitTestCase\Client;

use Doctrine\Common\Persistence\AbstractManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Client;

/**
 * Class ParaunitTestClient
 * @package Facile\Cbr\CoreBundle\Tests
 */
class ParaunitTestClient extends Client
{
    /**
     * This function reboots the Client's kernel preserving the underlying
     */
    public function rebootKernel()
    {
        $connections = $this->getDoctrineConnections();

        $this->kernel->shutdown();
        $this->kernel->boot();

        $this->reinjectDoctrineConnections($connections);
    }

    /**
     * This method checks that the EM is still valid and avoids rebooting the kernel.
     * The EM is cleared every time to avoid inconsistencies.
     * If something broke the EntityManager, or if some Doctrine-related stateful service is breaking your test,
     * you should explicitly reboot the kernel BEFORE the request, using the rebootKernel() method
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function doRequest($request)
    {
        /** @var EntityManagerInterface $manager */
        foreach ($this->getDoctrine()->getManagers() as $manager) {
            $this->clearManager($manager);
        }

        return $this->kernel->handle($request);
    }

    /**
     * @param EntityManagerInterface $manager
     */
    private function clearManager(EntityManagerInterface $manager)
    {
        $manager->clear();

        if ( ! $manager->isOpen()) {
            throw new \RuntimeException(
                'The EntityManager was closed before the request. Check if a previous request broke it. You can also try to explicitly reboot the kernel before the request'
            );
        }
    }

    /**
     * @return array | Connection[]
     */
    private function getDoctrineConnections()
    {
        $connections = [];

        foreach ($this->getDoctrine()->getConnectionNames() as $connectionName) {
            $connections[$connectionName] = $this->getContainer()->get($connectionName);
        }
        
        return $connections;
    }

    /**
     * @param array | Connection[] $connections
     */
    private function reinjectDoctrineConnections(array $connections)
    {
        foreach ($connections as $name => $connection) {
            $this->replaceManagerWithPreviousConnection($connection, $name);
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
     * @param Connection $connection
     * @param string $connectionName
     * @throws \Doctrine\ORM\ORMException
     */
    private function replaceManagerWithPreviousConnection($connection, $connectionName)
    {
        $name = $this->extractConnectionName($connectionName);
        $entityManagerName = 'doctrine.orm.' . $name . '_entity_manager';
        /** @var EntityManager $oldEntityManager */
        $oldEntityManager = $this->getContainer()->get($entityManagerName);

        $this->getContainer()->set(
            $entityManagerName,
            EntityManager::create($connection, $oldEntityManager->getConfiguration())
        );
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
