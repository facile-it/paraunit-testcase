<?php

namespace ParaunitTestCase\TestCase;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
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

        /** @var EntityManagerInterface $manager */
        foreach ($this->getContainer()->get('doctrine')->getManagers() as $manager) {
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
            'PHP_AUTH_PW'   => $password,
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
     * @throws ORMException If the entity manager is closed
     */
    protected function getEm($entityManagerName = null)
    {
        /** @var EntityManagerInterface $entityManger */
        $entityManger = $this->getContainer()->get('doctrine')->getManager($entityManagerName);

        if ($entityManger->isOpen()) {
            throw ORMException::entityManagerClosed();
        }

        return $entityManger;
    }
}
