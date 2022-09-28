<?php

namespace Acquia\BltPa11y\Blt\Plugin\Commands;

use Acquia\Blt\Robo\Commands\Tests\TestsCommandBase;
use Acquia\Blt\Robo\Exceptions\BltException;
use Acquia\BltPa11y\Blt\Wizards\TestsWizard;
use Robo\Contract\VerbosityThresholdInterface;
use Symfony\Component\Console\Output\OutputInterface;
use League\Container\Definition\DefinitionInterface;

/**
 * Defines commands in the "tests" namespace.
 */
class Pa11yTestCommand extends TestsCommandBase
{

    /**
     * The directory containing Pa11y logs.
     *
     * @var string
     */
    protected $logDir;

    /**
     * This hook will fire for all commands in this command file.
     *
     * @hook init
     */
    public function initialize()
    {
        parent::initialize();
        $this->logDir = $this->getConfigValue('tests.reports.localDir') . "/pa11y";

        if ($this::usingLegacyContainer()) {
            $this->container->add(TestsWizard::class)->withArgument('executor');
        } else {
            $this->container->add(TestsWizard::class)->addArgument('executor');
        }
    }

    /**
     * Generates tests/pa11y/local.yml file for executing Pa11y tests locally.
     *
     * @command tests:pa11y:init
     */
    public function setupPa11y()
    {
        if (!$this->isPa11yConfigured()) {
            $confirm = $this->confirm("Pa11y configuration is not fully initialized. Run recipes:pa11y:init now? ", TRUE);
            if ($confirm) {
                $this->invokeCommands(['recipes:pa11y:init']);
            } else {
                return FALSE;
            }
        }

        $defaultPa11yLocalConfigFile = $this->getConfigValue('repo.root') . '/tests/pa11y/example.local.yml';
        $projectPa11yLocalConfigFile = $this->getConfigValue('repo.root') . '/tests/pa11y/local.yml';
        $copy_map = [
            $defaultPa11yLocalConfigFile => $projectPa11yLocalConfigFile,
        ];

        $task = $this->taskFilesystemStack()
            ->stopOnFail()
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE);

        // Copy files without overwriting.
        foreach ($copy_map as $from => $to) {
            if (file_exists($to)) {
                unset($copy_map[$from]);
            }
        }

        if ($copy_map) {
            $this->say("Generating Pa11y configuration files...");
            foreach ($copy_map as $from => $to) {
                $task->copy($from, $to);
            }
            $result = $task->run();
            foreach ($copy_map as $to) {
                $this->getConfig()->expandFileProperties($to);
            }

            if (!$result->wasSuccessful()) {
                $filepath = $this->getInspector()->getFs()->makePathRelative($defaultPa11yLocalConfigFile, $this->getConfigValue('repo.root'));
                throw new BltException("Unable to copy $filepath into your repository.");
            }
        }

        $this->setupPa11yExecutable();
    }

    /**
     * Entrypoint for running pa11y tests.
     *
     * @command tests:pa11y:run
     * @description Executes all pa11y tests. This optionally launch Selenium
     *   prior to execution.
     * @usage
     *   Executes the tests for all configured urls.
     * @usage -D pa11y.paths=/about
     *   Executes the tests for specific URL.
     *
     * @aliases tests:pa11y
     *
     * @interactGenerateSettingsFiles
     * @interactInstallDrupal
     * @validateDrupalIsInstalled
     * @validateVmConfig
     * @launchWebServer
     * @executeInVm
     *
     * @throws \Acquia\Blt\Robo\Exceptions\BltException
     * @throws \Exception
     */
    public function pa11y()
    {
        if ($this->getConfigValue('pa11y.validate')) {
            /** @var \Acquia\BltPa11y\Blt\Wizards\TestsWizard $tests_wizard */
            $tests_wizard = $this->getContainer()->get(TestsWizard::class);
            $tests_wizard->wizardConfigurePa11y();
        }

        // Log config for debugging purposes.
        $this->logConfig($this->getConfigValue('pa11y'), 'pa11y');
        $this->logConfig($this->getInspector()->getLocalPa11yConfig()->export());
        $this->createReportsDir();

        $this->executePa11yTests();
    }

    /**
     * Executes all pa11y tests in pa11y.paths configuration array.
     *
     * @throws \Exception
     *   Throws an exception if any Pa11y test fails.
     */
    protected function executePa11yTests()
    {
        $pa11y_paths = $this->getConfigValue('pa11y.paths');
        if (is_string($pa11y_paths)) {
            $pa11y_paths = [$pa11y_paths];
        }

        $base_url = $this->getConfigValue('base_url');

        foreach ($pa11y_paths as $pa11y_path) {
            $url =  $base_url . $pa11y_path;

            // Output errors.
            $task = $this->taskPa11y($this->getConfigValue('repo.root') . '/node_modules/.bin/pa11y')
                ->arg($url)
                ->option('colors')
                ->noInteraction()
                ->stopOnFail()
                ->interactive($this->input()->isInteractive());

            if ($this->output()->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
                $task->verbose();
            }

            // @todo pass all the configs.
            $result = $task->run();

            if (!$result->wasSuccessful()) {
                throw new BltException("Pa11y tests failed!");
            }
        }
    }

    /**
     * Determines if Pa11y configuration exists in the project.
     *
     * @return bool
     *   TRUE if Pa11y configuration exists.
     */
    public function isPa11yConfigured()
    {
        return file_exists($this->getConfigValue('repo.root') . '/tests/pa11y/pa11y.yml')
            && file_exists($this->getConfigValue('repo.root') . '/tests/pa11y/example.local.yml');
    }

    /**
     * Determine if the legacy version of league/container is in use.
     *
     * @return bool
     *   TRUE if using the legacy container, FALSE otherwise.
     */
    protected static function usingLegacyContainer()
    {
        return method_exists(DefinitionInterface::class, 'withArgument');
    }

    /**
     * Setup Pa11y.
     *
     * @hook post-command source:build:frontend-reqs
     */
    public function setupPa11yExecutable()
    {
        if (!$this->getConfigValue('pa11y')) {
            return;
        }

        $repo_root = $this->getConfigValue('repo.root');

        $result = $this->taskExecStack()
            ->dir($repo_root)
            ->exec('npm install pa11y --save-dev')
            ->printMetadata(FALSE)
            ->run();

        if ($result->getExitCode()) {
            throw new \Exception('Unable to setup Pa11y, please confirm node.js, npm, and npx are available.');
        }
    }

}
