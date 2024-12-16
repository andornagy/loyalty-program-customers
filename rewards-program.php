<?php

/**
 * Plugin Name: Rewards Program CSV Downloader
 * Plugin URI: https://jilllynndesign.com/
 * Description: Downloads teh CVS sent to a sepcific email, and extracts the customer data, then saves it into the database
 * Version: 1.0.0
 * Author: Andor Nagy
 * Author URI: https://jilllynndesign.com/
 */


if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Autoload classes
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/classes/GmailHandler.php';
require_once __DIR__ . '/classes/PluginSettings.php';
require_once __DIR__ . '/classes/CSVProcessor.php';

use RewardsProgram\PluginSettings;

// Initialize plugin
if (is_admin()) {
    new PluginSettings();
}


function call_plugin_settings_method()
{
    $plugin_settings = new PluginSettings();
    $plugin_settings->check_and_download_csv_cron(); // Replace with the actual method.
}

add_action('rewards_program_daily_cron', 'call_plugin_settings_method');
