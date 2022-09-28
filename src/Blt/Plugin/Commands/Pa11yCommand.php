<?php

namespace Acquia\BltPa11y\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Exceptions\BltException;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Defines commands in the "recipes:pa11y:*" namespace.
 */
class Pa11yCommand extends BltTasks {

  /**
   * Generates example files for writing custom Pa11y tests.
   *
   * @command recipes:pa11y:init
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function init() {
    $source = $this->getConfigValue('blt.root') . '/../blt-pa11y/scripts';
    $dest = $this->getConfigValue('repo.root') . '/tests/pa11y';
    $result = $this->taskCopyDir([$source => $dest])
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Could not copy example files into the repository root.");
    }

    $this->say("<info>Example Pa11y tests were copied into your application.</info>");
  }

}
