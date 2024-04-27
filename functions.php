<?php

// Main function to process emails and create/update posts
function e2p_email_to_post_process_emails()
{
  // Log the start of email processing
  echo "Email processing started. <br/>";

  // Connect to the email inbox
  $inbox = e2p_connect_to_inbox();

  // Check for new emails
  $emails = e2p_get_new_emails($inbox);
  if (empty($emails)) {
    echo "No new emails found. <br/>";
  }

  // Process emails and extract attachments
  $attachment = e2p_process_emails($emails, $inbox);

  // Close the connection to the inbox
  e2p_close_connection($inbox);

  // If an attachment was found, add customer data
  if ($attachment) {
    e2p_add_customer($attachment);
  }

  // Log the completion of email processing
  echo "Email processing completed. <br/>";
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
  return imap_search($inbox, 'ALL');
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

  return $filePath;
}

// Function to close the IMAP connection
function e2p_close_connection($inbox)
{
  return imap_close($inbox);
}

// Function to add customer data from CSV file
function e2p_add_customer($csv_file)
{
  if (($handle = fopen($csv_file, "r")) !== FALSE) {

    while (($data = fgetcsv($handle, 1000, ",", "\"")) !== FALSE) {

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

          $city_updated = false;
          if ($existing_city != $city) {
            update_post_meta($post_id, 'city', sanitize_text_field($city));
            $city_updated = true;
          }

          $state_updated = false;
          if ($existing_state != $state) {
            update_post_meta($post_id, 'state', sanitize_text_field($state));
            $state_updated = true;
          }

          $points_updated = false;
          if ($existing_points != $points) {
            update_post_meta($post_id, 'customer_points', $points);

            $points_updated = true;
          }

          // Log customer update event
          if ($city_updated || $state_updated || $points_updated) {
            echo "Customer with number $customer_number updated. <br/>";
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

        // Log new customer creation event
        echo "New customer created with ID $new_post_id and customer number $customer_number <br/>";
      }
    }
    fclose($handle);
  } else {
    // Log error if unable to open CSV file
    echo "Error: Unable to open CSV file. <br/>";
  }
}
