# paraunit-testcase

[![Stable release][Last stable image]][Packagist link]
[![Unstable release][Last unstable image]][Packagist link]

[![Scrutinizer][Master scrutinizer image]][Master scrutinizer link]

TestCase and HTTP client to test Symfony2 applications with Doctrine database isolation:

 * no more manual database cleanup after each test, it's already done!
 * no more garbage left over in your test database
 * mess all you want with your fixtures
 * (a bit) faster functional tests

## Requirements
This package is meant to be used for functional testing inside Symfony2+Doctrine applications. It works only with transactional databases, so Entity Manager only, sorry!

If you need to test controllers that requires authentication, it's best to set the security to HTTP-basic in your test environment, to speed up the test and avoid re-testing the login functionality of your app; if this isn't viable for you, see **Advanced usage**.

It's suggested in combination with [facile-it/paraunit](https://github.com/facile-it/paraunit), for even more faster testing!

## Installation
To use this package, use composer:

 * from CLI: `composer require facile-it/paraunit-testcase`
 * or, directly in your `composer.json`:

``` 
{
    "require": {
        "facile-it/paraunit-testcase": "~0.1"
    }
}
```

## Usage
This package provides a test case class, `ParaunitWebTestCase`: to achieve **per-test-method transactional isolation**, extend you functional test classes from it.

With this, anything that your test writes on the DB:

 * is normally readable everywhere inside your test method
 * is "forgotten" at the end of the test method: the first-level transaction is always rolled back
 * is faster to write (it doesn't really reach the DB)
 * your app will behave normally: it can open and close more transactions, and it will fail as normal when flushing incorrect/incomplete data

### Testing a controller
The TestCase provides some utility methods:

 * `getUnauthorizedClient()`: extended Symfony HTTP client, for controller testing (it can read inside the transaction, even between multiple requests)
 * `getAuthorizedClient($user, $password)`: same as before, but with HTTP basic authentication
 * `getEM()`: Doctrine's Entity Manager (transactional)
 * `refreshEntity(&$entity, $entityManagerName = null)`: shortcut for refreshing an entity, re-fetching all the data 
 from the database; really useful if you need to run some assertion on an entity and you want to be sure to read the data
 as persisted/rollbacked on the database.

### Testing a Console Command
We also provide an easy way to test in parallel easily console `ContainerAwareCommand`. To do it, you need to extend
your test class from our `ParaunitCommandTestCase`, and use the the `runCommandTesterAndReturnOutput()` method, like this:
```
class YourCommandTest extends ParaunitCommandTestCase
{
    public function testYourCommand()
    {
        $output = $this->runCommandTesterAndReturnOutput(
            new YourCommand(), 
            [
                'argument' => 'argumentValue',
                '--option' => 0,
            ]
        );
        
        $this->assertContains('Execution completed', $output);
    }
}
```

##Advanced usage
It's possible to extend `ParaunitWebTestCase` more before using it as your base test case:

 * extend and use the `prepareAuthorizedClient(...)` hook method to add additional authentication and preparation to the client, if needed
 * do NOT EVER FORGET to call the parent methods first if you override the `setUp()` and `tearDown()` methods

[Last stable image]: https://poser.pugx.org/facile-it/paraunit-testcase/version.svg
[Last unstable image]: https://poser.pugx.org/facile-it/paraunit-testcase/v/unstable.svg
[Master scrutinizer image]: https://scrutinizer-ci.com/g/facile-it/paraunit/badges/quality-score.png?b=master

[Packagist link]: https://packagist.org/packages/facile-it/paraunit-testcase
[Master scrutinizer link]: https://scrutinizer-ci.com/g/facile-it/paraunit/?branch=master
