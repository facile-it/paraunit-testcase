<?php

namespace ParaunitTestCase\Exception;

/**
 * Class UnauthorizedCommitException
 * @package ParaunitTestCase\Exception
 */
class UnauthorizedCommitException extends \Exception
{
    public function __construct() 
    {
        parent::__construct('You tried to commit and write to the test database. Check your tests, you may have one too much commit()');
    }
}
