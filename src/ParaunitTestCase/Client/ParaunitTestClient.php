<?php

namespace ParaunitTestCase\Client;

use Doctrine\Common\Persistence\AbstractManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * Class ParaunitTestClient
 * @package Facile\Cbr\CoreBundle\Tests
 */
class ParaunitTestClient extends Client
{
    /** @var bool */
    private $reboot = false;
    
    /** @var bool */
    protected $profilerEnabled = false;

    public function enableKernelRebootBeforeRequest()
    {
        $this->reboot = true;
    }

    public function enableProfiler()
    {
        if ($this->getContainer()->has('profiler')) {
            $this->profilerEnabled = true;
        }

        parent::enableProfiler();
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

            $this->reinjectDbConnections($managers);
        }

        $this->checkAllManagersForDeadlocks();
        $this->clearAllManagers();

        if ($this->profilerEnabled) {
            $this->getContainer()->get('profiler')->enable();
        }

        $response = $this->kernel->handle($request);

        $this->checkAllManagersForDeadlocks();

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($request, $response);
        }

        return $response;
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
    private function reinjectDbConnections(array $entityManagers)
    {
        $container = $this->getContainer();
        $reflectionProperty = new \ReflectionProperty(Connection::class, '_conn');

        foreach ($entityManagers as $name => $entityManager) {
            /** @var EntityManager $newEntityManager */
            $newEntityManager = $container->get($name);
            $newConnection = $newEntityManager->getConnection();
            $unusedWrappedConnection = $newConnection->getWrappedConnection();

            $newConnection->setTransactionIsolation(Connection::TRANSACTION_READ_COMMITTED);
            $newEntityManager->beginTransaction();

            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($newConnection, $entityManager->getConnection()->getWrappedConnection());
            $reflectionProperty->setAccessible(false);

            if (method_exists($unusedWrappedConnection, 'close')) {
                $unusedWrappedConnection->close();
            }
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
