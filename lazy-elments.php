<?php

/*
  Plugin Name: Lazy Elements
  Description: Ajax support for each Shortcode
  Version: 1.1
  Author: Weber Informatics LLC - Janosch Weber
  License: Proprietary
 */

define("CACHE_ENABLED", true);

add_shortcode("lazy_element", function ($args, $content) {
    $get_params = apply_filters('le_ajax_params_before_load', $_GET);

    $cache_key = null;
    if (CACHE_ENABLED) {
        $cache_key = generate_cache_key($get_params, $content, $args);
        $cache_file_path = get_cache_file_path($cache_key);
        if (!file_exists(dirname($cache_file_path))) {
            if (!mkdir(dirname($cache_file_path))) {
                return "It was not possible to create the directory '/wp-content/lazy-elements'. Do you have enough permissions? You can also create it manually.";
            }
        }

        if (file_exists(get_cache_file_path($cache_key, true))) {
            return file_get_contents(get_cache_file_path($cache_key, true));
        }
    }

    $lazy_wrapper_content_id = "content_" . rand(0, PHP_INT_MAX);
    $get_params["cacheKey"] = $cache_key;
    $get_params["content"] = base64_encode($content);
    $get_params["contentKey"] = hash("sha256", $content . NONCE_SALT);
    $get_params = json_encode($get_params);

    $html = "<div id='ajax-placeholder-" . $lazy_wrapper_content_id . "' ><img alt='Loading indicator' src='" . plugin_dir_url(__FILE__) . "/images/loader.gif'></div>";
    $only_if_visible = !isset($args["only_if_visible"]) ? false : filter_var($args["only_if_visible"], FILTER_VALIDATE_BOOLEAN);
    $html .= "<script>jQuery(document).ready(function () {lazyElements.startWatching('" . $lazy_wrapper_content_id . "', " . $get_params . ", " . $only_if_visible . ")})</script>";
    return $html;
});


function lazy_elements_ajax_func()
{
    $cache_key = $_POST["cacheKey"];
    if (CACHE_ENABLED && file_exists(get_cache_file_path($cache_key, true))) {
        //Is used if you have a nested already processed shortcode which is watching.
        wp_send_json_success(file_get_contents(get_cache_file_path($cache_key, true)));
        return;
    }

    unset($_POST["cacheKey"]);
    unset($_POST["action"]);

    $queryString = "";
    foreach ($_POST as $key => $value) {
        $_GET[$key] = $value;
        $queryString .= $key . "=" . $value . "&";
    }

    $_SERVER['QUERY_STRING'] = $queryString;
    $content = base64_decode($_POST["content"]);
    if (!$content || empty($content) || $_GET["contentKey"] !== hash("sha256", $content . NONCE_SALT)) {
        wp_send_json_error("No content.");
        return;
    }

    $processed_content = do_shortcode($content);
    if (CACHE_ENABLED && !empty($processed_content)) {
        file_put_contents(get_cache_file_path($cache_key, true), $processed_content);
    }

    wp_send_json_success($processed_content);
}

function get_cache_file_path($cache_key, $processed_content = false)
{
    return ABSPATH . "wp-content/lazy-elements/" . $cache_key . ($processed_content ? ".p" : "");
}

function generate_cache_key($request_params, $content, $args)
{
    unset($request_params["contentId"]);
    unset($request_params["cacheKey"]);

    ksort($request_params);
    $cache_key = "";
    foreach ($request_params as $key => $param) {
        $cache_key .= $key . serialize($param);
        $cache_key = md5($cache_key);
    }
    return md5($cache_key . $content . serialize($args) . NONCE_SALT);
}

add_action('wp_ajax_lazy_element_action', "lazy_elements_ajax_func");
add_action('wp_ajax_nopriv_lazy_element_action', "lazy_elements_ajax_func");

add_action("wp_enqueue_scripts", function () {
    wp_enqueue_script('lazy-elements', plugin_dir_url(__FILE__) . '/js/lazy-elements.js', array('jquery', 'wp-util'));
});

function delete_lazy_elements()
{
    $dir = ABSPATH . "wp-content/lazy-elements";
    if (file_exists($dir)) {
        $di = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }
    }
}

add_action('delete_lazy_elements_cron', 'delete_lazy_elements');
if (CACHE_ENABLED) {
    if (!wp_next_scheduled('delete_lazy_elements_cron')) {
        wp_schedule_event(time(), 'daily', 'delete_lazy_elements_cron');
    }
}
