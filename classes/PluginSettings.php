<?php

namespace RewardsProgram;

class PluginSettings
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_gmail_authenticate', [$this, 'authenticate_gmail']);
        add_action('admin_post_gmail_check_email', [$this, 'check_and_download_csv_manually']);
        add_action('rewards_program_daily_cron', [$this, 'check_and_download_csv_cron']);
        register_deactivation_hook(plugin_basename(__FILE__), [$this, 'deactivate_plugin']);

        // Schedule cron job on plugin initialization
        add_action('init', [$this, 'schedule_cron_job']);
    }

    /**
     * Registers the settings page for the plugin.
     *
     * @action admin_menu
     *
     * @return void
     */
    public function register_settings_page()
    {
        add_options_page(
            'Rewards Program CSV Downloader',
            'Rewards Program CSV',
            'manage_options',
            'rewards-program',
            [$this, 'settings_page_html']
        );
    }

    /**
     * Registers the settings for the plugin.
     *
     * @action admin_init
     *
     * @return void
     */
    public function register_settings()
    {
        register_setting('rewards_program_options', 'gmail_access_token');
        register_setting('rewards_program_options', 'gmail_last_check');
        register_setting('rewards_program_options', 'gmail_last_file');
        register_setting('rewards_program_options', 'gmail_last_processed_id');
    }

    /**
     * Displays the settings page for the plugin.
     *
     * @return void
     */
    public function settings_page_html()
    {
        if (!current_user_can('manage_options')) return;
        include plugin_dir_path(__FILE__) . '../templates/settings-page.php';
    }

    /**
     * Initiates the Gmail authentication process.
     *
     * This method creates an instance of GmailHandler and calls its authenticate method 
     * to start the OAuth authentication flow with Google. It handles the redirection 
     * to Google's authorization page and stores the access token upon successful authentication.
     *
     * @action admin_post_gmail_authenticate
     *
     * @return void
     */
    public function authenticate_gmail()
    {
        $gmailHandler = new GmailHandler();
        $gmailHandler->authenticate();
    }

    /**
     * Checks for new CSV attachments in Gmail and downloads them.
     *
     * This method uses the GmailHandler to fetch and process new unread emails
     * with CSV attachments. After processing, it redirects the user to the
     * Rewards Program settings page.
     *
     * @action admin_post_gmail_check_email
     *
     * @return void
     */
    public function check_and_download_csv_manually()
    {
        $gmailHandler = new GmailHandler();
        $gmailHandler->check_for_csv();
        wp_redirect(admin_url('options-general.php?page=rewards-program'));
        exit;
    }

    public function check_and_download_csv_cron()
    {
        error_log('Cron job is running.');
        $gmailHandler = new GmailHandler();
        if ($gmailHandler->check_for_csv()) {
            error_log('Daily Gmail check and CSV download completed.');
        } else {
            error_log('No CSV files found or an error occurred.');
        }
    }


    public function schedule_cron_job()
    {
        error_log('schedule_cron_job triggered.');

        if (!wp_next_scheduled('rewards_program_daily_cron')) {
            wp_schedule_event(time(), 'daily', 'rewards_program_daily_cron');
            error_log('Cron job rewards_program_daily_cron has been scheduled.');
        } else {
            error_log('Cron job rewards_program_daily_cron is already scheduled.');
        }
    }

    public function deactivate_plugin()
    {
        $timestamp = wp_next_scheduled('rewards_program_daily_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rewards_program_daily_cron');
        }
    }
}
