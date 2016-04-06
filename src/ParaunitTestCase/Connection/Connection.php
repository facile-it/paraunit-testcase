<?php

namespace ParaunitTestCase\Connection;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use ParaunitTestCase\Exception\AutocommitUnavailableException;
use ParaunitTestCase\Exception\UnauthorizedCommitException;

/**
 * Class Connection
 * @package ParaunitTestCase\Connection
 */
class Connection extends \Doctrine\DBAL\Connection
{
    public function __construct(array $params, Driver $driver, Configuration $config = null, EventManager $eventManager = null)
    {
        parent::__construct($params, $driver, $config = null, $eventManager);
        
        $this->setAutoCommit(false);
    }

    /**
     * Avoids usage of autocommit
     * 
     * @param bool $autoCommit
     * @throws AutocommitUnavailableException
     */
    public function setAutoCommit($autoCommit)
    {
        if ($autoCommit) {
            throw new AutocommitUnavailableException();
        }
        
        parent::setAutoCommit(false);
    }

    /**
     * Overwrites the original method to avoid loosing the transaction inside the connection; this allows to reboot
     * the kernel without loosing the data committed inside the transaction
     */
    public function close()
    {
        // Nope!
    }

    /**
     * Avoids a real write on the database, to achieve full test isolation
     *
     * @throws UnauthorizedCommitException
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function commit()
    {
        if ($this->getTransactionNestingLevel() == 1) {
            throw new UnauthorizedCommitException();
        }

        parent::commit();
    }
}
