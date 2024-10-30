<?php

namespace RewardsProgram;

class PluginSettings
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_gmail_authenticate', [$this, 'authenticate_gmail']);
        add_action('admin_post_gmail_check_email', [$this, 'check_and_download_csv']);
        add_action('init', [$this, 'register_custom_post_type']);
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
    public function check_and_download_csv()
    {
        $gmailHandler = new GmailHandler();
        $gmailHandler->check_for_csv();
        wp_redirect(admin_url('options-general.php?page=rewards-program'));
        exit;
    }

    /**
     * Registers a custom post type for customers.
     *
     * This method registers a custom post type named "customer" with a few basic
     * settings. It makes the post type publicly accessible, allows it to be
     * queried by url, and enables the title field in the WordPress editor.
     *
     * @return void
     */
    public function register_custom_post_type()
    {
        register_post_type('customer', [
            'labels' => [
                'name' => __('Customers'),
                'singular_name' => __('Customer'),
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title'],
            'show_in_rest' => true,
        ]);
    }
}
