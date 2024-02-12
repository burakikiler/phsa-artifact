<?php

namespace EvolvingWeb\DESK\Tools\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvent;
use Composer\Script\ScriptEvents;
use EvolvingWeb\DESK\Tools\Helper;

class Plugin implements PluginInterface, EventSubscriberInterface
{

  /**
   * @var Composer
   */
  protected $composer;

  public static function getSubscribedEvents()
  {
    return [
      ScriptEvents::POST_INSTALL_CMD => "onPostInstallCmd",
    ];
  }

  public function activate(Composer $composer, IOInterface $io)
  {
    $this->composer = $composer;
  }

  public function deactivate(Composer $composer, IOInterface $io)
  {
  }

  public function uninstall(Composer $composer, IOInterface $io)
  {
  }

  public static function onPostInstallCmd(Event $event){
    // Update the BLT project name
    Helper::updateBLTProjectName($event->getIO());
    // Swap the README with the project README template.
    Helper::initializeReadmeFile($event->getIO());
  }
}
