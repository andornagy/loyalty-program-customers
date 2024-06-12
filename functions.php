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
  // $hostname = '{' . get_option('jldrp_hostname') . ':995/pop3/ssl/novalidate-cert}INBOX';
  $username = get_option('jldrp_username');
  $password = get_option('jldrp_password');

  $connect = @imap_open($hostname, $username, $password);

  var_dump($connect);

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
  if (!$attachments) {
    return false;
  }

  // Create directory if it doesn't exist
  if (!file_exists(WP_CONTENT_DIR . '/jldrp-attachments')) {
    mkdir(WP_CONTENT_DIR . '/jldrp-attachments', 0777, true);
  }

  // Code to save attachments...
  foreach ($attachments as $attachment) {
    if ($attachment['is_attachment'] == 1) {
      $filename = $attachment['name'] ?: $attachment['filename'] ?: time() . ".dat";
      $filePath = WP_CONTENT_DIR . '/jldrp-attachments/' . $emailNumber . "-" . $filename;

      // Check file format (CSV)
      $extension = pathinfo($filename, PATHINFO_EXTENSION);
      if (strtolower($extension) === 'csv') {
        // Process CSV attachment
        file_put_contents($filePath, $attachment['attachment']);
      } else {
        echo "Skipping non-CSV attachment: $filename\n";
      }
    }
  }

  update_option('jldrp_last_attachment', $filePath);

  return $filePath;
}

// Function to close the IMAP connection
function jldrp_close_connection($inbox)
{
  return imap_close($inbox);
}

function jldrp_get_local_file_content(string $file_path)
{
  ob_start();
  include $file_path;
  $contents = ob_get_clean();

  return $contents;
}

// Function to add customer data from CSV file
function jldrp_add_customer($attachment = null)
{
  // Assuming the attachment is a CSV file
  $file = fopen($attachment, 'r');

  $addedCustomers = 0;
  $updatedCustomers = 0;

  // Step 1: Retrieve all existing customers' meta data once
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

  // Step 2: Process each line in the CSV
  $new_customers = [];
  $update_meta = [];

  while ($line = fgetcsv($file)) {
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

  fclose($file);

  // Step 3: Bulk insert new customers
  foreach ($new_customers as $customer_data) {
    wp_insert_post($customer_data);
  }

  // Step 4: Bulk update existing customers
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

  update_option('jldrp_csv_process_running', time());
  update_option('jldrp_csv_process_offset', count(fgetcsv($file)));
}

// Add custom interval to WP Cron
// add_filter('cron_schedules', 'jldrp_add_custom_cron_interval');

// function jldrp_add_custom_cron_interval($schedules)
// {
//   $schedules['every_five_minutes'] = array(
//     'interval' => 5 * 60, // 5 minutes in seconds
//     'display' => esc_html__('Every 5 minutes'),
//   );

//   return $schedules;
// }

// Schedule the event with the new custom interval
// add_action('wp', 'jldrp_schedule_csv_processing');

// function jldrp_schedule_csv_processing()
// {
//   if (!wp_next_scheduled('jldrp_process_csv_batch')) {
//     wp_schedule_event(time(), 'every_five_minutes', 'jldrp_process_csv_batch');
//   }
// }
// add_action('jldrp_process_csv_batch', 'jldrp_add_customer');
