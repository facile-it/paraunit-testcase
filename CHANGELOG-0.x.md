# Changes in Paraunit Testcase 0.x

All notable changes of the Paraunit Testcase 0.x release series are documented in this file using the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## TBA
### Changed
### Fixed

## [0.5.2] - 2017-04-11

### Changed
* Added `KernelRebootHandler` interface
* Add possibility to set a `KernelRebootHandler` implementor in the test case to handle better the client's kernel reboots

### Fixed
* Fixed visibility of `ParaunitFunctionalTestCase`'s `setup` and `teardown` methods to `protected`, to reflect the 
originals. 

## [0.5.1] - 2017-04-04

### Changed

* Add a new `prepareIsolatedContainer(ContainerInterface $container)` hook that can be used to alter the container before being used in a Client or when using `runContainerAwareCommandTester()`

## [0.5] - 2016-11-03

### Changed

* Add a new `ContainerAwareCommandTester` that extends Symfony's `CommandTester`: this class is specialized to test
a `ContainerAwareCommand` class
* New `runContainerAwareCommandTester` and `createContainerAwareCommandTester` methods in the testcase (usage explained
in the readme) 
* Deprecated `ParaunitFunctionalTestCase::runCommandTesterAndReturnOutput` in favor of the new 
`ParaunitFunctionalTestCase::runContainerAwareCommandTester` 

## [0.4] - 2016-09-22

### Changed

* Deprecated `ParaunitWebTestCase` and `ParaunitCommandTestCase`, in favor of the new single `ParaunitFunctionalTestCase` class; both classes will be removed in the 1.0 release.

## [0.3] - 2016-09-16

### Changed

* New `ParaunitCommandTestCase`: it uses the same "magic method" as the client, to use a different kernel & container
 while testing a Console `ContainerAwareCommand`

### Fixed

* Fixed #7: now the Symfony Profiler works, so it's possible to check if a mail has been sent by the controller (thanks 
to @marrek13 for the issue!)

## [0.2] - 2016-08-16

### Changed

* A new approach is used for database isolation: since Doctrine's internals are pretty complicated, now the Testcase and
the Client use the Reflection to replace the MySQL connection inside Doctrine's classes
* The test client uses now its own kernel, separated from the test's
* The test client is able to reboot his kernel if needed, without loosing the DB connection (and its transaction)
   * Kernel reboot is generally needed when testing Symfony entity-related forms, because some Doctrine services are not stateless
* A Connection Wrapper is now needed to avoid loosing the DB connection between kernel reboots

## [0.1.4] - 2016-03-14

### Fixed

* The `getEm()` method of the Testcase is simplified

## [0.1.3] - 2016-03-10

### Fixed

* Doctrine's EventManager is no longer passed beside the connection inside the Client

## [0.1.2] - 2015-03-10

### Fixed

* Closed EntityManagers are not passed around; instead, an exception is thrown

## [0.1.1] - 2016-01-08

### Changed

*  Dependencies are upgraded: PHPUnit 5.x and compatibility; LiipTestcase 1.3 requested

## [0.1] - 2015-09-08

### Changed

* First release
