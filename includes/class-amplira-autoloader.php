<?php
/**
 * Autoloader for Amplira plugin classes
 */
class Autoloader {
    /**
     * Register the autoloader
     */
    public static function register() {
        spl_autoload_register(array(new self(), 'autoload'));
    }

    /**
     * Autoload Amplira classes
     *
     * @param string $class The fully-qualified class name.
     */
    public function autoload($class) {
        $prefix = 'Amplira\\';
        $base_dir = AMPLIRA_PLUGIN_DIR . 'includes/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . 'class-amplira-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
}

Autoloader::register();