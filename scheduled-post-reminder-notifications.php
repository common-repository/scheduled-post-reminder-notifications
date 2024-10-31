<?php
/*
Plugin Name: Scheduled Post Reminder Notifications
Description: Sends reminders for scheduled posts via email or dashboard notifications.
Version: 0.1
Author: The 215 Guys
Author URI: https://www.the215guys.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add settings page
function sprn_add_settings_page() {
    add_options_page('Scheduled Post Reminder Notifications', 'Scheduled Post Reminder Notifications', 'manage_options', 'sprn-settings', 'sprn_render_settings_page');
}
add_action('admin_menu', 'sprn_add_settings_page');

// Render settings page
function sprn_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Scheduled Post Reminder Notifications Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('sprn_settings_group');
            do_settings_sections('sprn-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
function sprn_register_settings() {
    register_setting('sprn_settings_group', 'sprn_notification_method');
    register_setting('sprn_settings_group', 'sprn_reminder_time');

    add_settings_section('sprn_settings_section', 'Notification Settings', null, 'sprn-settings');

    add_settings_field('sprn_notification_method', 'Notification Method', 'sprn_notification_method_callback', 'sprn-settings', 'sprn_settings_section');
    add_settings_field('sprn_reminder_time', 'Reminder Time (in minutes) before post is published', 'sprn_reminder_time_callback', 'sprn-settings', 'sprn_settings_section');
}
add_action('admin_init', 'sprn_register_settings');

function sprn_notification_method_callback() {
    $method = get_option('sprn_notification_method', 'email');
    echo '<select name="sprn_notification_method">
            <option value="email"' . selected($method, 'email', false) . '>Admin Email</option>
            <option value="dashboard"' . selected($method, 'dashboard', false) . '>Dashboard</option>
          </select>';
}

function sprn_reminder_time_callback() {
    $time = get_option('sprn_reminder_time', 60);
    echo '<input type="number" name="sprn_reminder_time" value="' . esc_attr($time) . '" />';
}

// Hook into post scheduling
function sprn_schedule_reminder($post_id) {
    $post = get_post($post_id);
    if ($post->post_status == 'future') {
        $reminder_time = get_option('sprn_reminder_time', 60) * 60;
        $reminder_timestamp = strtotime($post->post_date) - $reminder_time;
        wp_schedule_single_event($reminder_timestamp, 'sprn_send_reminder', array($post_id));
    }
}
add_action('wp_insert_post', 'sprn_schedule_reminder');

// Send reminder
function sprn_send_reminder($post_id) {
    $post = get_post($post_id);
    $method = get_option('sprn_notification_method', 'email');
    $message = 'Reminder: Your post "' . $post->post_title . '" is scheduled to be published soon.';

    if ($method == 'email') {
        wp_mail(get_bloginfo('admin_email'), 'Scheduled Post Reminder', $message);
    } else {
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-info"><p>' . esc_html($message) . '</p></div>';
        });
    }
}

// Clear scheduled events on plugin deactivation
function sprn_deactivate() {
    $timestamp = wp_next_scheduled('sprn_send_reminder');
    while ($timestamp) {
        wp_unschedule_event($timestamp, 'sprn_send_reminder');
        $timestamp = wp_next_scheduled('sprn_send_reminder');
    }
}
register_deactivation_hook(__FILE__, 'sprn_deactivate');
