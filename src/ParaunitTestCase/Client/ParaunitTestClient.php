<?php

namespace ParaunitTestCase\Client;

use Doctrine\Common\Persistence\ConnectionRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use Symfony\Bundle\FrameworkBundle\Client;

/**
 * Class ParaunitTestClient
 * @package Facile\Cbr\CoreBundle\Tests
 */
class ParaunitTestClient extends Client
{
    /**
     * This method does the trick: it's needed to make multiple requests without losing the transaction between them
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\ORMException
     */
    protected function doRequest($request)
    {
        /** @var ConnectionRegistry $doctrine */
        $doctrine = $this->getContainer()->get('doctrine');
        foreach ($doctrine->getConnectionNames() as $connectionServiceName) {
            $this->reloadEMWithSameTransaction($connectionServiceName);
        }

        return $this->kernel->handle($request);
    }

    /**
     * @param string $connectionServiceName
     * @throws ORMException
     */
    private function reloadEMWithSameTransaction($connectionServiceName)
    {
        $connectionName = $this->extractConnectionName($connectionServiceName);
        $doctrineName = 'doctrine.orm.' . $connectionName . '_entity_manager';
        /** @var EntityManager $em */
        $em = $this->getContainer()->get($doctrineName);

        $newEm = $em->create($em->getConnection(), $em->getConfiguration());
        $this->getContainer()->set($doctrineName, $newEm);
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
