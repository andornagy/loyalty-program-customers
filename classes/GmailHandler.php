<?php

namespace RewardsProgram;

use Google\Client;
use Google\Service\Gmail;

class GmailHandler
{
    private $client;
    private $service;

    public function __construct()
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        $this->client = new Client();
        $this->client->setAuthConfig(__DIR__ . '/../credentials.json');
        $this->client->setRedirectUri(admin_url('admin-post.php?action=gmail_authenticate'));
        $this->client->setScopes([
            Gmail::GMAIL_READONLY,
            'https://www.googleapis.com/auth/userinfo.email', // Scope to access the user's email address
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        // Retrieve and decode access token
        $accessToken = get_option('gmail_access_token');
        $decodedToken = $accessToken ? json_decode($accessToken, true) : null;

        // Log access token retrieval
        error_log("Access token retrieved: " . print_r($decodedToken, true));

        // Check if we have a valid access token
        if ($decodedToken && isset($decodedToken['access_token'])) {
            $this->client->setAccessToken($decodedToken);
        } else {
            error_log("No valid access token found. Redirecting to authenticate.");
            $this->request_new_token();
            return; // Prevent further execution if no token is available
        }

        // Check if the token is expired
        if ($this->client->isAccessTokenExpired()) {
            error_log("Access token expired, attempting to refresh.");

            $refreshToken = $this->client->getRefreshToken();
            if ($refreshToken) {
                // Attempt to refresh the access token
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                if (!isset($newToken['error'])) {
                    // Update the access token
                    update_option('gmail_access_token', json_encode(array_merge($decodedToken, $newToken)));
                    error_log("Access token refreshed successfully.");
                } else {
                    error_log("Error refreshing token, re-authentication required: " . $newToken['error']);
                    $this->request_new_token();
                    return;
                }
            } else {
                error_log("Refresh token missing, re-authentication required.");
                $this->request_new_token();
                return;
            }
        }

        $this->service = new Gmail($this->client);
    }

    /**
     * Handle authentication callback from Google
     *
     * @return void
     */
    public function authenticate()
    {
        if (!isset($_GET['code'])) {
            $authUrl = $this->client->createAuthUrl();
            wp_redirect($authUrl);
            exit();
        } else {
            $token = $this->client->fetchAccessTokenWithAuthCode($_GET['code']);

            // Handle token errors
            if (isset($token['error'])) {
                error_log("Error in authentication token: " . $token['error']);
                wp_die("Error fetching access token: " . esc_html($token['error']));
            }

            // Retrieve existing token if available to merge refresh token
            $existingToken = json_decode(get_option('gmail_access_token'), true) ?: [];
            if (isset($token['refresh_token'])) {
                // Save the new refresh token
                $existingToken['refresh_token'] = $token['refresh_token'];
                error_log("New refresh token received and saved.");
            }

            // Save the merged token data to include refresh token if missing
            update_option('gmail_access_token', json_encode(array_merge($existingToken, $token)));

            $gmailHandler = new GmailHandler();
            $email = $gmailHandler->get_user_email();

            if ($email) {
                error_log("Logged-in user email: $email");
                update_option('gmail_logged_in_email', $email);
            } else {
                error_log("Unable to retrieve the user's email address.");
            }

            wp_redirect(admin_url('options-general.php?page=rewards-program'));
            exit();
        }
    }

    private function request_new_token()
    {
        // Set a transient to limit redirects to one time per 5 minutes
        if (get_transient('gmail_auth_redirect')) {
            error_log("Redirect already attempted recently, preventing loop.");
            return;
        }

        // Set the transient to avoid further redirects
        set_transient('gmail_auth_redirect', true, 300); // 5 minutes

        $authUrl = $this->client->createAuthUrl();
        error_log("Redirecting to Google authentication URL: $authUrl");
        wp_redirect($authUrl);
        exit();
    }

    public function get_user_email()
    {
        $profile = $this->service->users->getProfile('me');
        if (isset($profile->emailAddress)) {
            return $profile->emailAddress;
        }
        return null; // No email found
    }

    /**
     * Checks for new unread Gmail messages with CSV attachments and processes them.
     *
     * This method fetches the latest unread email with attachments from the user's Gmail account.
     * If a CSV attachment is found, it downloads the file, processes it using a CSVProcessor, and
     * updates the last processed email ID to prevent reprocessing. The storage is limited to the
     * 5 most recent downloaded files.
     *
     * It echoes messages to indicate whether new emails or CSV attachments were found and processed.
     *
     * @return void
     */
    public function check_for_csv()
    {
        $user = 'me';

        // Fetch the ID of the last processed email from the database
        $lastProcessedId = get_option('gmail_last_processed_id', '');

        // Fetch the latest unread email with attachments
        $optParams = [
            'maxResults' => 1,
            'q' => 'has:attachment'
        ];
        $messages = $this->service->users_messages->listUsersMessages($user, $optParams);

        if (empty($messages->getMessages())) {
            echo "No new emails with attachments found.";
            return;
        }

        // Get the first message from the list
        $message = $messages->getMessages()[0];
        $messageId = $message->getId();

        // If this email has already been processed, skip it
        if ($messageId === $lastProcessedId) {
            echo "No new emails to process.";
            return;
        }

        // Fetch message details to check for CSV attachments
        $messageDetail = $this->service->users_messages->get($user, $messageId, ['format' => 'full']);
        $parts = $messageDetail->getPayload()->getParts();

        foreach ($parts as $part) {
            // Check if the part is a CSV file attachment
            if ($part->getFilename() && strtolower(pathinfo($part->getFilename(), PATHINFO_EXTENSION)) === 'csv') {
                $attachmentId = $part->getBody()->getAttachmentId();
                $attachment = $this->service->users_messages_attachments->get($user, $messageId, $attachmentId);
                $data = base64_decode(strtr($attachment->getData(), '-_', '+/'));

                // Save the file with its original name
                $filePath = plugin_dir_path(__FILE__) . '../downloads/' . $part->getFilename();
                file_put_contents($filePath, $data);

                // Limit storage to the 5 most recent files
                $this->limit_downloads_to_recent(5);

                // Process the downloaded CSV
                $csvProcessor = new CSVProcessor($filePath);
                $csvProcessor->process_csv();

                // Update last processed message ID
                update_option('gmail_last_check', current_time('mysql'));
                update_option('gmail_last_file', $part->getFilename());
                update_option('gmail_last_processed_id', $messageId);

                echo "Downloaded and processed CSV attachment: {$part->getFilename()}";
                return; // Stop after processing the first CSV attachment
            }
        }
    }

    // Function to limit the number of saved CSV files to the most recent 5
    private function limit_downloads_to_recent($maxFiles)
    {
        $downloadDir = plugin_dir_path(__FILE__) . '../downloads/';
        $files = glob($downloadDir . '*.csv');

        // Sort files by modified time, descending
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Remove old files if we exceed the max limit
        foreach (array_slice($files, $maxFiles) as $file) {
            if (is_file($file)) {
                unlink($file); // Delete the file
            }
        }
    }
}
