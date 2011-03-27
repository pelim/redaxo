<?php

/**
 * Managerklasse zum handeln von rexAddons
 */
abstract class rex_packageManager
{
  private $i18nPrefix;

  /**
   * Konstruktor
   *
   * @param $i18nPrefix Sprachprefix aller I18N Sprachschlüssel
   */
  function __construct($i18nPrefix)
  {
    $this->i18nPrefix = $i18nPrefix;
  }

  /**
   * Installiert ein Addon
   *
   * @param $addonName Name des Addons
   * @param $installDump Flag, ob die Datei install.sql importiert werden soll
   */
  public function install($addonName, $installDump = TRUE)
  {
  	global $REX;

    $state = TRUE;

    $install_dir  = $this->baseFolder($addonName);
    $install_file = $install_dir.'install.inc.php';
    $install_sql  = $install_dir.'install.sql';
    $config_file  = $install_dir.'config.inc.php';
    $files_dir    = $install_dir.'assets';
    $package_file = $install_dir.'package.yml';

    // Pruefen des Addon Ornders auf Schreibrechte,
    // damit das Addon spaeter wieder geloescht werden kann
    $state = rex_is_writable($install_dir);

    if ($state === TRUE)
    {
      // load package infos
      $this->loadPackageInfos($addonName);

      // check if requirements are met
      $state = $this->checkRequirements($addonName);

      if($state === TRUE)
      {
        // check if install.inc.php exists
        if (is_readable($install_file))
        {
          $this->includeInstaller($addonName, $install_file);
          $state = $this->verifyInstallation($addonName);
        }
        else
        {
          // no install file -> no error
          $this->apiCall('setProperty', array($addonName, 'install', 1));
        }

        if($state === TRUE && $installDump === TRUE && is_readable($install_sql))
        {
          $state = rex_install_dump($install_sql);

          if($state !== TRUE)
            $state = 'Error found in install.sql:<br />'. $state;
        }

        // Installation ok
        if ($state === TRUE)
        {
          $this->saveConfig();
        }
      }
    }

    // Dateien kopieren
    if($state === TRUE && is_dir($files_dir))
    {
      if(!rex_dir::copy($files_dir, $this->assetsFolder($addonName)))
      {
        $state = $this->I18N('install_cant_copy_files');
      }
    }

    if($state !== TRUE)
    {
      $this->apiCall('setProperty', array($addonName, 'install', 0));
      $state = $this->I18N('no_install', $addonName) .'<br />'. $state;
    }

    return $state;
  }

  /**
   * De-installiert ein Addon
   *
   * @param $addonName Name des Addons
   */
  public function uninstall($addonName)
  {
    $state = TRUE;

    $install_dir    = $this->baseFolder($addonName);
    $uninstall_file = $install_dir.'uninstall.inc.php';
    $uninstall_sql  = $install_dir.'uninstall.sql';
    $package_file   = $install_dir.'package.yml';

    $isActivated = $this->apiCall('isActivated', array($addonName));
    if ($isActivated)
    {
      $state = $this->deactivate($addonName);
      if ($state !== true)
      {
        return $state;
      }
    }

    // start un-installation
    if($state === TRUE)
    {
      // check if uninstall.inc.php exists
      if (is_readable($uninstall_file))
      {
        $this->includeUninstaller($addonName, $uninstall_file);
        $state = $this->verifyUninstallation($addonName);
      }
      else
      {
        // no uninstall file -> no error
        $this->apiCall('setProperty', array($addonName, 'install', 0));
      }
    }

    if($state === TRUE && is_readable($uninstall_sql))
    {
      $state = rex_install_dump($uninstall_sql);

      if($state !== TRUE)
        $state = 'Error found in uninstall.sql:<br />'. $state;
    }

    $mediaFolder = $this->assetsFolder($addonName);
    if($state === TRUE && is_dir($mediaFolder))
    {
      if(!rex_dir::delete($mediaFolder))
      {
        $state = $this->I18N('install_cant_delete_files');
      }
    }

    if($state === TRUE)
    {
      rex_config::removeNamespace($this->configNamespace($addonName));
    }

    if($state !== TRUE)
    {
      // Fehler beim uninstall -> Addon bleibt installiert
      $this->apiCall('setProperty', array($addonName, 'install', 1));
      if($isActivated)
      {
        $this->apiCall('setProperty', array($addonName, 'status', 1));
      }
      $this->saveConfig();
      $state = $this->I18N('no_uninstall', $addonName) .'<br />'. $state;
    }
    else
    {
      $this->saveConfig();
    }

    return $state;
  }

