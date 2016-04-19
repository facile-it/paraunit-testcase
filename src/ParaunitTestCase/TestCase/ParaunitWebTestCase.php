<?php

namespace ParaunitTestCase\TestCase;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use ParaunitTestCase\Client\ParaunitTestClient;
use Symfony\Bundle\FrameworkBundle\Client;

/**
 * Class ParaUnitWebTestCase
 * @package ParaunitTestCase\TestCase
 */
abstract class ParaunitWebTestCase extends WebTestCase
{
    /**
     * @param null $name
     * @param array $data
     * @param string $dataName
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        ini_set('xdebug.max_nesting_level', 250);

        parent::__construct($name, $data, $dataName);
    }

    /**
     * Do not EVER forget to call parent::setUp() if you override this method!
     */
    public function setUp()
    {
        parent::setUp();

        /** @var ManagerRegistry $doctrine */
        $doctrine = $this->getContainer()->get('doctrine');

        /** @var EntityManagerInterface $manager */
        foreach ($doctrine->getManagerNames() as $managerName) {
            $manager = $this->getCleanManager($managerName);
            $manager->clear();
            $manager->getConnection()->setTransactionIsolation(Connection::TRANSACTION_READ_COMMITTED);
            $manager->beginTransaction();
        }
    }

    /**
     * Do not EVER forget to call parent::tearDown() if you override this method!
     */
    public function tearDown()
    {
        /** @var EntityManagerInterface $manager */
        foreach ($this->getContainer()->get('doctrine')->getManagers() as $manager) {
            $manager->rollback();
            $manager->close();
            $manager->getConnection()->close();
        }

        parent::tearDown();
    }

    /**
     * @param string $username
     * @param string $password
     * @return ParaunitTestClient
     */
    protected function getAuthorizedClient($username, $password)
    {
        /** @var ParaunitTestClient $client */
        $client = $this->makeClient([
            'username' => $username,
            'password' => $password,
        ]);

        $this->injectManagersInClient($client);

        return $client;
    }

    /**
     * @return ParaunitTestClient
     */
    protected function getUnauthorizedClient()
    {
        /** @var ParaunitTestClient $client */
        $client = $this->makeClient();

        $this->injectManagersInClient($client);

        return $client;
    }

    /**
     * Overrides the original method to use out client class inside the makeClient() function
     *
     * @param array $options An array of options to pass to the createKernel class
     * @param array $server  An array of server parameters
     *
     * @return ParaunitTestClient A Client instance
     */
    protected static function createClient(array $options = array(), array $server = array())
    {
        static::bootKernel($options);

        $client = new ParaunitTestClient(static::$kernel);
        $client->setServerParameters($server);

        return $client;
    }

    /**
     * Will return the entity manager.
     * It's possible to pass the name of the entity manager, to fetch a non-default one
     *
     * @param string $entityManagerName The name of the desired entity manager or null for the default one
     * @return ObjectManager | EntityManagerInterface
     * @throws \InvalidArgumentException If the entity manager (with that name) does not exist
     */
    protected function getEm($entityManagerName = null)
    {
        return $this->getContainer()->get('doctrine')->getManager($entityManagerName);
    }

    /**
     * This method is usable to refresh a previous fetched entity. It need to be called between EMs changes, like after
     * a failer request through the client that closed the previous EM.
     *
     * @param object $entity
     * @param string $entityManagerName The name of the EM where the entity is mapped, if not the default one
     * @throws \Exception
     */
    protected function refreshEntity(&$entity, $entityManagerName = null)
    {
        if ( ! method_exists($entity, 'getId')) {
            $this->fail('Entity does not have getId(), cannot refresh it using a simple find()! Class: ' . get_class($entity));
        }

        $em = $this->getEm($entityManagerName);

        $entity = $em->find(get_class($entity), $entity->getId()); // forced manage
        $this->getEm()->refresh($entity); // forced refresh
    }

    /**
     * This function replaces the EM if closed, since the Liip TestCase caches the kernel, and it could contain
     * an EntityManager that was broken in the previous test inside the same test class
     *
     * @param string $managerName
     * @return EntityManager
     */
    private function getCleanManager($managerName)
    {
        /** @var EntityManager $manager */
        $manager = $this->getContainer()->get($managerName);

        if ( ! $manager->isOpen()) {
            $newManager = EntityManager::create($manager->getConnection(), $manager->getConfiguration());
            $this->getContainer()->set($managerName, $newManager);
            $manager->getConnection()->close();
            $manager = $newManager;
        }

        return $manager;
    }

    /**
     * @param ParaunitTestClient $client
     */
    private function injectManagersInClient(ParaunitTestClient $client)
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = $this->getContainer()->get('doctrine');
        $reflectionProperty = new \ReflectionProperty(Connection::class, '_conn');
        $clientContainer = $client->getContainer();

        /** @var EntityManagerInterface $manager */
        foreach ($doctrine->getManagerNames() as $entityManagerName) {
            /** @var EntityManager $entityManager */
            $entityManager = $clientContainer->get($entityManagerName);
            /** @var Connection $newConnection */
            $newConnection = $this->getContainer()->get($entityManagerName)->getConnection();

            $newConnection->setTransactionIsolation(Connection::TRANSACTION_READ_COMMITTED);
            $entityManager->beginTransaction();

            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($newConnection, $entityManager->getConnection()->getWrappedConnection());
            $reflectionProperty->setAccessible(false);
        }
    }
}
