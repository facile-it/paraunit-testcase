<?php

namespace ParaunitTestCase\TestCase;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use ParaunitTestCase\Client\KernelRebootHandler;
use ParaunitTestCase\Client\ParaunitTestClient;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ParaUnitWebTestCase
 * @package ParaunitTestCase\TestCase
 */
abstract class ParaunitFunctionalTestCase extends WebTestCase
{
    /** @var KernelRebootHandler */
    private static $clientKernelRebootHandler;

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
     * @param KernelRebootHandler | null $clientKernelRebootHandler
     */
    public static function setClientKernelRebootHandler(KernelRebootHandler $clientKernelRebootHandler = null)
    {
        self::$clientKernelRebootHandler = $clientKernelRebootHandler;
    }

    /**
     * Do not EVER forget to call parent::setUp() if you override this method!
     */
    protected function setUp()
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
    protected function tearDown()
    {
        /** @var EntityManagerInterface $manager */
        foreach ($this->getContainer()->get('doctrine')->getManagers() as $manager) {
            $manager->rollback();
            $manager->close();

            $connection = $manager->getConnection();
            if ($connection instanceof \ParaunitTestCase\Connection\Connection) {
                $connection->closeForReal();
            } elseif (method_exists($connection, 'close')) {
                $connection->close();
            }
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
     * Runs a command and returns it output
     *
     * @deprecated Prefer to use the new runContainerAwareCommandTester() method;
     *             Warning, the new method doesn't add the command name to the input automatically
     *
     * @param ContainerAwareCommand $command
     * @param array $input
     * @return string
     */
    public function runCommandTesterAndReturnOutput(ContainerAwareCommand $command, array $input = [])
    {
        return $this->runContainerAwareCommandTester($command, $input);
    }

    /**
     * @param ContainerAwareInterface $command
     * @param array $input An array of arguments to pass to the command, in the form of 'argName' => argValue
     * @param array $options An array of options to pass to the command, in the form of 'optName' => optValue
     * @return string
     */
    protected function runContainerAwareCommandTester(
        ContainerAwareInterface $command,
        array $input = array(),
        array $options = array()
    ) {
        $tester = $this->createContainerAwareCommandTester($command);
        $tester->execute($input, $options);

        return $tester->getDisplay();
    }

    /**
     * @param ContainerAwareInterface|Command $command
     * @return ContainerAwareCommandTester
     */
    private function createContainerAwareCommandTester(ContainerAwareInterface $command)
    {
        $kernel = self::createKernel();
        $kernel->boot();

        $container = $kernel->getContainer();
        $this->injectManagersInContainer($container);
        $this->assertInstanceOf(Command::class, $command);

        return new ContainerAwareCommandTester($command, $container);
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
     * Overrides the original method to use our client class inside the makeClient() function
     *
     * @param array $options An array of options to pass to the createKernel class
     * @param array $server An array of server parameters
     *
     * @return ParaunitTestClient A Client instance
     */
    protected static function createClient(array $options = array(), array $server = array())
    {
        static::bootKernel($options);

        $client = new ParaunitTestClient(static::$kernel);
        if (self::$clientKernelRebootHandler instanceof KernelRebootHandler) {
            $client->setKernelRebootHandler(self::$clientKernelRebootHandler);
        }
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
        $this->injectManagersInContainer($client->getContainer());
    }

    /**
     * @param ContainerInterface $container
     */
    private function injectManagersInContainer(ContainerInterface $container)
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = $this->getContainer()->get('doctrine');
        $reflectionProperty = new \ReflectionProperty(Connection::class, '_conn');

        /** @var EntityManagerInterface $manager */
        foreach ($doctrine->getManagerNames() as $entityManagerName) {
            /** @var EntityManager $clientEntityManager */
            $clientEntityManager = $container->get($entityManagerName);
            /** @var Connection $connection */
            $connection = $this->getContainer()->get($entityManagerName)->getConnection();
            $this->assertEquals(1, $connection->getTransactionNestingLevel(), 'Wrong level of transaction level');

            $managerConnection = $clientEntityManager->getConnection();
            $managerConnection->setTransactionIsolation(Connection::TRANSACTION_READ_COMMITTED);
            $clientEntityManager->beginTransaction();

            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($managerConnection, $connection->getWrappedConnection());
            $reflectionProperty->setAccessible(false);
        }

        $this->prepareIsolatedContainer($container);
    }

    /**
     * Override this hook to alter the container before using it in a Client or with runContainerAwareCommandTester()
     * 
     * @param ContainerInterface $container
     */
    protected function prepareIsolatedContainer(ContainerInterface $container)
    {
    }
}
