<?php
/*
 * This file is part of dgfip-si1/application.
 *
 */
namespace DgfipSI1\ApplicationTests;

use DgfipSI1\Application\Application;
use DgfipSI1\Application\ApplicationSchema as CONF;
use DgfipSI1\Application\ApplicationSchema;
use DgfipSI1\Application\Exception\NoNameOrVersionException;
use DgfipSI1\Application\Exception\ApplicationTypeException;
use DgfipSI1\ApplicationTests\AppTestConfigSchema;
use DgfipSI1\testLogger\LogTestCase;
use Composer\Autoload\ClassLoader;
use org\bovigo\vfs\vfsStream;
use ReflectionClass;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *  tests of
 *  - DgfipSI1\Application\Application
 *  - DgfipSI1\Application\ApplicationSchema
 */
class ApplicationTest extends LogTestCase
{
    /** @var ClassLoader $loader */
    protected $loader;
    /**
     * @inheritDoc
     *
     */
    public function setup(): void
    {
        $loaders = array_values(ClassLoader::getRegisteredLoaders());
        $this->loader = $loaders[0];
    }
    /**
     *  test constructor
     *
     * @covers \DgfipSI1\Application\Application::__construct
     * @covers \DgfipSI1\Application\Application::config
     * @covers \DgfipSI1\Application\ApplicationSchema::__construct
     * @covers \DgfipSI1\Application\ApplicationSchema::getConfigTreeBuilder
     */
    public function testConstructor(): void
    {
        $class = new ReflectionClass(\DgfipSI1\Application\Application::class);
        $inputProp = $class->getProperty('input');
        $inputProp->setAccessible(true);
        $outputProp = $class->getProperty('output');
        $outputProp->setAccessible(true);
        $containerProp = $class->getProperty('container');
        $containerProp->setAccessible(true);
        $configProp = $class->getProperty('config');
        $configProp->setAccessible(true);

        $app = new Application($this->loader);
        $app->config()->build();
        $input = $inputProp->getValue($app);
        $this->assertInstanceOf('\Symfony\Component\Console\Input\ArgvInput', $input);
        $output = $outputProp->getValue($app);
        $this->assertInstanceOf('\Symfony\Component\Console\Output\ConsoleOutput', $output);
        $container = $containerProp->getValue($app);
        $this->assertInstanceOf('League\Container\Container', $container);
        $config = $configProp->getValue($app);
        $this->assertInstanceOf('DgfipSI1\ConfigHelper\ConfigHelper', $config);

        $schema    = new AppTestConfigSchema();
        $app = new Application($this->loader, confSchema: $schema);
        $app->config()->build();
        $this->assertEquals(AppTestConfigSchema::DUMPED_SCHEMA, $app->config()->dumpSchema());
    }
    /**
     * Tests application name and version handling
     *
     * @covers \DgfipSI1\Application\Application::setApplicationName
     * @covers \DgfipSI1\Application\Application::setApplicationVersion
     * @covers \DgfipSI1\Application\Application::setApplicationNameAndVersion
     * @covers \DgfipSI1\Application\Application::finalize
     *
     * @uses \DgfipSI1\Application\Application::__construct
     * @uses \DgfipSI1\Application\Application::config
     * @uses \DgfipSI1\Application\Application::logger
     * @uses \DgfipSI1\Application\Application::buildLogger
     * @uses \DgfipSI1\Application\Application::getVerbosity
     * @uses \DgfipSI1\Application\ApplicationSchema::__construct
     * @uses \DgfipSI1\Application\ApplicationSchema::getConfigTreeBuilder
     * @uses \DgfipSI1\Application\Application::configureAndRegisterCommands
     * @uses \DgfipSI1\Application\Application::discoverPsr4Commands
     * @uses \DgfipSI1\Application\Application::findRoboCommands
     * @uses \DgfipSI1\Application\ApplicationContainer
     *
     * @return void
     */
    public function testAppNameAndVersion(): void
    {
        /** test without name or version */
        $app = new Application($this->loader);
        $app->findRoboCommands('roboTestCommands');
        $msg = '';
        try {
            $app->finalize();
        } catch (NoNameOrVersionException $e) {
            $msg = $e->getMessage();
        }
        /** test name and version setters */
        $this->assertEquals('Application name missing', $msg);
        $app->setApplicationName('tests');
        $msg = '';
        try {
            $app->finalize();
        } catch (NoNameOrVersionException $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals('Version missing', $msg);
        $app->setApplicationVersion('tests');
        $app->finalize();

        /** test name and version via config */
        $app = new Application($this->loader);
        $app->findRoboCommands('roboTestCommands');
        $app->config()->setDefault(CONF::APPLICATION_NAME, 'test');
        $app->config()->setDefault(CONF::APPLICATION_VERSION, '1.0.0');
        $app->finalize();
    }
    /**
     * @return array<string,mixed>
     */
    public function dataGetVerbosity(): array
    {
        $data['<default>   '] = [[ ]               , OutputInterface::VERBOSITY_NORMAL];
        $data['-q          '] = [[ '-q' ]          , OutputInterface::VERBOSITY_QUIET];
        $data['--quiet     '] = [[ '--quiet']      , OutputInterface::VERBOSITY_QUIET];
        $data['-v          '] = [[ '-v']           , OutputInterface::VERBOSITY_VERBOSE];
        $data['--verbose 1 '] = [[ '--verbose', 1] , OutputInterface::VERBOSITY_VERBOSE];
        $data['--verbose=1 '] = [[ '--verbose=1']  , OutputInterface::VERBOSITY_VERBOSE];
        $data['-vv         '] = [[ '-vv']          , OutputInterface::VERBOSITY_VERY_VERBOSE];
        $data['--verbose 2 '] = [[ '--verbose', 2] , OutputInterface::VERBOSITY_VERY_VERBOSE];
        $data['--verbose=2 '] = [[ '--verbose=2']  , OutputInterface::VERBOSITY_VERY_VERBOSE];
        $data['-vvv        '] = [[ '-vvv']         , OutputInterface::VERBOSITY_DEBUG];
        $data['--verbose 3 '] = [[ '--verbose', 3] , OutputInterface::VERBOSITY_DEBUG];
        $data['--verbose=3 '] = [[ '--verbose=3']  , OutputInterface::VERBOSITY_DEBUG];

        return $data;
    }
    /**
     * Tests getVerbosity method
     *
     * @covers \DgfipSI1\Application\Application::getVerbosity
     *
     * @uses   \DgfipSI1\Application\Application::__construct
     * @uses   \DgfipSI1\Application\ApplicationSchema::__construct
     *
     * @param array<string|int> $opts
     * @param int               $expected
     *
     * @return void
     *
     * @dataProvider dataGetVerbosity
     */
    public function testGetVerbosity($opts, $expected): void
    {
        $class = new ReflectionClass('\DgfipSI1\Application\Application');
        $gv = $class->getMethod('getVerbosity');
        $gv->setAccessible(true);
        $app = new Application($this->loader);

        $opts = array_merge([ './tests' ], $opts);
        $this->assertEquals($expected, $gv->invokeArgs($app, [ new ArgvInput($opts) ]));
    }
    /**
     * @return array<string,mixed>
     */
    public function dataBuildLogger(): array
    {
        $ld = CONF::LOG_DIRECTORY;
        $fn = CONF::LOG_FILENAME;
        $of = CONF::LOG_OUTPUT_FORMAT;
        $df = CONF::LOG_DATE_FORMAT;
        $dof = CONF::DEFAULT_OUTPUT_FORMAT;
        $ddf = CONF::DEFAULT_DATE_FORMAT;

        $customDf = 'Y:m:d at H:i:s';
        $customOf = "%context.name%|%message%\n";

        $data['no logfile   '] = [ [$ld => null]                   , null               , null     , null     , false];
        $data['all defaults '] = [ [$ld => '.']                    , "./test.log"       , $dof     , $ddf     , false];
        $data['log/tests.log'] = [ [$ld => 'log']                  , "log/test.log"     , $dof     , $ddf     , false];
        $data['./app.log    '] = [ [$ld => '.', $fn => 'app.log']  , "./app.log"        , $dof     , $ddf     , false];
        $data['log/app.log  '] = [ [$ld => 'log', $fn => 'app.log'], "log/app.log"      , $dof     , $ddf     , false];
        $data['dateFormat   '] = [ [$ld => '.', $df => $customDf ] , "./test.log"       , $dof     , $customDf, false];
        $data['outputFormat '] = [ [$ld => '.', $of => $customOf ] , "./test.log"       , $customOf, $ddf     , false];
        $data['exception    '] = [ [$ld => '/foo/bar' ]            , null               , null     , null     , true ];
        $data['mkdir        '] = [ [$ld => 'VFS:/log' ]            , 'VFS:/log/test.log', $dof     , $ddf     , false];

        return $data;
    }
    /**
     * Tests buildLogger method
     *
     * @covers \DgfipSI1\Application\Application::buildLogger
     * @covers \DgfipSI1\Application\Application::logger
     * @covers \DgfipSI1\Application\Application::finalize
     *
     * @uses   \DgfipSI1\Application\Application::__construct
     * @uses   \DgfipSI1\Application\ApplicationSchema::__construct
     * @uses   \DgfipSI1\Application\ApplicationSchema::getConfigTreeBuilder
     * @uses   \DgfipSI1\Application\Application::config
     * @uses   \DgfipSI1\Application\Application::getVerbosity
     * @uses   \DgfipSI1\Application\Application::setApplicationNameAndVersion
     * @uses   \DgfipSI1\Application\Application::configureAndRegisterCommands
     * @uses   \DgfipSI1\Application\Application::discoverPsr4Commands
     * @uses   \DgfipSI1\Application\Application::findRoboCommands
     * @uses   \DgfipSI1\Application\ApplicationContainer
     *
     * @param array<string,string|null> $opts
     * @param string|null               $filename
     * @param string|null               $outputFormat
     * @param string|null               $dateFormat
     * @param bool                      $throwException
     *
     * @return void
     *
     * @dataProvider dataBuildLogger
     */
    public function testBuildLogger($opts, $filename, $outputFormat, $dateFormat, $throwException): void
    {
        $root = vfsStream::setup();
        $class = new ReflectionClass('\DgfipSI1\Application\Application');
        $bl = $class->getMethod('buildLogger');
        $bl->setAccessible(true);
        $app = new Application($this->loader);
        $app->findRoboCommands('roboTestCommands');
        $app->config()->set(CONF::APPLICATION_NAME, 'test');
        $app->config()->set(CONF::APPLICATION_VERSION, '1.0.0');
        foreach ($opts as $param => $value) {
            if (is_string($value)) {
                $value = str_replace('VFS:', $root->url(), $value);
            }
            $app->config()->set($param, $value);
        }
        $msg = '';
        try {
            $app->finalize();
            $logger = $bl->invokeArgs($app, [ OutputInterface::VERBOSITY_NORMAL ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $logger = null;
        }
        if ($throwException) {
            $this->assertNotEmpty($msg, $msg);
        } else {
            $this->assertEmpty($msg);
            /** @var \Monolog\Logger $logger */
            $this->assertInstanceOf('\Monolog\Logger', $logger);
            $this->assertEquals($app->logger(), $logger);
            /** @var array<\Monolog\Handler\HandlerInterface> $handlers */
            $handlers = $logger->getHandlers();
            if (null === $filename) {
                $this->assertEquals(1, sizeof($handlers));
                $this->assertInstanceOf('\Monolog\Handler\PsrHandler', $handlers[0]);
            } else {
                $this->assertDirectoryExists(''.$app->config()->get(ApplicationSchema::LOG_DIRECTORY));
                $this->assertEquals(2, sizeof($handlers));
                $this->assertInstanceOf('\Monolog\Handler\PsrHandler', $handlers[0]);
                $this->assertInstanceOf('\Monolog\Handler\StreamHandler', $handlers[1]);

                /** @var \Monolog\Handler\StreamHandler $sh */
                $sh = $handlers[1];
                if (strpos($filename, "VFS:") === false) {
                    $this->assertEquals(realpath('.')."/$filename", $sh->getUrl());
                } else {
                    $this->assertEquals(str_replace('VFS:', $root->url(), $filename), $sh->getUrl());
                }
                /** @var \Monolog\Formatter\LineFormatter $formatter */
                $formatter = $sh->getFormatter();

                $formaterClass = new ReflectionClass('Monolog\Formatter\LineFormatter');
                $of = $formaterClass->getProperty('format');
                $of->setAccessible(true);
                $this->assertEquals($outputFormat, $of->getValue($formatter), "Output format does not match expected");
                $df = $formaterClass->getProperty('dateFormat');
                $df->setAccessible(true);
                $this->assertEquals($dateFormat, $df->getValue($formatter), "Date format does not match expected");
            }
        }
    }
    /**
     *  test class finders
     *
     * @covers \DgfipSI1\Application\Application::configureAndRegisterCommands
     * @covers \DgfipSI1\Application\Application::discoverPsr4Commands
     * @covers \DgfipSI1\Application\Application::addSharedCommand
     *
     * @uses \DgfipSI1\Application\Application::run
     * @uses \DgfipSI1\Application\Application::findRoboCommands
     * @uses \DgfipSI1\Application\Application::findSymfonyCommands
     * @uses \DgfipSI1\Application\Application::finalize
     * @uses \DgfipSI1\Application\Application::__construct
     * @uses \DgfipSI1\Application\Application::config
     * @uses \DgfipSI1\Application\ApplicationSchema::__construct
     * @uses \DgfipSI1\Application\ApplicationSchema::getConfigTreeBuilder
     * @uses \DgfipSI1\Application\Application::buildLogger
     * @uses \DgfipSI1\Application\Application::getVerbosity
     * @uses \DgfipSI1\Application\Application::setApplicationName
     * @uses \DgfipSI1\Application\Application::setApplicationNameAndVersion
     * @uses \DgfipSI1\Application\Application::setApplicationVersion
     * @uses \DgfipSI1\Application\ApplicationContainer
     */
    public function testClassFinders(): void
    {
        $app   = new Application($this->loader);

        $class = new ReflectionClass('\DgfipSI1\Application\Application');
        $rc = $class->getMethod('configureAndRegisterCommands');
        $rc->setAccessible(true);
        $asc = $class->getMethod('addSharedCommand');
        $asc->setAccessible(true);
        $cc = $class->getProperty('commandClasses');
        $cc->setAccessible(true);
        $cm = $class->getProperty('commands');
        $cm->setAccessible(true);
        $at = $class->getProperty('appType');
        $at->setAccessible(true);

        $symfoClass = $class->getConstant('SYMFONY_SUBCLASS');
        $roboClass  = $class->getConstant('ROBO_SUBCLASS');
        $symfoApp   = $class->getConstant('SYMFONY_APPLICATION');
        $roboApp    = $class->getConstant('ROBO_APPLICATION');

        /** check everything is empty */
        $this->assertEquals([], $cc->getValue($app));
        $this->assertEquals([], $cm->getValue($app));
        /** find robo commands */
        $at->setValue($app, $roboApp);
        $rc->invokeArgs($app, [ 'roboTestCommands', $roboClass ]);
        /** check results */
        $this->assertEquals(['DgfipSI1\ApplicationTests\roboTestCommands\AppTestRoboFile'], $cc->getValue($app));
        $this->assertEquals(['helloTest'], $cm->getValue($app));

        /** find symfony commands */
        $cc->setValue($app, []);
        $cm->setValue($app, []);
        $at->setValue($app, $symfoApp);
        $rc->invokeArgs($app, [ 'symfonyTestCommands', $symfoClass ]);
        $this->assertEquals(['DgfipSI1\ApplicationTests\symfonyTestCommands\HelloWorldCommand'], $cc->getValue($app));
        $this->assertEquals(['hello'], $cm->getValue($app));

        /** test symfony errors */
        $cc->setValue($app, []);
        $cm->setValue($app, []);
        $at->setValue($app, $symfoApp);
        $msg = '';
        try {
            $rc->invokeArgs($app, [ 'symfonyBadCommand', $symfoClass ]);
            /** @phpstan-ignore-next-line */
        } catch (LogicException $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Command name is empty /', $msg);

        /** test symfony errors */
        $msg = '';
        try {
            $asc->invokeArgs($app, [ 'test', $this ]);
            /** @phpstan-ignore-next-line */
        } catch (LogicException $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/invalid Symfony Command provided/', $msg);
    }
    /**
     *  test roboRun
     *
     * @covers \DgfipSI1\Application\Application::run
     * @covers \DgfipSI1\Application\Application::findRoboCommands
     * @covers \DgfipSI1\Application\Application::findSymfonyCommands
     * @covers \DgfipSI1\Application\Application::finalize
     *
     * @uses \DgfipSI1\Application\Application::__construct
     * @uses \DgfipSI1\Application\Application::config
     * @uses \DgfipSI1\Application\ApplicationSchema::__construct
     * @uses \DgfipSI1\Application\ApplicationSchema::getConfigTreeBuilder
     * @uses \DgfipSI1\Application\Application::buildLogger
     * @uses \DgfipSI1\Application\Application::getVerbosity
     * @uses \DgfipSI1\Application\Application::setApplicationName
     * @uses \DgfipSI1\Application\Application::setApplicationNameAndVersion
     * @uses \DgfipSI1\Application\Application::setApplicationVersion
     * @uses \DgfipSI1\Application\Application::configureAndRegisterCommands
     * @uses \DgfipSI1\Application\Application::discoverPsr4Commands
     * @uses \DgfipSI1\Application\ApplicationContainer
     */
    public function testRoboRun(): void
    {
        $this->expectOutputString('Hello !');
        $app = new Application($this->loader, [ './test', 'hello:test']);
        /* check that finalize fails if we haven't found classes  */
        $msg = '';
        try {
            $app->finalize();
        } catch (ApplicationTypeException $e) {
            $msg = $e->getMessage();
        }
        /* check that run fails if not initalized  */
        $msg = '';
        try {
            $app->run();
        } catch (ApplicationTypeException $e) {
            $msg = $e->getMessage();
        }
        $this->assertNotEmpty($msg);

        $app->setApplicationName('test');
        $app->setApplicationVersion('1.00');
        $app->findRoboCommands('roboTestCommands');
        $app->finalize();
        /* check that calling symphonyNamespace throws an error */
        $msg = '';
        try {
            $app->findSymfonyCommands('foo');
        } catch (ApplicationTypeException $e) {
            $msg = $e->getMessage();
        }
        $this->assertNotEmpty($msg);
        $app->run();
    }
    /**
     *  test symfonyRun
     *
     * @covers \DgfipSI1\Application\Application::run
     * @covers \DgfipSI1\Application\Application::findRoboCommands
     * @covers \DgfipSI1\Application\Application::findSymfonyCommands
     * @covers \DgfipSI1\Application\Application::finalize
     *
     * @uses \DgfipSI1\Application\Application::__construct
     * @uses \DgfipSI1\Application\Application::config
     * @uses \DgfipSI1\Application\ApplicationSchema::__construct
     * @uses \DgfipSI1\Application\ApplicationSchema::getConfigTreeBuilder
     * @uses \DgfipSI1\Application\Application::buildLogger
     * @uses \DgfipSI1\Application\Application::getVerbosity
     * @uses \DgfipSI1\Application\Application::setApplicationName
     * @uses \DgfipSI1\Application\Application::setApplicationNameAndVersion
     * @uses \DgfipSI1\Application\Application::setApplicationVersion
     * @uses \DgfipSI1\Application\Application::configureAndRegisterCommands
     * @uses \DgfipSI1\Application\Application::addSharedCommand
     * @uses \DgfipSI1\Application\Application::discoverPsr4Commands
     * @uses \DgfipSI1\Application\ApplicationContainer
     */
    public function testSynfonyRun(): void
    {
        $this->expectOutputString('Hello world !!');
        $app = new Application($this->loader, [ './test', 'hello']);

        $app->setApplicationName('test');
        $app->setApplicationVersion('1.00');
        $app->findSymfonyCommands('symfonyTestCommands');
        /* check that calling roboNamespace throws an error */
        $msg = '';
        try {
            $app->findRoboCommands('foo');
        } catch (ApplicationTypeException $e) {
            $msg = $e->getMessage();
        }
        $this->assertNotEmpty($msg);
        $app->finalize();
        $app->run();
    }
}
