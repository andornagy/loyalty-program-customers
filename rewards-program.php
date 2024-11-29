<?php

/**
 * Plugin Name: Rewards Program CSV Downloader
 * Plugin URI: https://jilllynndesign.com/
 * Description: Downloads teh CVS sent to a sepcific email, and extracts the customer data, then saves it into the database
 * Version: 0.7
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
