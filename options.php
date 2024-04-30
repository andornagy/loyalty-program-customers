<?php
// Add options page
add_action('admin_menu', 'jldrp_options_page');
function jldrp_options_page()
{
  add_options_page(
    'Rewards Program Settings',
    'Rewards Program',
    'manage_options',
    'jldrp-settings',
    'jldrp_options_page_content'
  );
}

// Options page content
function jldrp_options_page_content()
{
?>
  <div class="wrap">
    <h2>Rewards Program Settings</h2>
    <form method="post" action="options.php">
      <?php settings_fields('jldrp_inbox_settings_group'); ?>
      <?php do_settings_sections('jldrp-settings'); ?>
      <?php submit_button('Save Settings'); ?>
    </form>
    <h3>Debug</h3>
    <?php echo 'Connection to inbox: ' . get_option('jldrp_csv_inbox_connection', false) . '<br/>'; ?>
    <?php echo 'Process running: ' . get_option('jldrp_csv_process_running', false) . '<br/>'; ?>
    <?php echo 'Processed lines: ' . get_option('jldrp_csv_process_offset') . '<br/>'; ?>
    <?php echo 'Last attachment file: ' . get_option('jldrp_last_attachment') . '<br/>'; ?>
  </div>
<?php
}

// Register and initialize settings
add_action('admin_init', 'jldrp_register_settings');
function jldrp_register_settings()
{
  // Inbox settings section
  register_setting('jldrp_inbox_settings_group', 'jldrp_hostname');
  register_setting('jldrp_inbox_settings_group', 'jldrp_username');
  register_setting('jldrp_inbox_settings_group', 'jldrp_password');
  register_setting('jldrp_inbox_settings_group', 'jldrp_batch');

  add_settings_field('jldrp_hostname', 'Hostname', 'jldrp_hostname_callback', 'jldrp-settings', 'jldrp_inbox_settings_section');
  add_settings_field('jldrp_username', 'Username', 'jldrp_username_callback', 'jldrp-settings', 'jldrp_inbox_settings_section');
  add_settings_field('jldrp_password', 'Password', 'jldrp_password_callback', 'jldrp-settings', 'jldrp_inbox_settings_section');
  add_settings_field('jldrp_batch', 'Batch size', 'jldrp_batch_callback', 'jldrp-settings', 'jldrp_inbox_settings_section');


  add_settings_section('jldrp_inbox_settings_section', 'Email Inbox Settings', 'jldrp_inbox_settings_section_callback', 'jldrp-settings');
}

// Callback functions for fields
function jldrp_inbox_settings_section_callback()
{
  echo 'Enter your email inbox settings below:';
}

function jldrp_hostname_callback()
{
  $hostname = get_option('jldrp_hostname');
  echo "<input type='text' name='jldrp_hostname' value='$hostname' />";
  echo "<p class='description' id='hostname-description'>Example: mail.domain.com:993</p>";
}

function jldrp_username_callback()
{
  $username = get_option('jldrp_username');
  echo "<input type='text' name='jldrp_username' value='$username' />";
  echo "<p class='description' id='username-description'>Example: mail@domain.com</p>";
}

function jldrp_password_callback()
{
  $password = get_option('jldrp_password');
  echo "<input type='password' name='jldrp_password' value='$password' />";
  echo "<p class='description' id='password-description'>The inbox password.</p>";
}

function jldrp_batch_callback()
{
  $batch = get_option('jldrp_batch');
  echo "<input type='text' name='jldrp_batch' value='$batch' />";
  echo "<p class='description' id='batch-description'>How many rows to process at once. Example: 500</p>";
}
