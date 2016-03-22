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
        foreach ($doctrine->getConnectionNames() as $connectionServiceName) {
            $manager = $this->getCleanManager($connectionServiceName);
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
     * @return Client | ParaunitTestClient
     */
    protected function getAuthorizedClient($username, $password)
    {
        $client = new ParaunitTestClient($this->getContainer()->get('kernel'), array(
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => $password,
        ));

        $this->prepareAuthorizedClient($client, $username, $password);

        return $client;
    }

    /**
     * @return Client | ParaunitTestClient
     */
    protected function getUnauthorizedClient()
    {
        return new ParaunitTestClient($this->getContainer()->get('kernel'), array());
    }

    /**
     * Hook for client advanced authentication
     *
     * Use this method (and ovveride it) if you need to do something else beside the simple
     * HTTP authentication when you call the self::getAuthorizedClient() method
     *
     * @param Client $client
     * @param string $username
     * @param string $password
     */
    protected function prepareAuthorizedClient(Client $client, $username, $password)
    {
        // override me!
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

        try {
            $repository = $em->getRepository(get_class($entity));
        } catch (MappingException $exception) {
            throw new \Exception('Error while trying to refresh object which is not a registered entity: ' . get_class($entity), null, $exception);
        }

        $entity = $repository->find($entity->getId());
    }

    /**
     * This function replaces the EM if closed, since the Liip TestCase caches the kernel, and it could contain
     * an EntityManager that was broken in the previous test inside the same test class
     *
     * @param string $connectionServiceName
     * @return EntityManager
     */
    private function getCleanManager($connectionServiceName)
    {
        $entityManagerName = 'doctrine.orm.' . $this->extractConnectionName($connectionServiceName) . '_entity_manager';
        /** @var EntityManager $manager */
        $manager = $this->getContainer()->get($entityManagerName);

        if ( ! $manager->isOpen()) {
            $newManager = EntityManager::create($manager->getConnection()->getParams(), $manager->getConfiguration());
            $this->getContainer()->set($entityManagerName, $newManager);
            $manager->getConnection()->close();
            $manager = $newManager;
        }

        return $manager;
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
