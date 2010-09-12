<?php

class rex_addonManager extends rex_baseManager
{
  var $configArray;
  
  function rex_addonManager($configArray)
  {
    $this->configArray = $configArray;
    parent::rex_baseManager('addon_');
  }
  
  public function delete($addonName)
  {
    global $REX, $I18N;
    
    // System AddOns d�rfen nicht gel�scht werden!
    if(in_array($addonName, $REX['SYSTEM_ADDONS']))
      return $I18N->msg('addon_systemaddon_delete_not_allowed');
      
    return parent::delete($addonName);
  }
  
  protected function includeConfig($addonName, $configFile)
  {
    global $REX, $I18N; // N�tig damit im Addon verf�gbar
    require $configFile;
  }
  
  
  protected function includeInstaller($addonName, $installFile)
  {
    global $REX, $I18N; // N�tig damit im Addon verf�gbar
    require $installFile;
  }
  
  protected function includeUninstaller($addonName, $uninstallFile)
  {
    global $REX, $I18N; // N�tig damit im Addon verf�gbar
    require $uninstallFile;
  }
  
  protected function generateConfig()
  {
    return rex_generateAddons($this->configArray);
  }
  
  protected function apiCall($method, $arguments)
  {
    if(!is_array($arguments))
      trigger_error('Expecting $arguments to be an array!', E_USER_ERROR);
      
    return rex_call_func(array('OOAddon', $method), $arguments, false);
  }
  
  protected function baseFolder($addonName)
  {
    return rex_addons_folder($addonName);
  }
  
  protected function mediaFolder($addonName)
  {
    global $REX;
    return $REX['OPENMEDIAFOLDER'] .DIRECTORY_SEPARATOR .'addons'. DIRECTORY_SEPARATOR .$addonName;
  }
}