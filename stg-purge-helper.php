<?php
/*
 * Plugin Name: StgNews Purge Helper
 * Version: 1.0
 * Plugin URI: http://www.olwm.com/
 * Description: Companion plugin for nginx helper. Send purge http requests to other nodes in cluster.
 * Author: Brett Millett
 * Author URI: http://www.olwm.com/
 * Requires at least: 3.9
 * Tested up to: 4.0
 *
 * @package WordPress
 * @author Brett Millett
 * @since 1.0.0
 */
namespace OLWM\WP\Nginx {
    if (!defined('ABSPATH')) exit;
    define('OLWM\WP\Nginx\OLWM_WP_NGINX_HELPER_NAME', 'nginx-helper/nginx-helper.php');

    /**
     * Detect plugin. For use on Front End only.
     */
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    // check for nginx-helper plugin
    if (is_plugin_active(OLWM_WP_NGINX_HELPER_NAME)) {

        class Helper {

            private $_namespace = 'stg-purge-helper';
            private $_queue = array();
            private $_ch = array();
            private $_options = array();

            function __construct() {
                $this->_options = get_option($this->_namespace . '-options');
            }

            /**
             * destructor that will proccess queue and send purge requests to all
             * relay nodes that have been configured.
             */
            function __destruct() {

                // create cURL resources
                foreach ($this->_queue as $host => $paths) {
                    foreach ($paths as $uri) {

                        // get current host's IP
                        $ip = gethostbyname(parse_url($host, PHP_URL_HOST));

                        // If we have already sent purge request for this host...skip.
                        if (!empty($this->_options['ignore']) && $ip == $_SERVER['SERVER_ADDR'])
                            continue;

                        // send headers
                        $headers = array(
                            'User-Agent: Wordpress/' . get_bloginfo('version')
                        );
                        
                        // host header change?
                        if (!empty($this->_options['change'])) {
                            // get an IP URL so we can pass host header
                            $url = parse_url($host, PHP_URL_SCHEME) . '://' . $ip . $uri;
                            // attempt to override host header
                            $headers[] = 'Host: ' . $_SERVER['HTTP_HOST'];
                        } else {
                            $url = $host . $uri;
                        }

                        // create a unique handle name for reference.
                        $handle = substr(sha1($host . $uri), 0, 15);
                        $this->_ch[$handle] = curl_init($url);

                        // set ooptions
                        curl_setopt($this->_ch[$handle], CURLOPT_HTTPHEADER, $headers);
                        //curl_setopt($this->_ch[$handle], CURLOPT_NOBODY, 1);
                        curl_setopt($this->_ch[$handle], CURLOPT_HEADER, 0);
                    }
                }

                $mh = curl_multi_init();

//                curl_multi_setopt($mh, CURLMOPT_PIPELINING, 1);
//                curl_multi_setopt($mh, CURLMOPT_MAXCONNECTS, 1);

                foreach ($this->_ch as &$ch) {
                    curl_multi_add_handle($mh, $ch);
                }

                $active = null;
                //execute the handles
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);

                while ($active && $mrc == CURLM_OK) {
                    if (curl_multi_select($mh) != -1) {
                        do {
                            $mrc = curl_multi_exec($mh, $active);
                        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
                    }
                }

                foreach ($this->_ch as &$ch) {
                    curl_multi_remove_handle($mh, $ch);
                }

                curl_multi_close($mh);
            }

            /**
             * Capture all purge urls into queue.
             *
             * @param array $response
             * @param type $type
             * @param type $class
             * @param array $args
             * @param string $url
             */
            function queue_cluster_purge($response, $type, $class, $args, $url) {
                $parsed_url = parse_url($url);
                if (strpos($url, $parsed_url['host'] . '/purge/') !== FALSE) {
                    foreach ($this->get_cluster_hosts() as $host) {
                        if (!filter_var($host, FILTER_VALIDATE_URL)) {
                            continue;
                        }
                        if (!isset($this->_queue[$host])) {
                            $this->_queue[$host] = array();
                        }
                        $this->_queue[$host][] = $parsed_url['path'];
                    }
                }
            }

            /**
             * Get relay node hosts from database.
             *
             * @return array
             */
            function get_cluster_hosts() {
                $field = 'hosts';
                $value = (isset($this->_options[$field])) ? $this->_options[$field] : '';
                $hosts = array_map('trim', explode("\n", $value));
                return $hosts;
            }

        }

        // get instance of purge helper.
        $helper = new Helper();
        // hook http debug to get current url.
        add_action('http_api_debug', array(&$helper, 'queue_cluster_purge'), 10, 5);

        // setup admin functionality
        include_once ('admin/libs/admin.php');
        new Admin(__FILE__);
    }
}