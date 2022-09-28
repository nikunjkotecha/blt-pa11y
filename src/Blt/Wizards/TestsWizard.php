<?php

namespace Acquia\BltPa11y\Blt\Wizards;

use Acquia\Blt\Robo\Config\BltConfig;
use Acquia\Blt\Robo\Config\YamlConfigProcessor;
use Consolidation\Config\Loader\YamlConfigLoader;
use function file_exists;
use Acquia\Blt\Robo\Wizards\Wizard;

/**
 * Class TestsWizard.
 *
 * @package Acquia\Blt\Robo\Wizards
 */
class TestsWizard extends Wizard {

  /**
   * Prompts user to generate valid Pa11y configuration file.
   */
  public function wizardConfigurePa11y() {
    $pa11y_local_config_file = $this->getConfigValue('repo.root') . '/tests/pa11y/local.yml';
    if (!file_exists($pa11y_local_config_file) || !$this->isPa11yConfigured()) {
      $this->logger->warning('Pa11y is not configured properly.');
      $this->say("BLT can (re)generate tests/pa11y/local.yml using tests/pa11y/example.local.yml.");
      $confirm = $this->confirm("Do you want (re)generate local Pa11y config in <comment>tests/pa11y/local.yml</comment>?", TRUE);
      if ($confirm) {
        $this->getConfigValue('composer.bin');
        $pa11y_local_config_file = $this->getConfigValue('repo.root') . "/tests/pa11y/local.yml";
        if (file_exists($pa11y_local_config_file)) {
          $this->fs->remove($pa11y_local_config_file);
        }
        $this->invokeCommand('tests:pa11y:init');
      }
    }
  }

  /**
   * Determines if Pa11y is properly configured on the local machine.
   *
   * This will ensure that required Pa11y file exists, and that require
   * configuration values are properly defined.
   *
   * @return bool
   *   TRUE is Pa11y is properly configured on the local machine.
   */
  public function isPa11yConfigured() {
    // Verify that URIs required for Drupal and Pa11y are configured correctly.
    $local_pa11y_config = $this->getLocalPa11yConfig();
    if ($this->getConfigValue('project.local.uri') != $local_pa11y_config->get('base_url')) {
      $this->logger->warning('project.local.uri in blt.yml does not match base_url in local.yml.');
      $this->logger->warning('project.local.uri = ' . $this->getConfigValue('project.local.uri'));
      $this->logger->warning('base_url = ' . $local_pa11y_config->get('base_url'));
      return FALSE;
    }

    // Verify that URIs required for an ad-hoc PHP internal server are
    // configured correctly.
    if ($this->getConfigValue('tests.run-server')) {
      if ($this->getConfigValue('tests.server.url') != $this->getConfigValue('project.local.uri')) {
        $this->logger->warning("tests.run-server is enabled, but the server URL does not match Drupal's base URL.");
        $this->logger->warning('project.local.uri = ' . $this->getConfigValue('project.local.uri'));
        $this->logger->warning('tests.server.url = ' . $this->getConfigValue('tests.server.url'));
        $this->logger->warning('base_url = ' . $local_pa11y_config->get('base_url'));

        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Gets the local pa11y configuration defined in local.yml.
   *
   * @return \Acquia\Blt\Robo\Config\BltConfig
   *   The local Pa11y configuration.
   */
  public function getLocalPa11yConfig() {
    $pa11y_local_config_file = $this->getConfigValue('repo.root') . '/tests/pa11y/local.yml';

    $pa11y_local_config = new BltConfig();
    $loader = new YamlConfigLoader();
    $processor = new YamlConfigProcessor();
    $processor->extend($loader->load($pa11y_local_config_file));
    $processor->extend($loader->load($this->getConfigValue('repo.root') . '/tests/pa11y/pa11y.yml'));
    $pa11y_local_config->replace($processor->export());

    return $pa11y_local_config;
  }

}
