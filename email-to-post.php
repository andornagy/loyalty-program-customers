<?php

/**
 * Plugin Name: Email to Customers
 * Plugin URI: https://andornagy.com
 * Description: Add new customers to the DB sent by email
 * Version: 0.0.1
 * Author: Andor Nagy
 * Author URI: https://andornagy.com
 */

include(plugin_dir_path(__FILE__) . 'functions.php');
include(plugin_dir_path(__FILE__) . 'options.php');

// Activation hook
register_activation_hook(__FILE__, 'e2p_email_to_post_activate');

// Deactivation hook
register_deactivation_hook(__FILE__, 'e2p_email_to_post_deactivate');

// Schedule daily task on activation
function e2p_email_to_post_activate()
{
  if (!wp_next_scheduled('e2p_email_to_post_process_emails')) {
    wp_schedule_event(time(), 'daily', 'e2p_email_to_post_process_emails');
  }
}

// Unschedule daily task on deactivation
function e2p_email_to_post_deactivate()
{
  wp_clear_scheduled_hook('e2p_email_to_post_process_emails');
}

// Schedule daily email processing task
add_action('e2p_email_to_post_process_emails', 'e2p_new_email_check');
