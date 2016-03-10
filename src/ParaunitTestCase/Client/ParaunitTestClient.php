<?php

namespace ParaunitTestCase\Client;

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
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        if ( ! $em->isOpen()) {
            throw new ORMException(sprintf(
                'Entity manager is closed. I was trying to process a request [%s] %s',
                $request->getMethod(),
                $request->getRequestUri()
            ));
        }

        $newEm = $em->create($em->getConnection(), $em->getConfiguration(), $em->getEventManager());
        $this->getContainer()->set('doctrine.orm.entity_manager', $newEm);

        return $this->kernel->handle($request);
    }
}