  /**
   * Aktiviert ein Addon
   *
   * @param $addonName Name des Addons
   */
  public function activate($addonName)
  {
    global $REX;

    if ($this->apiCall('isInstalled', array($addonName)))
    {
      // load package infos
      $this->loadPackageInfos($addonName);

      $state = $this->checkRequirements($addonName);

      if ($state === true)
      {
        $this->apiCall('setProperty', array($addonName, 'status', 1));
        if(!$REX['SETUP'])
        {
          $configFile = $this->baseFolder($addonName) .'config.inc.php';
          if(is_readable($configFile))
          {
            $this->includeConfig($addonName, $configFile);
          }
        }
        $this->saveConfig();
      }
      if($state === true)
      {
        $this->addToPackageOrder($addonName);
      }
    }
    else
    {
      $state = $this->I18N('not_installed', $addonName);
    }

    if($state !== TRUE)
    {
      // error while config generation, rollback addon status
      $this->apiCall('setProperty', array($addonName, 'status', 0));
      $state = $this->I18N('no_activation', $addonName) .'<br />'. $state;
    }

    return $state;
  }

  /**
   * Deaktiviert ein Addon
   *
   * @param $addonName Name des Addons
   */
  public function deactivate($addonName)
  {
    $state = $this->checkDependencies($addonName);

    if ($state === true)
    {
      $this->apiCall('setProperty', array($addonName, 'status', 0));
      $this->saveConfig();
    }

    if($state === TRUE)
    {
      // reload autoload cache when addon is deactivated,
      // so the index doesn't contain outdated class definitions
      rex_autoload::getInstance()->removeCache();

      $this->removeFromPackageOrder($addonName);
    }
    else
    {
      $state = $this->I18N('no_deactivation', $addonName) .'<br />'. $state;
    }

    return $state;
  }

  /**
   * Löscht ein Addon im Filesystem
   *
   * @param $addonName Name des Addons
   */
  public function delete($addonName)
  {
    // zuerst deinstallieren
    // bei erfolg, komplett löschen
    $state = TRUE;
    $state = $state && $this->uninstall($addonName);
    $state = $state && rex_dir::delete($this->baseFolder($addonName));
    $state = $state && rex_dir::delete($this->dataFolder($addonName));
    $this->saveConfig();

    return $state;
  }

  /**
   * Verifies if the installation of the given Addon was successfull.
   *
   * @param string $addonName The name of the addon
   */
  private function verifyInstallation($addonName)
  {
    $state = TRUE;

    // Wurde das "install" Flag gesetzt?
    // Fehlermeldung ausgegeben? Wenn ja, Abbruch
    if(($instmsg = $this->apiCall('getProperty', array($addonName, 'installmsg', ''))) != '')
    {
      $state = $instmsg;
    }
    elseif(!$this->apiCall('isInstalled', array($addonName)))
    {
      $state = $this->I18N('no_reason');
    }

    return $state;
  }

  /**
   * Verifies if the un-installation of the given Addon was successfull.
   *
   * @param string $addonName The name of the addon
   */
  private function verifyUninstallation($addonName)
  {
    $state = TRUE;

    // Wurde das "install" Flag gesetzt?
    // Fehlermeldung ausgegeben? Wenn ja, Abbruch
    if(($instmsg = $this->apiCall('getProperty', array($addonName, 'installmsg', ''))) != '')
    {
      $state = $instmsg;
    }
    elseif($this->apiCall('isInstalled', array($addonName)))
    {
      $state = $this->I18N('no_reason');
    }

    return $state;
  }

