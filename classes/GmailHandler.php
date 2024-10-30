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
        $this->client->setScopes(Gmail::GMAIL_READONLY);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        $accessToken = json_decode(get_option('gmail_access_token'), true);
        $this->client->setAccessToken($accessToken);

        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            update_option('gmail_access_token', json_encode($this->client->getAccessToken()));
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
            update_option('gmail_access_token', json_encode($token));
            wp_redirect(admin_url('options-general.php?page=rewards-program'));
            exit();
        }
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
