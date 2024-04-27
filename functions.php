<?php

// Main function to process emails and create/update posts
function e2p_new_email_check()
{
  // Connect to the email inbox
  $inbox = e2p_connect_to_inbox();

  // Check for new emails
  $emails = e2p_get_new_emails($inbox);
  if (is_wp_error($emails)) return;

  // Process emails and extract attachments
  $attachment = e2p_process_emails($emails, $inbox);

  // Close the connection to the inbox
  e2p_close_connection($inbox);

  // If an attachment was found, start processing
  if ($attachment) {
    e2p_add_customer($attachment);
  }
}


// Function to connect to the email inbox
function e2p_connect_to_inbox()
{
  $hostname = '{' . get_option('e2p_hostname') . '/imap/ssl}INBOX';
  $username = get_option('e2p_username');
  $password = get_option('e2p_password');
  return imap_open($hostname, $username, $password);
}

// Function to retrieve new emails
function e2p_get_new_emails($inbox)
{
  return imap_search($inbox, 'UNSEEN');
}

// Function to process emails and extract attachments
function e2p_process_emails($emails, $inbox)
{
  if (!$emails) {
    return false;
  }

  // Limit the number of emails to process
  $maxEmails = 1;

  rsort($emails); // Sort emails with newest on top

  foreach ($emails as $emailNumber) {

    $structure = imap_fetchstructure($inbox, $emailNumber);
    $attachments = e2p_extract_attachments($structure, $inbox, $emailNumber);

    // Save attachments to disk
    $downloaded = e2p_save_attachments($attachments, $emailNumber);

    // Break loop if maximum number of emails processed
    if (--$maxEmails <= 0) {
      break;
    }
  }

  return $downloaded;
}

// Function to extract attachments from emails
function e2p_extract_attachments($structure, $inbox, $emailNumber)
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
function e2p_save_attachments($attachments, $emailNumber)
{
  if (!$attachments) {
    return false;
  }

  // Create directory if it doesn't exist
  if (!file_exists(WP_CONTENT_DIR . '/e2p-attachments')) {
    mkdir(WP_CONTENT_DIR . '/e2p-attachments', 0777, true);
  }

  // Code to save attachments...
  foreach ($attachments as $attachment) {
    if ($attachment['is_attachment'] == 1) {
      $filename = $attachment['name'] ?: $attachment['filename'] ?: time() . ".dat";
      $filePath = WP_CONTENT_DIR . '/e2p-attachments/' . $emailNumber . "-" . $filename;
      file_put_contents($filePath, $attachment['attachment']);
    }
  }

  update_option('e2p_last_attachment', $filePath);

  return $filePath;
}

// Function to close the IMAP connection
function e2p_close_connection($inbox)
{
  return imap_close($inbox);
}

// Function to add customer data from CSV file
function e2p_add_customer($new_attachment = null)
{
  $attachment = $new_attachment ? $new_attachment : get_option('e2p_last_attachment');

  // If an attachment was found, add customer data
  if (is_wp_error($attachment)) return;

  $batch_size = get_option('e2p_batch') ? get_option('e2p_batch') : 100; // Number of records to process at a time

  $offset = get_option('e2p_csv_process_offset', 0);

  $csv = e2p_get_local_file_content($attachment);

  if (is_wp_error($csv)) return;

  $lines = explode("\n", $csv);

  for ($i = $offset; $i < min($offset + $batch_size, count($lines)); $i++) {

    $data = str_getcsv($lines[$i]);

    list($customer_number, $name, $city, $state, $points) = $data;

    $customer_query = new WP_Query(array(
      'post_type' => 'customers',
      'meta_query' => array(
        array(
          'key' => 'customer_number',
          'value' => $customer_number,
          'compare' => '=',
        )
      )
    ));

    if ($customer_query->have_posts()) {
      while ($customer_query->have_posts()) {
        $customer_query->the_post();
        $post_id = get_the_ID();

        $existing_city = get_post_meta($post_id, 'city', true);
        $existing_state = get_post_meta($post_id, 'state', true);
        $existing_points = get_post_meta($post_id, 'customer_points', true);

        if ($existing_city != $city) {
          update_post_meta($post_id, 'city', sanitize_text_field($city));
        }

        if ($existing_state != $state) {
          update_post_meta($post_id, 'state', sanitize_text_field($state));
        }

        if ($existing_points != $points) {
          update_post_meta($post_id, 'customer_points', $points);
        }
      }
    } else {
      $new_post_args = array(
        'post_type' => 'customers',
        'post_title' => sanitize_text_field($name),
        'post_content' => '',
        'post_status' => 'publish',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
      );
      $new_post_id = wp_insert_post($new_post_args);

      update_post_meta($new_post_id, 'customer_number', sanitize_text_field($customer_number));
      update_post_meta($new_post_id, 'city', sanitize_text_field($city));
      update_post_meta($new_post_id, 'state', sanitize_text_field($state));
      update_post_meta($new_post_id, 'customer_points', $points);
    }
  }

  update_option('e2p_csv_process_offset', $offset + $batch_size);
  // If end of file reached, reset offset and remove scheduled event 

  if ($offset + $batch_size >= count($lines)) {
    update_option('e2p_csv_process_offset', 0);
    $timestamp = wp_next_scheduled('e2p_process_csv_batch');
    wp_unschedule_event($timestamp, 'e2p_process_csv_batch');
  }
}


function e2p_get_local_file_content($file_path)
{
  ob_start();
  include $file_path;
  $contents = ob_get_clean();

  return $contents;
}

// Add custom interval to WP Cron
add_filter('cron_schedules', 'e2p_add_custom_cron_interval');

function e2p_add_custom_cron_interval($schedules)
{
  $schedules['every_five_minutes'] = array(
    'interval' => 5 * 60, // 5 minutes in seconds
    'display' => esc_html__('Every 5 minutes'),
  );

  return $schedules;
}

// Schedule the event with the new custom interval
add_action('wp', 'e2p_schedule_csv_processing');

function e2p_schedule_csv_processing()
{
  if (!wp_next_scheduled('e2p_process_csv_batch')) {
    wp_schedule_event(time(), 'every_five_minutes', 'e2p_process_csv_batch');
  }
}
add_action('e2p_process_csv_batch', 'e2p_process_csv_in_batches');
