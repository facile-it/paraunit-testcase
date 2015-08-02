<?php

namespace ParaunitTestCase\TestCase;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use ParaunitTestCase\Client\ParaunitTestClient;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class ParaUnitWebTestCase extends WebTestCase
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

    public function setUp()
    {
        parent::setUp();

        $this->getEM()->getConnection()->setTransactionIsolation(Connection::TRANSACTION_READ_COMMITTED);
        $this->getEM()->beginTransaction();
    }

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
     * @return Client
     */
    protected function getAuthorizedClient($username, $password)
    {
        $client = new ParaunitTestClient($this->getContainer()->get('kernel'), array(
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW'   => $password,
        ));

        $this->logInUser($client, $username);

        return $client;
    }

    protected function getUnauthorizedClient()
    {
        return new ParaunitTestClient($this->getContainer()->get('kernel'), array());
    }

    /**
     * @param Client $client
     * @param $username
     * @return null
     * @throws \Exception
     */
    private function logInUser(Client $client, $username)
    {
        $container = $client->getContainer();
        $session = $container->get('session');

        $user = $container->get('facile.cbr_core_bundle.security.user_provider')->loadUserByUsername($username);

        if(!$user) {
            throw new \Exception('User not found');
        }

        $firewall = 'be';
        $token = new UsernamePasswordToken($username, null, $firewall, $user->getRoles());
        $token->setUser($user);
        $container->get('security.context')->setToken($token);

        $session->set('_security_'.$firewall, serialize($token));
        $this->setOtherThingsInSession($session, $user);
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);
    }

    /**
     * @param Session $session
     * @param UserInterface $user
     */
    protected function setOtherThingsInSession(Session $session, UserInterface $user)
    {
        // hook for additional things to be set in session
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEm()
    {
        return $this->em;
    }

    private function initialize()
    {
        static::$kernel = static::createKernel();
        static::$kernel->boot();

        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
    }
}
