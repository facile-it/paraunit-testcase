<?php

namespace ParaunitTestCase\Connection;

use Doctrine\DBAL\Driver;

/**
 * Class Connection
 * @package ParaunitTestCase\Connection
 */
class Connection extends \Doctrine\DBAL\Connection
{
    /**
     * Overwrites the original method to avoid loosing the transaction inside the connection; this allows to reboot
     * the kernel without loosing the data committed inside the transaction
     */
    public function close()
    {
        // Nope!
    }

    /**
     * This method can be called to explicitly close the connection
     */
    public function closeForReal()
    {
        parent::close();
    }
}
