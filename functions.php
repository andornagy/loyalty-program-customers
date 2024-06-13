<?php
// Main function to process emails and create/update posts
function jldrp_new_email_check()
{
  // Connect to the email inbox
  $inbox = jldrp_connect_to_inbox();
  if (!$inbox) return;

  // Check for new emails
  $emails = jldrp_get_new_emails($inbox);
  if (!$emails) {
    jldrp_close_connection($inbox); // Close connection if no emails found
    return;
  }

  // Process emails and extract attachments
  $attachment = jldrp_process_latest_email($emails, $inbox);

  // Close the connection to the inbox
  jldrp_close_connection($inbox);

  // If an attachment was found, start processing
  if ($attachment) {
    jldrp_add_customer($attachment);
  }
}


// Function to connect to the email inbox
function jldrp_connect_to_inbox()
{
  $hostname = '{' . get_option('jldrp_hostname') . ':993/imap/ssl/novalidate-cert}INBOX';
  $username = get_option('jldrp_username');
  $password = get_option('jldrp_password');

  $connect = @imap_open($hostname, $username, $password);

  // var_dump($connect);

  if (!$connect) {
    $errors = @imap_errors();
    if ($errors) {
      update_option('jldrp_inbox_connection_status', 'Login failed: ' . implode('; ', $errors));
    }
    echo 'Login failed: ' . implode('; ', $errors);
  } else {
    update_option('jldrp_inbox_connection_status', 'Successfully connected');
    return @imap_open($hostname, $username, $password);
  }
}

// Function to close the IMAP connection
function jldrp_close_connection($inbox)
{
  return imap_close($inbox);
}

// Function to retrieve new emails
function jldrp_get_new_emails($inbox)
{ {
    // Attempt to search for new emails
    $emails = imap_search($inbox, 'UNSEEN');
    if ($emails === false) {
      echo 'Error searching for new emails: ' . imap_last_error();
      return false; // Return false if search failed
    }
    return $emails; // Return array of email numbers if search successful
  }
}

// Process the latest email and extract attachments.
function jldrp_process_latest_email($emails, $inbox)
{
  // If no emails provided, return false
  if (empty($emails)) {
    return false;
  }

  // Sort emails with newest on top
  rsort($emails);

  // Get the latest email number
  $latestEmailNumber = reset($emails);

  // Fetch email structure
  $structure = imap_fetchstructure($inbox, $latestEmailNumber);

  // Extract attachments from the latest email
  $attachments = jldrp_extract_attachments($structure, $inbox, $latestEmailNumber);

  // Save attachments to disk
  $downloaded = jldrp_save_attachments($attachments, $latestEmailNumber);

  // Return the number of downloaded attachments
  return $downloaded;
}

// Function to extract attachments from emails
function jldrp_extract_attachments($structure, $inbox, $emailNumber)
{
  $attachments = array();

  // Code to extract attachments...  
  if (isset($structure->parts) && count($structure->parts)) {
    for ($i = 0; $i < count($structure->parts); $i++) {
      $attachments[$i] = array(
        'is_attachment' => false,
        'filename' => '',
        'name' => '',
        'attachment' => ''
      );

      if ($structure->parts[$i]->ifdparameters) {
        foreach ($structure->parts[$i]->dparameters as $object) {
          if (strtolower($object->attribute) == 'filename') {
            $attachments[$i]['is_attachment'] = true;
            $attachments[$i]['filename'] = $object->value;
          }
        }
      }

      if ($structure->parts[$i]->ifparameters) {
        foreach ($structure->parts[$i]->parameters as $object) {
          if (strtolower($object->attribute) == 'name') {
            $attachments[$i]['is_attachment'] = true;
            $attachments[$i]['name'] = $object->value;
          }
        }
      }

      if ($attachments[$i]['is_attachment']) {
        $attachments[$i]['attachment'] = imap_fetchbody($inbox, $emailNumber, $i + 1);

        if ($structure->parts[$i]->encoding == 3) {
          $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
        } elseif ($structure->parts[$i]->encoding == 4) {
          $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
        }
      }
    }
  }
  return $attachments;
}

// Function to save attachments to disk
function jldrp_save_attachments($attachments, $emailNumber)
{
  if (empty($attachments)) {
    echo ('No attachments found.');
    return false;
  }

  $attachmentsDirectory = WP_CONTENT_DIR . '/jldrp-attachments';
  if (!file_exists($attachmentsDirectory)) {
    mkdir($attachmentsDirectory, 0777, true);
  }

  $filePath = null;
  foreach ($attachments as $attachment) {
    if (!$attachment['is_attachment']) {
      continue;
    }

    $attachmentName = $attachment['name'] ?: $attachment['filename'];
    echo ("Checking attachment: $attachmentName");
    $isValidReport = strtolower($attachmentName) === 'loyaltyreport.csv'
      && strtolower(pathinfo($attachmentName, PATHINFO_EXTENSION)) === 'csv';
    if (!$isValidReport) {
      continue;
    }

    $filePath = $attachmentsDirectory . '/' . $emailNumber . '-' . $attachmentName;
    file_put_contents($filePath, $attachment['attachment']);
    echo ("Saved attachment to: $filePath");
    break;
  }

  if ($filePath) {
    update_option('jldrp_last_attachment', $filePath);
  } else {
    error_log('No valid attachment found.');
  }

  return $filePath;
}


function jldrp_get_local_file_content(string $file_path)
{
  ob_start();
  include $file_path;
  $contents = ob_get_clean();

  return $contents;
}

