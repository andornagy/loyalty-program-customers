<?php

// Main function to process emails and create/update posts
function jldrp_new_email_check()
{
  // Connect to the email inbox
  $inbox = jldrp_connect_to_inbox();

  // Check for new emails
  $emails = jldrp_get_new_emails($inbox);
  if (is_wp_error($emails)) return;

  // Process emails and extract attachments
  $attachment = jldrp_process_latest_email($emails, $inbox);

  // Close the connection to the inbox
  jldrp_close_connection($inbox);

  // If an attachment was found, start processing
  if ($attachment) {
    update_option('jldrp_csv_process_running', 'true');
    jldrp_add_customer($attachment);
  }
}


// Function to connect to the email inbox
function jldrp_connect_to_inbox()
{
  $hostname = '{' . get_option('jldrp_hostname') . '/imap/ssl}INBOX';
  $username = get_option('jldrp_username');
  $password = get_option('jldrp_password');

  $connect = imap_open($hostname, $username, $password);

  if (!$connect) {
    update_option('jldrp_csv_inbox_connection', 'Unsuccessful');
  } else {
    update_option('jldrp_csv_inbox_connection', 'Successful');
    return imap_open($hostname, $username, $password);
  }
}

// Function to retrieve new emails
function jldrp_get_new_emails($inbox)
{
  return imap_search($inbox, 'UNSEEN');
}

/**
 * Process the latest email and extract attachments.
 *
 * @param array $emails An array of email numbers to process.
 * @param IMAP\Connection $inbox An IMAP stream resource representing the mailbox.
 * @return bool|int Returns false if no emails are provided or the processing fails, otherwise returns the number of downloaded attachments.
 */
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
      file_put_contents($filePath, $attachment['attachment']);
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

function jldrp_get_local_file_content($file_path)
{
  ob_start();
  include $file_path;
  $contents = ob_get_clean();

  return $contents;
}

// Function to add customer data from CSV file
function jldrp_add_customer($new_attachment = null)
{
  if (!get_option('jldrp_csv_process_running')) return;

  $attachment = $new_attachment ? $new_attachment : get_option('jldrp_last_attachment');

  // If an attachment was found, add customer data
  if (is_wp_error($attachment)) return;

  $batch_size = get_option('jldrp_batch') ? get_option('jldrp_batch') : 100; // Number of records to process at a time

  $offset = get_option('jldrp_csv_process_offset', 0);

  $csv = jldrp_get_local_file_content($attachment);

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

  update_option('jldrp_csv_process_offset', $offset + $batch_size);

  // If end of file reached, reset offset and remove scheduled event 
  if ($offset + $batch_size >= count($lines)) {
    update_option('jldrp_csv_process_offset', 0);
    $timestamp = wp_next_scheduled('jldrp_process_csv_batch');
    wp_unschedule_event($timestamp, 'jldrp_process_csv_batch');
    update_option('jldrp_csv_process_running', 'false');
  }
}

// Add custom interval to WP Cron
add_filter('cron_schedules', 'jldrp_add_custom_cron_interval');

function jldrp_add_custom_cron_interval($schedules)
{
  $schedules['every_five_minutes'] = array(
    'interval' => 5 * 60, // 5 minutes in seconds
    'display' => esc_html__('Every 5 minutes'),
  );

  return $schedules;
}

// Schedule the event with the new custom interval
add_action('wp', 'jldrp_schedule_csv_processing');

function jldrp_schedule_csv_processing()
{
  if (!wp_next_scheduled('jldrp_process_csv_batch')) {
    wp_schedule_event(time(), 'every_five_minutes', 'jldrp_process_csv_batch');
  }
}
add_action('jldrp_process_csv_batch', 'jldrp_add_customer');
