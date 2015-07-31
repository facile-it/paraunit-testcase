<?php

namespace Facile\Cbr\CoreBundle\Tests;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Client;

/**
 * Class ParaunitTestClient
 * @package Facile\Cbr\CoreBundle\Tests
 */
class ParaunitTestClient extends Client
{
    protected function doRequest($request)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $newEm = $em->create($em->getConnection(), $em->getConfiguration(), $em->getEventManager());
        $this->getContainer()->set('doctrine.orm.entity_manager', $newEm);

        return $this->kernel->handle($request);
    }
}
