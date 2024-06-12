<?php

/**
 * Plugin Name: Rewards Program
 * Plugin URI: https://jilllynndesign.com/
 * Description: Custom Funcationality for the Rewards Program
 * Version: 0.3
 * Author: Andor Nagy
 * Author URI: https://andornagy.com/
 */

include(plugin_dir_path(__FILE__) . 'functions.php');
include(plugin_dir_path(__FILE__) . 'options.php');

// Activation hook
register_activation_hook(__FILE__, 'jldrp_email_to_post_activate');

// Deactivation hook
register_deactivation_hook(__FILE__, 'jldrp_email_to_post_deactivate');

// Schedule daily task on activation
function jldrp_email_to_post_activate()
{
  if (!wp_next_scheduled('jldrp_email_to_post_process_emails')) {
    wp_schedule_event(time(), 'daily', 'jldrp_email_to_post_process_emails');
  }
}

// Unschedule daily task on deactivation
function jldrp_email_to_post_deactivate()
{
  wp_clear_scheduled_hook('jldrp_email_to_post_process_emails');
  jldrp_remove_options_from_db();
}

// Schedule daily email processing task
add_action('jldrp_email_to_post_process_emails', 'jldrp_new_email_check');


function jldrp_remove_options_from_db()
{
  delete_option('jldrp_hostname');
  delete_option('jldrp_username');
  delete_option('jldrp_password');
  delete_option('jldrp_csv_process_offset');
  delete_option('jldrp_inbox_connection_status');
  delete_option('jldrp_csv_process_running');
  delete_option('jldrp_last_attachment');
}
