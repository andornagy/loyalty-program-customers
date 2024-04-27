<?php

// Add custom interval to WP Cron

add_filter('cron_schedules', 'add_custom_cron_interval');

function add_custom_cron_interval($schedules)
{

  $schedules['every_five_minutes'] = array(

    'interval' => 5 * 60, // 5 minutes in seconds

    'display' => esc_html__('Every Five Minutes'),

  );

  return $schedules;
}

// Schedule the event with the new custom interval

add_action('wp', 'schedule_csv_processing');

function schedule_csv_processing()
{

  if (!wp_next_scheduled('process_csv_batch')) {

    wp_schedule_event(time(), 'every_five_minutes', 'process_csv_batch');
  }
}

add_action('process_csv_batch', 'process_csv_in_batches');

function process_csv_in_batches()
{

  $batch_size = 400; // Number of records to process at a time

  $offset = get_option('csv_process_offset', 0);

  $csv_url = 'https://location.com/file.csv';

  $response = wp_remote_get($csv_url);

  if (is_wp_error($response)) return;

  $csv = wp_remote_retrieve_body($response);

  $lines = explode("\n", $csv);

  array_shift($lines); // Remove header

  for ($i = $offset; $i < min($offset + $batch_size, count($lines)); $i++) {
    $data = str_getcsv($lines[$i]);
    if (!empty($data)) {
      $order_id = $data[0];
      $date_completed = $data[2];
      update_order_date_completed($order_id, $date_completed);
    }
  }
  update_option('csv_process_offset', $offset + $batch_size); // If end of file reached, reset offset and remove scheduled event if ($offset + $batch_size>= count($lines)) {

  update_option('csv_process_offset', 0);

  $timestamp = wp_next_scheduled('process_csv_batch');

  wp_unschedule_event($timestamp, 'process_csv_batch');
}
