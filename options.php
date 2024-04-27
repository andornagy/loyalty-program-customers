<?php
// Add options page
add_action('admin_menu', 'e2p_options_page');
function e2p_options_page()
{
  add_options_page(
    'Email to Customers Settings',
    'Email to Customers',
    'manage_options',
    'e2p-settings',
    'e2p_options_page_content'
  );
}

// Options page content
function e2p_options_page_content()
{
?>
  <div class="wrap">
    <h2>Email to Customers Settings</h2>
    <form method="post" action="options.php">
      <?php settings_fields('e2p_inbox_settings_group'); ?>
      <?php settings_fields('e2p_general_settings_group'); ?>
      <?php do_settings_sections('e2p-settings'); ?>
      <?php submit_button('Save Settings'); ?>
    </form>
    <h3>Debug</h3>
    <?php echo 'Processed lines: ' . get_option('e2p_csv_process_offset') . '<br/>'; ?>
    <?php echo 'name of the last attachment file: ' . get_option('e2p_last_attachment') . '<br/>'; ?>
  </div>
<?php
}

// Register and initialize settings
add_action('admin_init', 'e2p_register_settings');
function e2p_register_settings()
{
  // Inbox settings section
  register_setting('e2p_inbox_settings_group', 'e2p_hostname');
  register_setting('e2p_inbox_settings_group', 'e2p_username');
  register_setting('e2p_inbox_settings_group', 'e2p_password');

  add_settings_field('e2p_hostname', 'Hostname', 'e2p_hostname_callback', 'e2p-settings', 'e2p_inbox_settings_section');
  add_settings_field('e2p_username', 'Username', 'e2p_username_callback', 'e2p-settings', 'e2p_inbox_settings_section');
  add_settings_field('e2p_password', 'Password', 'e2p_password_callback', 'e2p-settings', 'e2p_inbox_settings_section');

  add_settings_section('e2p_inbox_settings_section', 'Email Inbox Settings', 'e2p_inbox_settings_section_callback', 'e2p-settings');


  // General settings section
  register_setting('e2p_general_settings_group', 'e2p_batch');

  add_settings_field('e2p_batch', 'Batch', 'e2p_batch_callback', 'e2p-settings', 'e2p_general_settings_section');

  add_settings_section('e2p_general_settings_section', 'General Settings', 'e2p_general_settings_section_callback', 'e2p-settings');
}

// Callback functions for fields
function e2p_inbox_settings_section_callback()
{
  echo 'Enter your email inbox settings below:';
}

function e2p_hostname_callback()
{
  $hostname = get_option('e2p_hostname');
  echo "<input type='text' name='e2p_hostname' value='$hostname' />";
  echo "<p class='description' id='username-hostname'>Example: mail.domain.com:993</p>";
}

function e2p_username_callback()
{
  $username = get_option('e2p_username');
  echo "<input type='text' name='e2p_username' value='$username' />";
  echo "<p class='description' id='username-description'>Example: mail@domain.com</p>";
}

function e2p_password_callback()
{
  $password = get_option('e2p_password');
  echo "<input type='password' name='e2p_password' value='$password' />";
}

// Callback functions for fields
function e2p_general_settings_section_callback()
{
  echo 'General Settings';
}

function e2p_batch_callback()
{
  $batch = get_option('e2p_batch');
  echo "<input type='text' name='e2p_batch' value='$batch' />";
  echo "<p class='description' id='batch-description'>500</p>";
}
