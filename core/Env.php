<?php
class Env {
  protected static $params = [
    'USER',
    'PROJECT',
    'PROJECT_DIR',
    'PROJECT_ENV',
    'APP_DIR',
    'HTML_DIR',
    'CONFIG_DIR',
    'ENV_DIR',
    'BIN_DIR',
    'RUN_DIR',
    'LOG_DIR',
    'VAR_DIR',
    'TMP_DIR',
    'KISS_CORE',
    'HTTP_HOST',
    'CONFIG_TEMPALTE_DIR',
  ];

  public static function init() {
    static::configure(getenv('APP_DIR') . '/config.ini.tpl');
    static::generateConfigs();
    static::generateURIMap();
    static::generateParamMap();
    static::generateNginxRouteMap();
  }

  public static function configure($template, array $params = []) {
    // Add default params
    foreach (static::$params as $param) {
      $params['%' . $param . '%'] = getenv($param);
    }

    // Add extra params
    $params += [
      '%DEBUG%' => (int) App::$debug,
    ];

    foreach(is_dir($template) ? glob($template . '/*.tpl') : [$template] as $file) {
      file_put_contents(getenv('CONFIG_DIR') . '/' . basename($file, '.tpl'), strtr(file_get_contents($file), $params));
    }
  }

  protected static function generateConfigs() {
    $configure = function ($file) {
      return include $file;
    };

    foreach (glob(getenv('APP_DIR') . '/plugin/*/configure.php') as $file) {
      $configure($file);
    }
  }

  /**
   * Генерация карты для обработки входящих запросов по URI
   */
  protected static function generateURIMap() {
    $map = [];
    $action_dir = getenv('APP_DIR') . '/actions';
    $files = explode(PHP_EOL, trim(`find -L $action_dir -name '*.php'`));
    foreach ($files as $file) {
      $action = substr(basename($file), 0, -4);
      $content = file_get_contents($file);
      if (preg_match_all('/^\s*\*\s*\@route\s+([^\:]+?)(\:(.+))?$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $pattern = trim($m[1][$k]);
          $params  = isset($m[2][$k]) && $m[2][$k] ? array_map('trim', explode(',', substr($m[2][$k], 1))) : [];
          array_unshift($params, $action);
          $map[$pattern] = $params;
        }
      }
    }
    App::writeJSON(config('common.uri_map_file'), $map);
  }

  protected static function generateParamMap() {
    $map = [];
    $action_dir = getenv('APP_DIR') . '/actions';
    $files = explode(PHP_EOL, trim(`find -L $action_dir -name '*.php'`));
    foreach ($files as $file) {
      $content = file_get_contents($file);
      if (preg_match_all('/^\s*\*\s*\@param\s+([a-z]+)\s+(.+?)$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $map[$file][] = [
            'name'    => $param = substr(strtok($m[2][$k], ' '), 1),
            'type'    => $m[1][$k],
            'default' => trim(substr($m[2][$k], strlen($param) + 1)) ?: null,
          ];
        }
      }
    }
    App::writeJSON(config('common.param_map_file'), $map);
  }

  protected static function generateNginxRouteMap() {
    $routes = App::getJSON(config('common.uri_map_file'));
    uasort($routes, function ($a, $b) {
      return (sizeof($a) > sizeof($b)) ? 1 : -1;
    });

    $rewrites = [];
    foreach ($routes as $route => $action) {
      $i = 0; // route like (bla (bla bla)) with uff8 cant handle by nginx. so hack it
      $uri = '/?ROUTE='
           . preg_replace_callback(
              '|\([^\)]+\)|is',
              function ($item) use (&$i) {
                return '$' . ++$i;
              },
              $route
            )
           . '&ACTION=' . array_shift($action)
      ;

      if ($action) {
        foreach ($action as $k => $v) {
          $uri .= '&' . $v . '=$' . ($k + 1);
        }
      }
      $rewrites[] = "rewrite '(*UTF8)^/$route/?$' '$uri';";
    }
    file_put_contents(config('common.nginx_route_file'), implode(PHP_EOL, $rewrites));
  }
}