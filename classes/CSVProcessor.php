<?php

namespace RewardsProgram;

class CSVProcessor
{
    private $csvFilePath;
    private $addedCount = 0;
    private $updatedCount = 0;
    private $deletedCount = 0;

    public function __construct($csvFilePath)
    {
        $this->csvFilePath = $csvFilePath;
    }

    /**
     * Processes a CSV file to synchronize customer data with the database.
     *
     * This function reads customer data from a CSV file, compares it with existing
     * customer data in the database, and performs the following operations:
     * - Extracts customer numbers from the CSV data.
     * - Identifies and adds new customers not present in the database.
     * - Updates existing customers in the database with CSV data.
     * - Removes customers from the database that are not present in the CSV.
     *
     * It also maintains counts of added, updated, and deleted customers and updates
     * these counts in the WordPress options for reporting purposes.
     *
     * @return void
     */
    public function process_csv()
    {
        $rows = $this->parse_csv();
        if (empty($rows)) return;

        // Extract customer numbers from the CSV data
        $csvCustomerNumbers = array_column($rows, 'customer_number');

        // Use array_flip to convert $csvCustomerNumbers values to keys
        $csvCustomerNumbers = array_flip($csvCustomerNumbers);

        // Get existing customers from the database
        $existingCustomers = $this->get_existing_customers();

        // Use array_diff_key to remove keys from $existingCustomers that are in $array1
        $customersToRemove = array_diff_key($existingCustomers, $csvCustomerNumbers);

        foreach ($rows as $row) {
            $customerNumber = $row['customer_number'];
            if (isset($existingCustomers[$customerNumber])) {
                // Update existing customer
                $this->update_customer($existingCustomers[$customerNumber], $row);
                unset($existingCustomers[$customerNumber]); // Remove from existing list
            } else {
                // Add new customer
                $this->add_customer($row);
            }
        }

        // Remove customers in the database that are not in the CSV
        foreach ($customersToRemove as $customer => $post_id) {
            wp_delete_post($post_id, true);
            clean_post_cache($post_id);
            $this->deletedCount++;
        }

        // Store counts in options for reporting on the settings page
        update_option('rewards_program_added_count', $this->addedCount);
        update_option('rewards_program_updated_count', $this->updatedCount);
        update_option('rewards_program_deleted_count', $this->deletedCount);
    }

    /**
     * Parses a CSV file and returns its data as an array of associative arrays.
     *
     * This method checks if the specified CSV file exists and is readable. It then
     * reads the CSV file line by line, expecting each row to contain values for
     * 'customer_number', 'name', 'city', 'state', and 'points'. If a row matches
     * the expected format, it is converted into an associative array and added to
     * the result set. If a row does not match the expected format, an error is logged.
     *
     * @return array An array of associative arrays representing the CSV data.
     *               Returns an empty array if the file is not readable or does not exist.
     */
    private function parse_csv()
    {
        if (!file_exists($this->csvFilePath) || !is_readable($this->csvFilePath)) return [];

        $data = [];
        $expectedKeys = ['customer_number', 'name', 'city', 'state', 'points'];

        if (($handle = fopen($this->csvFilePath, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($expectedKeys)) {
                    $data[] = array_combine($expectedKeys, $row);
                } else {
                    error_log("CSV row does not match expected format: " . print_r($row, true));
                }
            }
            fclose($handle);
        }
        return $data;
    }

    /**
     * Retrieves existing customers from the database.
     *
     * This method queries the database for posts of type 'customer' and
     * retrieves their IDs and customer numbers. The result is an associative
     * array where the keys are the customer numbers and the values are the
     * corresponding post IDs.
     *
     * @return array A mapping of customer numbers to post IDs.
     */
    private function get_existing_customers()
    {
        $existingCustomers = [];
        $query = new \WP_Query([
            'post_type' => 'customer',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'customer_number',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        foreach ($query->posts as $postId) {
            $customerNumber = get_post_meta($postId, 'customer_number', true);
            $existingCustomers[$customerNumber] = $postId;
        }

        return $existingCustomers;
    }

    /**
     * Adds a new customer to the database.
     *
     * This method inserts a new post of type 'customer' and adds the customer
     * number, city, state, and points as post meta fields. The method also
     * increments the added customer count.
     *
     * @param array $data Associative array of customer data with keys:
     *                    'name', 'customer_number', 'city', 'state', 'points'
     */
    private function add_customer($data)
    {
        $postId = wp_insert_post([
            'post_title' => $data['name'],
            'post_type' => 'customer',
            'post_status' => 'publish'
        ]);

        if ($postId) {
            update_post_meta($postId, 'customer_number', $data['customer_number']);
            update_post_meta($postId, 'city', $data['city']);
            update_post_meta($postId, 'state', $data['state']);
            update_post_meta($postId, 'points', $data['points']);
            $this->addedCount++;
        }
    }

    /**
     * Updates an existing customer in the database.
     *
     * This method checks the existing post and meta values for a customer post
     * and updates them if they differ from the new data provided. It updates the
     * post title and meta fields such as 'city', 'state', and 'points'. If any
     * of these fields are updated, the updated customer count is incremented.
     *
     * @param int $postId The ID of the customer post to be updated.
     * @param array $data Associative array of customer data with keys:
     *                    'name', 'city', 'state', 'points'
     * @return void
     */
    private function update_customer($postId, $data)
    {
        // Get the current post and meta values
        $current_post = get_post($postId);
        $current_meta = get_post_meta($postId);

        $isUpdated = false;

        // Check if post title needs updating
        if ($current_post->post_title !== $data['name']) {
            wp_update_post([
                'ID' => $postId,
                'post_title' => $data['name']
            ]);
            $isUpdated = true;
        }

        // Check if city meta needs updating
        if (isset($current_meta['city'][0]) && $current_meta['city'][0] !== $data['city']) {
            update_post_meta($postId, 'city', $data['city']);
            $isUpdated = true;
        }

        // Check if state meta needs updating
        if (isset($current_meta['state'][0]) && $current_meta['state'][0] !== $data['state']) {
            update_post_meta($postId, 'state', $data['state']);
            $isUpdated = true;
        }

        // Check if points meta needs updating
        if (isset($current_meta['points'][0]) && $current_meta['points'][0] !== $data['points']) {
            update_post_meta($postId, 'points', $data['points']);
            $isUpdated = true;
        }

        // If any meta was updated, update the counter
        if ($isUpdated) {
            $this->updatedCount++;
        }
    }
}
