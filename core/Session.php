<?php
/**
 * Class Session
 * Work with sessions
 *
 * <code>
 * Session::start();
 * Session::set('key', 'Test value');
 * Session::get('key');
 * Session::remove('key');
 * if (Session::has('key')) echo 'Found key in Session';
 * Session::regenerate();
 * </code>
 *
 * Add calculated data if key not exists
 * <code>
 * Session::add('key', function () { return time(); });
 * </code>
 *
 * Get key from session with default value
 * <code>
 * Session:get('key', 'default');
 * </code>
 */
class Session {
  /** @var Session $Instance */
  protected static $Instance = null;

  /** @var array $container */
  protected static $container = [];

  public final function __construct() {}

  public static function start() {
    session_name(config('session.name'));
    session_start();
    static::$container = &$_SESSION;
  }

  /**
   * Regenrate new session ID
   */
  public static function regenerate() {
    session_regenerate_id();
  }

  /**
   * @param string $key
   * @return bool
   */
  public static function has($key) {
    assert('is_string($key)');
    return isset(static::$container[$key]);
  }

  /**
   * Add new session var if it not exists
   * @param string $key
   * @param mixed $value Can be callable function, so it executes and pushes
   * @return void
   */
  public static function add($key, $value) {
    if (!static::has($key)) {
      static::set($key, is_callable($value) ? $value() : $value);
    }
  }

  /**
   * Set new var into session
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public static function set($key, $value) {
    assert("is_string(\$key)");
    static::$container[$key] = $value;
  }

  /**
   * Remove the key from session array
   * @param string $key
   * @return bool
   */
  public static function remove($key) {
    assert("is_string(\$key)");
    if (isset(static::$container[$key])) {
      unset(static::$container[$key]);
      return true;
    }
    return  false;
  }

  /**
   * Alias for self::remove
   * @see self::remove
   */
  public static function delete($key) {
    return static::remove($key);
  }

  /**
   * Get var with key from session array
   * @param string $key
   * @param mixed $default Return default there is no such key, set on closure
   * @return mixed
   */
  public static function get($key, $default = null) {
    if (!static::has($key) && $default && is_callable($default)) {
      $default = $default();
      static::set($key, $default);
    }
    return static::has($key) ? static::$container[$key] : $default;
  }
}
