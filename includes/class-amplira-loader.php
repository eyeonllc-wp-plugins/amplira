<?php
/**
 * The core plugin class.
 */
class Amplira_Loader {


    /**
     * The array of actions registered with WordPress.
     */
    protected $actions;

    /**
     * The array of filters registered with WordPress.
     */
    protected $filters;

    /**
     * Initialize the collections used to maintain the actions and filters.
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();

        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        require_once AMPLIRA_PLUGIN_DIR . 'includes/class-amplira-ai.php';
        require_once AMPLIRA_PLUGIN_DIR . 'admin/class-amplira-admin.php';
        require_once AMPLIRA_PLUGIN_DIR . 'includes/class-amplira-shortcode.php';
        // ... other dependencies ...
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks() {
        $plugin_admin = new Amplira_Admin();

        // Admin menu and pages
        $this->add_action('admin_menu', $plugin_admin, 'add_admin_pages');
        $this->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_admin_assets');

        // AJAX handlers
        $this->add_action('wp_ajax_amplira_get_templates', $plugin_admin, 'get_templates');
        $this->add_action('wp_ajax_amplira_get_pages_with_shortcodes', $plugin_admin, 'get_pages_with_shortcodes');
        $this->add_action('wp_ajax_amplira_analyze_page', $plugin_admin, 'analyze_page');
        $this->add_action('wp_ajax_amplira_duplicate_page', $plugin_admin, 'duplicate_page');

        // Add settings
        $this->add_action('admin_init', $plugin_admin, 'register_settings');

        // Remove this line
        // $plugin_admin->register_ajax_handlers();
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single collection.
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }

    
}
