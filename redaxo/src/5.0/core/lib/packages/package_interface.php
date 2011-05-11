<?php

/**
 * Interface for packages
 *
 * @author gharlan
 */
interface rex_packageInterface
{
	/**
   * Returns the name of the package
   *
   * @return string Name
   */
  public function getName();

  /**
   * Returns the related Addon
   *
   * @return rex_addon
   */
  public function getAddon();

  /**
   * Returns the package ID
   *
   * @return string
   */
  public function getPackageId();

  /**
   * Returns the base path
   *
   * @param string $file File
   */
  public function getBasePath($file = '');

  /**
   * Returns the assets path
   *
   * @param string $file File
   */
  public function getAssetsPath($file = '');

  /**
   * Returns the data path
   *
   * @param string $file File
   */
  public function getDataPath($file = '');

  /**
   * @see rex_config::set()
   */
  public function setConfig($key, $value);

  /**
   * @see rex_config::get()
   */
  public function getConfig($key, $default = null);

  /**
   * @see rex_config::has()
   */
  public function hasConfig($key);

  /**
   * Sets a property
   *
   * @param string $key Key of the property
   * @param mixed $value New value for the property
   */
  public function setProperty($key, $value);

  /**
   * Returns a property
   *
   * @param string $key Key of the property
   * @param mixed $default Default value, will be returned if the property isn't set
   *
   * @return mixed
   */
  public function getProperty($key, $default = null);

  /**
   * Returns if a property is set
   *
   * @param string $key Key of the property
   *
   * @return boolean
   */
  public function hasProperty($key);

	/**
   * Returns if the package is available (activated and installed)
   *
   * @return boolean
   */
  public function isAvailable();

	/**
   * Returns if the package is installed
   *
   * @return boolean
   */
  public function isInstalled();

	/**
   * Returns if the package is activated
   *
   * @return boolean
   */
  public function isActivated();

	/**
   * Returns if it is a system package
   *
   * @return boolean
   */
  public function isSystemPackage();

  /**
   * Returns the author
   *
   * @param mixed $default Default value, will be returned if the property isn't set
   *
   * @return mixed
   */
  public function getAuthor($default = null);

  /**
   * Returns the version
   *
   * @param mixed $default Default value, will be returned if the property isn't set
   *
   * @return mixed
   */
  public function getVersion($default = null);

  /**
   * Returns the supportpage
   *
   * @param mixed $default Default value, will be returned if the property isn't set
   *
   * @return mixed
   */
  public function getSupportPage($default = null);

  /**
   * Includes a file in the package context
   *
   * @param string $file Filename
   * @param array $globals Array of global variablenames
   */
  public function includeFile($file, array $globals = array());
}