<?php

namespace EvolvingWeb\DESK\Tools;

use Composer\IO\IOInterface;
use Symfony\Component\Yaml\Yaml;

class Helper
{
  /**
   * Update BLT machine_name
   *
   * BLT initialize the machine_name with the name of the current directoy. Under DDEV, it's always
   * going to be 'html' as a project always resides in /var/www/html.
   *
   * This function takes care of renaming the 'machine_name' property using the DDEV project name
   * from the DDEV_PROJECT env variable.
   */
  public static function updateBLTProjectName(IOInterface $io)
  {
    $ddevProjectName = getenv('DDEV_PROJECT');
    $bltConfigPath = getcwd() . '/blt/blt.yml';
    if (!empty($ddevProjectName) && is_file($bltConfigPath) && is_readable($bltConfigPath) && is_writable($bltConfigPath)) {
      $bltConfig = Yaml::parseFile($bltConfigPath);
      if ($bltConfig['project']['machine_name'] == 'html') {
        $io->write("Fixing the DDEV project name...");
        $bltConfig['project']['machine_name'] = $ddevProjectName;
        file_put_contents($bltConfigPath, Yaml::dump($bltConfig, PHP_INT_MAX, 2));
      }
    }
  }

  /**
   * Initialize the project README
   *
   * Replace the composer template README file which contains the instruction about the project creation
   * with the README.project.md (from the project template) which contains the project usage instructions.
   */
  public static function initializeReadmeFile(IOInterface $io) {
    $readmeFile = getcwd() . DIRECTORY_SEPARATOR . 'README.md';
    $templateReadmeFile = getcwd() . DIRECTORY_SEPARATOR . 'README.project.md';
    if(is_file($readmeFile) && is_file($templateReadmeFile)){
      $io->write("Replacing project README.md with README.project.md...");
      unlink($readmeFile);
      rename($templateReadmeFile, $readmeFile);
    }

  }
}