  /**
   * Checks whether the requirements are met.
   *
   * @param string $addonName The name of the addon
   */
  protected function checkRequirements($addonName)
  {
    global $REX;

    $state = array();
    $requirements = $this->apiCall('getProperty', array($addonName, 'requires', array()));

    if(isset($requirements['redaxo']) && is_array($requirements['redaxo']))
    {
      $rexVers = $REX['VERSION'] .'.'. $REX['SUBVERSION'] .'.'. $REX['MINORVERSION'];
      if (($msg = $this->checkRequirementVersion('redaxo_', $requirements['redaxo'], $rexVers)) !== true)
      {
        return $msg;
      }
    }

    if(isset($requirements['php-extensions']) && is_array($requirements['php-extensions']))
    {
      foreach($requirements['php-extensions'] as $reqExt)
      {
        if(is_string($reqExt))
        {
          if(!extension_loaded($reqExt))
          {
            $state[] = rex_i18n::msg('addon_requirement_error_php_extension', $reqExt);;
          }
        }
      }
    }

    if(empty($state) && isset($requirements['addons']) && is_array($requirements['addons']))
    {
      foreach($requirements['addons'] as $depName => $depAttr)
      {
        // check if dependency exists
        if(!rex_ooAddon::isAvailable($depName))
        {
          $state[] = rex_i18n::msg('addon_requirement_error_addon', $depName);
        }
        else
        {
          if(($msg = $this->checkRequirementVersion('addon_', $depAttr, rex_ooAddon::getVersion($depName), $depName)) !== true)
          {
            $state[] = $msg;
          }

          // check plugin requirements
          if(isset($depAttr['plugins']) && is_array($depAttr['plugins']))
          {
            foreach($depAttr['plugins'] as $pluginName => $pluginAttr)
            {
              // check if dependency exists
              if(!rex_ooPlugin::isAvailable($depName, $pluginName))
              {
                $state[] = rex_i18n::msg('addon_requirement_error_plugin', $depName, $pluginName);
              }
              elseif(($msg = $this->checkRequirementVersion('plugin_', $pluginAttr, rex_ooPlugin::getVersion($depName, $pluginName), $depName, $pluginName)) !== true)
              {
                $state[] = $msg;
              }
            }
          }
        }
      }
    }

    return empty($state) ? true : implode('<br />', $state);
  }

  /**
   * Checks the version of the requirement.
   *
   * @param string $i18nPrefix Prefix for I18N
   * @param array $attributes Requirement attributes (version, min-version, max-version)
   * @param string $version Active version of requirement
   * @param string $addonName Name of the required addon, only necessary if requirement is a addon/plugin
   * @param string $pluginName Name of the required plugin, only necessary if requirement is a plugin
   */
  private function checkRequirementVersion($i18nPrefix, array $attributes, $version, $addonName = null, $pluginName = null)
  {
    global $REX;

    $i18nPrefix = 'addon_requirement_error_'. $i18nPrefix;
    $state = true;

    // check dependency exact-version
    if(isset($attributes['version']) && rex_version_compare($version, $attributes['version'], '!='))
    {
      $state = rex_i18n::msg($i18nPrefix . 'exact_version', $attributes['version'], $version, $addonName, $pluginName);
    }
    else
    {
      // check dependency min-version
      if(isset($attributes['min-version']) && rex_version_compare($version, $attributes['min-version'], '<'))
      {
        $state = rex_i18n::msg($i18nPrefix . 'min_version', $attributes['min-version'], $version, $addonName, $pluginName);
      }
      // check dependency max-version
      else if(isset($attributes['max-version']) && rex_version_compare($version, $attributes['max-version'], '>'))
      {
        $state = rex_i18n::msg($i18nPrefix . 'max_version', $attributes['max-version'], $version, $addonName, $pluginName);
      }
    }
    return $state;
  }

  /**
   * Checks if another Addon which is activated, depends on the given addon
   *
   * @param string $addonName The name of the addon
   */
  protected abstract function checkDependencies($addonName);

	/**
   * Adds the package to the package order
   *
   * @param string $packageName The name of the package
   */
  protected function addToPackageOrder($packageName)
  {
    $order = rex_core_config::get('package-order', array());
    $package = $this->package($packageName);
    if(!in_array($package, $order))
    {
      $order[] = $package;
      rex_core_config::set('package-order', $order);
    }
  }

  /**
   * Removes the package from the package order
   *
   * @param string $packageName The name of the package
   */
  protected function removeFromPackageOrder($packageName)
  {
    $order = rex_core_config::get('package-order', array());
    if(($key = array_search($this->package($packageName), $order)) !== false)
    {
      unset($order[$key]);
      rex_core_config::set('package-order', array_values($order));
    }
  }