// Function to retrieve all existing customers
// Function to retrieve all existing customers
function get_existing_customers()
{
  $existing_customers = [];
  $query = new WP_Query(array(
    'post_type' => 'customers',
    'posts_per_page' => -1,
    'fields' => 'ids'
  ));

  if ($query->have_posts()) {
    foreach ($query->posts as $post_id) {
      $customer_number = get_post_meta($post_id, 'customer_number', true);
      $existing_customers[$customer_number] = array(
        'post_id' => $post_id,
        'name' => get_post_meta($post_id, 'customer_name', true),
        'city' => get_post_meta($post_id, 'city', true),
        'state' => get_post_meta($post_id, 'state', true),
        'points' => get_post_meta($post_id, 'customer_points', true)
      );
    }
  }

  echo "Retrieved " . count($existing_customers) . " existing customers.\n";
  return $existing_customers;
}

// Function to process CSV file and determine actions
function process_csv_file($file, $existing_customers)
{
  $addedCustomers = 0;
  $updatedCustomers = 0;
  $totalCustomers = 0;
  $new_customers = [];
  $update_meta = [];

  while (!feof($file)) {
    $line = fgetcsv($file);

    // Skip lines where the first column isn't a number
    if (!is_numeric($line[0])) {
      echo "Skipping line with non-numeric customer number: " . implode(", ", $line) . "\n";
      continue;
    }

    // Increment total customers count
    $totalCustomers++;

    // Map CSV columns to variables
    $customer_number = $line[0];
    $name = $line[1];
    $city = $line[2];
    $state = $line[3];
    $points = $line[4];

    if (isset($existing_customers[$customer_number])) {
      // Update existing customer data
      $post_id = $existing_customers[$customer_number]['post_id'];
      $updated = false;

      if ($existing_customers[$customer_number]['name'] != $name) {
        $update_meta[] = array('post_id' => $post_id, 'meta_key' => 'customer_name', 'meta_value' => sanitize_text_field($name));
        $updated = true;
      }

      if ($existing_customers[$customer_number]['city'] != $city) {
        $update_meta[] = array('post_id' => $post_id, 'meta_key' => 'city', 'meta_value' => sanitize_text_field($city));
        $updated = true;
      }

      if ($existing_customers[$customer_number]['state'] != $state) {
        $update_meta[] = array('post_id' => $post_id, 'meta_key' => 'state', 'meta_value' => sanitize_text_field($state));
        $updated = true;
      }

      if ($existing_customers[$customer_number]['points'] != $points) {
        $update_meta[] = array('post_id' => $post_id, 'meta_key' => 'customer_points', 'meta_value' => $points);
        $updated = true;
      }

      if ($updated) {
        $updatedCustomers++;
      }

      unset($existing_customers[$customer_number]);
    } else {
      // Collect new customer data for bulk insertion
      $new_customers[] = array(
        'post_title' => sanitize_text_field($name),
        'post_type' => 'customers',
        'post_status' => 'publish',
        'meta_input' => array(
          'customer_number' => sanitize_text_field($customer_number),
          'city' => sanitize_text_field($city),
          'state' => sanitize_text_field($state),
          'customer_points' => $points
        )
      );
      $addedCustomers++;
    }
  }

  echo "Processed CSV file. Total customers: $totalCustomers, New customers: $addedCustomers, Updated customers: $updatedCustomers.\n";
  return [$new_customers, $update_meta, $addedCustomers, $updatedCustomers, $existing_customers, $totalCustomers];
}

// Function to add new customers
function add_new_customers($new_customers)
{
  foreach ($new_customers as $customer_data) {
    wp_insert_post($customer_data);
  }
  echo "Added " . count($new_customers) . " new customers.\n";
}

// Function to update existing customers
function update_existing_customers($update_meta)
{
  global $wpdb;
  foreach ($update_meta as $meta) {
    $wpdb->update(
      $wpdb->postmeta,
      array('meta_value' => $meta['meta_value']),
      array('post_id' => $meta['post_id'], 'meta_key' => $meta['meta_key']),
      array('%s'),
      array('%d', '%s')
    );
  }
  echo "Updated " . count($update_meta) . " customer records.\n";
}

// Function to delete customers not in the CSV
function delete_customers_not_in_csv($existing_customers)
{
  $deletedCustomers = 0;
  foreach ($existing_customers as $customer) {
    wp_delete_post($customer['post_id'], true);
    $deletedCustomers++;
  }
  echo "Deleted $deletedCustomers customers not found in CSV.\n";
  return $deletedCustomers;
}

// Main function to add customer data from CSV file
function jldrp_add_customer($attachment = null)
{
  $file = fopen($attachment, 'r');
  if (!$file) {
    echo "Failed to open file: $attachment\n";
    return;
  }

  $existing_customers = get_existing_customers();

  list($new_customers, $update_meta, $addedCustomers, $updatedCustomers, $remaining_customers, $totalCustomers) = process_csv_file($file, $existing_customers);
  fclose($file);

  add_new_customers($new_customers);
  update_existing_customers($update_meta);
  $deletedCustomers = delete_customers_not_in_csv($remaining_customers);

  $customerData = [
    'total' => $totalCustomers,
    'added' => $addedCustomers,
    'updated' => $updatedCustomers,
    'deleted' => $deletedCustomers
  ];

  update_option('jldrp_csv_process_running', time());
  update_option('jldrp_csv_process_data', $customerData);

  echo "CSV processing complete. Total customers in CSV: $totalCustomers, Added: $addedCustomers, Updated: $updatedCustomers, Deleted: $deletedCustomers.\n";

  // Optionally, return the counts for further processing or logging
  return [
    'total' => $totalCustomers,
    'added' => $addedCustomers,
    'updated' => $updatedCustomers,
    'deleted' => $deletedCustomers
  ];
}
