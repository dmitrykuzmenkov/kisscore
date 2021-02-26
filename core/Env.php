<?php
final class Env {
  protected static $params = [
    'PROJECT',
    'PROJECT_DIR',
    'PROJECT_ENV',
    'PROJECT_REV',
    'APP_DIR',
    'STATIC_DIR',
    'CONFIG_DIR',
    'ENV_DIR',
    'BIN_DIR',
    'RUN_DIR',
    'LOG_DIR',
    'VAR_DIR',
    'TMP_DIR',
    'KISS_CORE',
  ];

  /**
   * Initialization of Application
   *
   * @return void
   */
  public static function init(): void {
    static::configure(getenv('APP_DIR') . '/config/app.ini.tpl');
    static::compileConfig();
    static::generateActionMap();
    static::generateURIMap();
    static::generateParamMap();
    static::generateTriggerMap();
    static::generateConfigs();
    static::prepareDirs();
  }

  /**
   * Configure all config tempaltes in dir $template or special $template file
   *
   * @param string $template
   * @param array $params
   * @return void
   */
  public static function configure(string $template, array $params = []): void {
    // Add default params
    foreach (static::$params as $param) {
      $params['{{' . $param . '}}'] = getenv($param);
    }

    // Add extra params
    $params += [
      '{{DEBUG}}' => (int) App::$debug,
    ];

    foreach(is_dir($template) ? glob($template . '/*.tpl') : [$template] as $file) {
      file_put_contents(getenv('CONFIG_DIR') . '/' . basename($file, '.tpl'), strtr(file_get_contents($file), $params));
    }
  }

  /**
   * Compile config.json into fast php array to include it ready to use optimized config
   */
  protected static function compileConfig(): void {
    $env = getenv('PROJECT_ENV');

    $config = [];
    // Prepare production config replacement
    foreach (parse_ini_file(getenv('CONFIG_DIR') . '/app.ini', true) as $group => $block) {
      if (str_contains($group, ':') && explode(':', $group)[1] === $env) {
        $origin = strtok($group, ':');
        $config[$origin] = array_merge($config[$origin], $block);
        $group = $origin;
      } else {
        $config[$group] = $block;
      }

      // Make dot.notation for group access
      foreach ($config[$group] as $key => &$val) {
        $config[$group . '.' . $key] = &$val;
      }
    }

    // Iterate to make dot.notation.direct.access
    $Iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($config));
    foreach ($Iterator as $leaf_value) {
      $keys = [];
      foreach (range(0, $Iterator->getDepth()) as $depth) {
        $keys[] = $Iterator->getSubIterator($depth)->key();
      }
      $config[join('.', $keys)] = $leaf_value;
    }

    file_put_contents(getenv('CONFIG_DIR') . '/config.php', '<?php return ' . var_export($config, true) . ';');
  }

  /**
   * Generate all configs for configurable plugins. It includes all plugin/_/configure.php files
   * @return void
   */
  protected static function generateConfigs(): void {
    $configure = function ($file) {
      return include $file;
    };

    foreach (glob(getenv('APP_DIR') . '/config/*/configure.php') as $file) {
      $configure($file);
    }
  }

  protected static function prepareDirs(): void {
    if (!is_dir(config('view.compile_dir'))) {
      mkdir(config('view.compile_dir'), 0700, true);
    }
  }

  /**
   * Generate nginx URI map for route request to special file
   */
  protected static function generateURIMap(): void {
    $map = [];
    foreach (static::getPHPFiles(getenv('APP_DIR') . '/actions') as $file) {
      $content = file_get_contents($file);
      if (preg_match_all('/^\s*\*\s*\@route\s+([^\:]+?)(\:(.+))?$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $pattern = trim($m[1][$k]);
          $params  = isset($m[2][$k]) && $m[2][$k] ? array_map('trim', explode(',', substr($m[2][$k], 1))) : [];
          array_unshift($params, static::getActionByFile($file));
          $map[$pattern] = $params;
        }
      }
    }
    App::writeJSON(config('common.uri_map_file'), $map);
  }

  /**
   * Generate action => file_path map
   */
  protected static function generateActionMap(): void {
    $map = [];
    foreach (static::getPHPFiles(getenv('APP_DIR') . '/actions') as $file) {
      $map[static::getActionByFile($file)] = $file;
    }
    App::writeJSON(config('common.action_map_file'), $map);
  }

  /**
   * Generate parameters map from annotations in actions and triggers files
   */
  protected static function generateParamMap(): void {
    $map_files = [
      'actions'  => config('common.param_map_file'),
      'triggers' => config('common.trigger_param_file'),
    ];
    foreach ($map_files as $folder => $map_file) {
      $map = [];
      foreach (static::getPHPFiles(getenv('APP_DIR') . '/' . $folder) as $file) {
        $content = file_get_contents($file);
        if (preg_match_all('/^\s*\*\s*\@param\s+([a-z]+)\s+(.+?)$/ium', $content, $m)) {
          foreach ($m[0] as $k => $matches) {
            $param = substr(strtok($m[2][$k], ' '), 1);
            $map[$file][] = [
              'name'    => $param,
              'type'    => $m[1][$k],
              'default' => trim(substr($m[2][$k], strlen($param) + 1)) ?: null,
            ];
          }
        }
      }
      App::writeJSON($map_file, $map);
    }
  }

  /**
   * Generate trigger map to be called on some event
   */
  protected static function generateTriggerMap(): void {
    $map = [];
    foreach (static::getPHPFiles(getenv('APP_DIR') . '/triggers') as $file) {
      $content = file_get_contents($file);
      if (preg_match_all('/^\s*\*\s*\@event\s+([^\$]+?)$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $pattern = trim($m[1][$k]);
          if (!isset($map[$pattern])) {
            $map[$pattern] = [];
          }
          $map[$pattern] = array_merge($map[$pattern], [$file]);
        }
      }
    }
    App::writeJSON(config('common.trigger_map_file'), $map);
  }

   protected static function getActionByFile(string $file): string {
     return substr(trim(str_replace(getenv('APP_DIR') . '/actions', '', $file), '/'), 0, -4);
   }

  /**
   * Helper for getting list of all php files in dir
   * @param string $dir
   * @return array
   */
  protected static function getPHPFiles(string $dir): array {
    assert(is_dir($dir));
    $output = `find -L $dir -name '*.php'`;
    return $output ? explode(PHP_EOL, trim($output)) : [];
  }
}
