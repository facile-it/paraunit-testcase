<?php

namespace ParaunitTestCase\TestCase;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use ParaunitTestCase\Client\ParaunitTestClient;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\DependencyInjection\Container;

/**
 * Class ParaUnitWebTestCase
 * @package ParaunitTestCase\TestCase
 */
abstract class ParaunitWebTestCase extends WebTestCase
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @param null $name
     * @param array $data
     * @param string $dataName
     */
    public function __construct($name = NULL, array $data = array(), $dataName = '')
    {
        ini_set('xdebug.max_nesting_level', 250);

        parent::__construct($name, $data, $dataName);

        $this->initialize();
    }

    /**
     * Do not EVER forget to call parent::setUp() if you override this method!
     */
    public function setUp()
    {
        parent::setUp();

        $this->getEM()->getConnection()->setTransactionIsolation(Connection::TRANSACTION_READ_COMMITTED);
        $this->getEM()->beginTransaction();
    }

    /**
     * Do not EVER forget to call parent::tearDown() if you override this method!
     */
    public function tearDown()
    {
        parent::tearDown();

        if ($this->getEm()) {
            $this->getEM()->rollback();
            $conn = $this->getEm()->getConnection();
            $conn->close();
        }
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
     * @return EntityManagerInterface
     * @throws \InvalidArgumentException If the entity manager (with that name) does not exist
     */
    protected function getEm($entityManagerName = null)
    {
        if ( ! $entityManagerName){
            return $this->em;
        }

        $entityManger = $this->getContainer()->get('doctrine')->getManager($entityManagerName);

        return $entityManger;
    }

    private function initialize()
    {
        static::$kernel = static::createKernel();
        static::$kernel->boot();

        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
    }
}
