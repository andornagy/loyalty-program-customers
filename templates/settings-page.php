<div class="wrap">
    <h1>Rewards Program CSV Downloader</h1>

    <?php if (get_option('gmail_access_token')):

        $loggedInEmail = get_option('gmail_logged_in_email');

    ?>
        <p>You are authenticated with Gmail as: <strong> <?= $loggedInEmail ?? 'unknown' ?></strong></p>
        <p><strong>Last Check:</strong> <?php echo esc_html(get_option('gmail_last_check')); ?></p>
        <p><strong>Last File Downloaded:</strong> <?php echo esc_html(get_option('gmail_last_file')); ?></p>

        <h2>Customer Sync Summary</h2>
        <p><strong>Customers Added:</strong> <?php echo esc_html(get_option('rewards_program_added_count', 0)); ?></p>
        <p><strong>Customers Updated:</strong> <?php echo esc_html(get_option('rewards_program_updated_count', 0)); ?></p>
        <p><strong>Customers Deleted:</strong> <?php echo esc_html(get_option('rewards_program_deleted_count', 0)); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=gmail_check_email')); ?>">
            <?php submit_button('Check for New CSV'); ?>
        </form>
    <?php else: ?>
        <p>To begin, please authenticate with your Google account.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=gmail_authenticate')); ?>">
            <?php submit_button('Authenticate with Google'); ?>
        </form>
    <?php endif; ?>
</div>