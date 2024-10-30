<?php

/**
 * Plugin Name: Rewards Program
 * Plugin URI: https://jilllynndesign.com/
 * Description: Custom Funcationality for the Rewards Program
 * Version: 0.5
 * Author: Andor Nagy
 * Author URI: https://andornagy.com/
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