  /**
   * Übersetzen eines Sprachschlüssels unter Verwendung des Prefixes
   */
  protected function I18N()
  {
    global $REX;

    $args = func_get_args();
    $args[0] = $this->i18nPrefix. $args[0];

    return rex_call_func(array('rex_i18n', 'msg'), $args, false);
  }

  /**
   * Bindet die config-Datei eines Addons ein
   */
  protected abstract function includeConfig($addonName, $configFile);

  /**
   * Bindet die installations-Datei eines Addons ein
   */
  protected abstract function includeInstaller($addonName, $installFile);

  /**
   * Bindet die deinstallations-Datei eines Addons ein
   */
  protected abstract function includeUninstaller($addonName, $uninstallFile);

  /**
   * Ansprechen einer API funktion
   *
   * @param $method Name der Funktion
   * @param $arguments Array von Parametern/Argumenten
   */
  protected abstract function apiCall($method, array $arguments);

  /**
   * Laedt die package.yml in $REX
   */
  protected abstract function loadPackageInfos($addonName);

  /**
   * Findet den Basispfad eines Addons
   */
  protected abstract function baseFolder($addonName);

  /**
   * Findet den Basispfad für Assets-Dateien
   */
  protected abstract function assetsFolder($addonName);

  /**
   * Findet den Pfad für den Data-Ordner
   */
  protected abstract function dataFolder($addonName);

  /**
   * Package representation
   */
  protected abstract function package($addonName);

  /**
   * Findet den Namespace für rex_config
   */
  protected abstract function configNamespace($addonName);

  /**
   * Saves the package config
   */
  static protected function saveConfig()
  {
    global $REX;

    $config = array();
    $config['install'] = $REX['ADDON']['install'];
    $config['status'] = $REX['ADDON']['status'];
    $config['plugins'] = array();
    if(isset($REX['ADDON']['plugins']) && is_array($REX['ADDON']['plugins']))
    {
      foreach($REX['ADDON']['plugins'] as $addon => $pluginConfig)
      {
        $config['plugins'][$addon]['install'] = $pluginConfig['install'];
        $config['plugins'][$addon]['status'] = $pluginConfig['status'];
      }
    }
    rex_core_config::set('package-config', $config);
  }

  /**
   * Synchronizes the packages with the file system
   */
  static public function synchronizeWithFileSystem()
  {
    global $REX;

    $addons = self::readPackageFolder(rex_path::addon('*'));
    $addonManager = new rex_addonManager();
    array_map(array($addonManager, 'delete'), array_diff(rex_ooAddon::getRegisteredAddons(), $addons));
    foreach($addons as $addon)
    {
      $REX['ADDON']['install'][$addon] = rex_ooAddon::getProperty($addon, 'install', false);
      $REX['ADDON']['status'][$addon] = rex_ooAddon::getProperty($addon, 'status', false);
      $plugins = self::readPackageFolder(rex_path::plugin($addon, '*'));
      $pluginManager = new rex_pluginManager($addon);
      array_map(array($pluginManager, 'delete'), array_diff(rex_ooPlugin::getRegisteredPlugins($addon), $plugins));
      foreach($plugins as $plugin)
      {
        $REX['ADDON']['plugins'][$addon]['install'][$plugin] = rex_ooPlugin::getProperty($addon, $plugin, 'install', false);
        $REX['ADDON']['plugins'][$addon]['status'][$plugin] = rex_ooPlugin::getProperty($addon, $plugin, 'status', false);
      }
      if(isset($REX['ADDON']['plugins'][$addon]['install']) && is_array($REX['ADDON']['plugins'][$addon]['install']))
        ksort($REX['ADDON']['plugins'][$addon]['install']);
      if(isset($REX['ADDON']['plugins'][$addon]['status']) && is_array($REX['ADDON']['plugins'][$addon]['status']))
        ksort($REX['ADDON']['plugins'][$addon]['status']);
    }
    ksort($REX['ADDON']['install']);
    ksort($REX['ADDON']['status']);
    self::saveConfig();
  }

  /**
   * Returns the subfolders of the given folder
   *
   * @param string $folder Folder
   */
  static private function readPackageFolder($folder)
  {
    $packages = array ();

    $files = glob(rtrim($folder, DIRECTORY_SEPARATOR), GLOB_NOSORT);
    if(is_array($files))
    {
      foreach($files as $file)
      {
        $packages[] = basename($file);
      }
    }

    return $packages;
  }
}