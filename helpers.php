<?php

/**
 * Inspect a value(s)
 *
 * @param mixed $value
 * @return void
 */
function inspect($value)
{
    echo '
<pre>';
    var_dump($value);
    echo '</pre>';
}


/**
 * Inspect a value(s) and die
 *
 * @param mixed $value
 * @return void
 */
function inspectAndDie($value)
{
    echo '
<pre>';
    die(var_dump($value));
    echo '</pre>';
}

// Log messages to WordPress debug log or custom log file
function log_stripe_test($message)
{
    $log_file = WP_CONTENT_DIR . '/gmail-to-csv.log'; // Custom log file
    $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}
