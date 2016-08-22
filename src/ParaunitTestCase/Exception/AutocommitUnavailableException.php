<?php

namespace ParaunitTestCase\Exception;

/**
 * Class AutocommitUnavailableException
 * @package ParaunitTestCase\Exception
 */
class AutocommitUnavailableException extends \Exception
{
    public function __construct()
    {
        parent::__construct('You cannot enable autocommit in this test environment');
    }
}
