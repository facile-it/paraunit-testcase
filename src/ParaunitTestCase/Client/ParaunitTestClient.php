<?php

namespace ParaunitTestCase\Client;

use Doctrine\Common\Persistence\AbstractManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Client;

/**
 * Class ParaunitTestClient
 * @package Facile\Cbr\CoreBundle\Tests
 */
class ParaunitTestClient extends Client
{
    /**
     * This method checks that the EM is still valid and avoids rebooting the kernel.
     * The EM is cleared every time to avoid inconsistencies.
     * If something broke the EntityManager, the connection is closed, to avoid further usage.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\ORMException
     */
    protected function doRequest($request)
    {
        /** @var AbstractManagerRegistry $doctrine */
        $doctrine = $this->getContainer()->get('doctrine');
        /** @var EntityManagerInterface $manager */
        foreach ($doctrine->getManagers() as $manager) {
            $this->handleManager($manager);
        }

        return $this->kernel->handle($request);
    }

    /**
     * @param EntityManagerInterface $manager
     */
    private function handleManager(EntityManagerInterface $manager)
    {
        $manager->clear();

        if ( ! $manager->isOpen()) {
            $manager->getConnection()->close();
        }
    }
}
