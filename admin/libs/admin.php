<?php
namespace OLWM\WP\Nginx {
    if (!defined('ABSPATH')) exit;

    /**
     * Description of Admin
     *
     * @author Brett Millett <bmillett@olwm.com>
     */
    class Admin {

        private $dir;
        private $file;
        private $assets_dir;
        private $assets_url;
        private $views_dir;
        private $namespace = 'stg-purge-helper';

        public function __construct($file) {
            // Set plugin paths
            $this->file = $file;
            $this->dir = dirname($this->file);
            $this->assets_dir = trailingslashit($this->dir) . 'admin/assets';
            $this->assets_url = esc_url(trailingslashit(plugins_url('/admin/assets/', $this->file)));
            $this->views_dir = trailingslashit($this->dir) . 'admin/views';

            //do all wordpress hooking
            $this->_wordPressHooks();
        }

        /**
         * action hooks for admin
         */
        private function _wordPressHooks() {
            if (is_admin()) {
                // Admin Settings
                add_action('admin_init', array($this, 'adminSettings'));
                // Admin page
                add_action('admin_menu', array($this, 'adminMenu'));
            }
            // scripts and css
            //add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));
        }

        /**
         * Start Admin Settings
         */
        function adminSettings() {

            if (FALSE == get_option($this->namespace . '-options')) {
                add_option($this->namespace . '-options');
            }

            register_setting($this->namespace, $this->namespace . '-options', array($this, 'validateOptions'));

            add_settings_section($this->namespace . '-nodes-section', __('Purge Relay Hosts'), array($this, 'nodesSectionText'), $this->namespace);
            add_settings_field($this->namespace . '-host', __('Enter URLs delimited by carriage returns'), array($this, 'makeSettingInputTextArea_hosts'), $this->namespace, $this->namespace . '-nodes-section');
        }

        function adminMenu() {
            add_plugins_page(__('Purge Helper'), __('Purge Helper'), 'manage_options', $this->namespace . '-options', array($this, 'settingsPage'));
        }

        function settingsPage() {
            $data = array();
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }
            $data['options'] = get_option($this->namespace . '-options');
            $data['namespace'] = $this->namespace;
            echo $this->_renderView('settings.php', $data);
        }

        /**
         * Validate hosts and any other future fields
         *
         * @param array $inputs
         * @return array
         */
        function validateOptions($inputs) {
            $new_inputs = array();
            foreach ($inputs as $key => $value) {
                switch ($key) {
                    case 'hosts':
                        $lines = array_map('trim', explode("\n", $value));
                        foreach ($lines as $idx => $line) {
                            if (!filter_var($line, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED)) {
                                unset($lines[$idx]);
                            }
                        }
                        $new_inputs[$key] = implode("\n", $lines);
                        break;
                    default:
                        $new_inputs[$key] = sanitize_text_field($value);
                        break;
                }
            }
            return $new_inputs;
        }

        function nodesSectionText() {
            echo '<p>' . __('Enter individual URLs (base with scheme) for each of your clustered load balanced hosts.') . '</p>';
            echo '<p>' . __('This plugin will attempt to relay the same purge URIs that are captured when a post/page is updated<br/>(currently, clicking the "Purge Cache" button will not relay to hosts.)') . '</p>';
            echo '<p>' . __('The <a href="https://rtcamp.com/nginx-helper/" target="_blank">Nginx Helper plugin</a> must be active for purge relays to work.') . '</p>';
        }

        public function enqueueScripts() {
//            wp_register_script(
//                    $this->namespace, $this->assets_url . 'js/script.js', array('jquery-ui-datepicker')
//            );
//            wp_enqueue_script($this->namespace);
//
//            $localized = array(
//                'namespace' => $this->namespace,
//                'ajax_url' => $this->env['ajax_url'],
//                'assets_url' => $this->assets_url
//            );
//
//            wp_localize_script($this->namespace, str_replace('-', '_', $this->namespace) . '_env', $localized);
//
//            //
//            wp_enqueue_style('jquery-ui-datepicker', 'http://code.jquery.com/ui/1.10.4/themes/smoothness/jquery-ui.css');
//            wp_register_style($this->namespace, $this->assets_url . 'css/styles.css', array('jquery-ui-datepicker'));
//            wp_enqueue_style($this->namespace);
        }

        /**
         * Print or return view files
         *
         * @param string $filename
         * @param array $data
         * @param boolean $return_buffer
         * @return boolean
         */
        private function _renderView($filename, $data, $return_buffer = true) {
            $filepath = trailingslashit($this->views_dir) . $filename;
            if (file_exists($filepath)) {
                if ($return_buffer) {
                    ob_start();
                }
                extract($data, EXTR_SKIP);
                printf("\n<!--Render View: %s-->\n", $filename);
                include $filepath;
                print("\n<!--End Render-->\n");
                if ($return_buffer) {
                    return ob_get_clean();
                }
                return TRUE;
            }
            return FALSE;
        }

        /**
         * Handle magic methods
         *
         * @param string $name
         * @param array $arguments
         */
        function __call($name, $arguments) {
            // handler for setting inputs
            if (preg_match('/^makeSettingInput(TextArea|Text|Password|Checkbox|Select|Radio)_([a-zA-Z_-]+)$/', $name, $matches)) {
                list(, $input_type, $field) = $matches;
                // get option
                $option = get_option($this->namespace . '-options');
                $value = (isset($option[$field])) ? $option[$field] : '';
                switch ($input_type) {
                    case 'TextArea':
                        printf('<textarea name="%s-options[%s]" id="%s">%s</textarea>', $this->namespace, $field, $field, $value);
                        break;
                    case 'Text':
                        printf('<input type="text" name="%s-options[%s]" id="%s" value="%s" />', $this->namespace, $field, $field, $value);
                        break;
                    case 'Password':
                        printf('<input type="password" name="%s-options[%s]" id="%s" value="%s" />', $this->namespace, $field, $field, $value);
                        break;
                    case 'Checkbox':
                    case 'Select':
                    case 'Radio':
                        echo '<p>Not implemented yet!</>';
                }
            }
        }

    }

}
