<?php

namespace Acquia\BltPa11y\Blt\Plugin\Commands;

use Acquia\Blt\Robo\Commands\Tests\TestsCommandBase;
use Acquia\Blt\Robo\Exceptions\BltException;
use Acquia\BltPa11y\Blt\Wizards\TestsWizard;
use Robo\Contract\VerbosityThresholdInterface;
use League\Container\Definition\DefinitionInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
   * @var TestsWizard
   */
  protected $testsWizard;

  /**
   * This hook will fire for all commands in this command file.
   *
   * @hook init
   */
  public function initialize()
  {
    parent::initialize();
    $this->logDir = $this->getConfigValue('tests.reports.localDir') . '/pa11y';

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
      $confirm = $this->confirm('Pa11y configuration is not fully initialized. Run recipes:pa11y:init now?', TRUE);
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
      $this->say('Generating Pa11y configuration files...');
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
   * @launchWebServer
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   * @throws \Exception
   */
  public function pa11y()
  {
    if ($this->getConfigValue('pa11y.validate', TRUE)) {
      $this->testsWizard = $this->getContainer()->get(TestsWizard::class);
      $this->testsWizard->wizardConfigurePa11y();
    }

    // Log config for debugging purposes.
    $this->logConfig($this->getConfigValue('pa11y', ['validate' => TRUE]), 'pa11y');
    $this->logConfig($this->testsWizard->getLocalPa11yConfig()->export());
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
    $pa11y_config = $this->testsWizard->getLocalPa11yConfig();

    $config = [
      'defaults' => [
        'timeout' => $pa11y_config->get('config.timeout', 5000),
        'reporter' => $pa11y_config->get('config.reporter', 'cli'),
        'viewport' => [
          'width' => $pa11y_config->get('config.viewport.width', 320),
          'height' => $pa11y_config->get('config.viewport.height', 480),
        ],
        'standard' => $pa11y_config->get('config.standard', 'WCAG2AA'),
        'hideElements' => $pa11y_config->get('config.hideElements', ['svg']),
        'ignore' => $pa11y_config->get('config.ignore', ['notice']),
        'chromeLaunchConfig' => [
          'ignoreHTTPSErrors' => TRUE,
          'args' => [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--headless',
          ],
        ],
      ]
    ];

    $base_url = $pa11y_config->get('base_url');
    foreach ($pa11y_config->get('paths', ['/']) as $path) {
      $config['urls'][] = $base_url . $path;
    }

    $config_file = sys_get_temp_dir() . '/pa11y-ci.json';
    file_put_contents($config_file, json_encode($config['defaults'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Output errors.
    foreach ($config['urls'] as $url) {
      $task = $this->taskExec($this->getConfigValue('repo.root') . '/node_modules/.bin/pa11y')
        ->arg($url)
        ->option('config', $config_file)
        ->interactive($this->input()->isInteractive());

      $threshold = $pa11y_config->get('config.threshold');
      if ($threshold) {
        // We want value to be passed all the time, even for 0 so using string.
        $task->option('threshold', "$threshold");
      }

      $result = $task->run();

      // For success, we expect the exit code to be 0.
      if ($result->getExitCode()) {
        unlink($config_file);
        $this->logConfig($config, 'pa11y-ci', OutputInterface::VERBOSITY_QUIET);

        if ($result->getExitCode() == 1) {
          throw new BltException('Pa11y tests execution failed, please check setup!');
        }

        if ($result->getExitCode() == 2) {
          throw new BltException('Pa11y tests failed!');
        }
      }
    }

    unlink($config_file);
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
