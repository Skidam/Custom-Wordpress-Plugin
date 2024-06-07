<?php
/*
Plugin Name: Post Type Search
Plugin URI: hhttps://github.com/Skidam/Custom-Wordpress-Plugin
Description: This plugin helps you display values to your user as a response to theier input , it checks if thier input is availale in the WP Table Plugin, it Available it output all the row of the matched valu.
Author: Skidam
Version: 1.0.0
Author URI: https://github.com/Skidam/
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Hook into the CF7 form submission
add_action('wpcf7_submit', 'handle_cf7_submission');

// Function to handle CF7 form submission
function handle_cf7_submission($contact_form) {
    $submission = WPCF7_Submission::get_instance(); // Get the form submission instance
    if ($submission) {
        $posted_data = $submission->get_posted_data(); // Get the posted data from the form
        set_transient('cf7_last_submission', $posted_data, 60 * 60); // Store data in transient for 1 hour
    }
}

// Handle AJAX request to fetch the last submission
add_action('wp_ajax_get_last_submission', 'get_last_submission');
add_action('wp_ajax_nopriv_get_last_submission', 'get_last_submission');

// Function to get the last submission data
function get_last_submission() {
    global $wpdb;
    $myCounty = false;
    $submission_data = get_transient('cf7_last_submission'); // Retrieve the stored form data

    if ($submission_data) {
        // Loop through the submitted data
        foreach ($submission_data as $key => $value) {
            $user_input = strtolower(trim($value)); // Convert user input to lowercase and trim whitespace

            // Query to get all posts from WP_Table (stored in wp_posts table)
            $table_name = $wpdb->prefix . 'posts';
            $query = "SELECT ID, post_content FROM $table_name WHERE post_type = 'tablepress_table' AND post_status = 'publish'";
            $results = $wpdb->get_results($query); // Execute the query

            // Loop through the results (posts)
            foreach ($results as $row) {
                $post_content = $row->post_content; // Get the post content
                $data = json_decode($post_content, true); // Decode the JSON data

                if (json_last_error() === JSON_ERROR_NONE) {
                    // Ensure headers are lowercase and trimmed
                    $headers = array_map('strtolower', array_map('trim', $data[0]));
                    // Get the index of the relevant columns
                    $county_full_index = array_search('county_full', $headers);
                    $total_territories_available_index = array_search('total territories available', $headers);
                    $territories_taken_index = array_search('terriorties taken', $headers); // Note typo in 'terriorties'
                    $territories_remaining_index = array_search('territories remaining', $headers);

                    // Check if county_full header is found
                    if ($county_full_index !== false) {
                        // Loop through the data rows
                        foreach (array_slice($data, 1) as $data_row) {
                            // Check if the county name matches the user input
                            if (strtolower(trim($data_row[$county_full_index])) === $user_input) {
                                $myCounty = true;
                                // Store the matched data
                                $county_full = $data_row[$county_full_index];
                                $total_territories_available = $data_row[$total_territories_available_index];
                                $territories_taken = $data_row[$territories_taken_index];
                                $territories_remaining = $data_row[$territories_remaining_index];
                                break 2; // Break both foreach loops
                            }
                        }
                    }
                }
            }
        }

        // Output the result based on whether the county was found
        if ($myCounty) {
            $output = '<h3>County FOUND !!!</h3>';
            $output .= '<p>County: ' . esc_html($county_full) . '</p>';
            $output .= '<p>Total Territories Available: ' . esc_html($total_territories_available) . '</p>';
            $output .= '<p>Territories Taken: ' . esc_html($territories_taken) . '</p>';
            $output .= '<p>Territories Remaining: ' . esc_html($territories_remaining) . '</p>';
        } else {
            $output = '<h3>County NOT FOUND !!!</h3>';
        }

        echo $output; // Display the output
    } else {
        echo '<p>No recent submissions found.</p>'; // Display message if no submission data
    }
    wp_die(); // Terminate immediately and return a proper response
}

// Function to get county_full values from the database
function get_county_full_values() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'posts';
    $query = "SELECT post_content FROM $table_name WHERE post_type = 'tablepress_table' AND post_status = 'publish'";
    $results = $wpdb->get_results($query);
    $county_full_values = [];

    foreach ($results as $row) {
        $post_content = $row->post_content;
        $data = json_decode($post_content, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $headers = array_map('strtolower', array_map('trim', $data[0]));
            $county_full_index = array_search('county_full', $headers);

            if ($county_full_index !== false) {
                foreach (array_slice($data, 1) as $data_row) {
                    $county_full_values[] = $data_row[$county_full_index];
                }
            }
        }
    }

    return $county_full_values;
}

// Enqueue JavaScript for AJAX handling and autocomplete
function enqueue_cf7_submission_scripts() {
    $county_full_values = get_county_full_values(); // Get county_full values
    wp_enqueue_script('cf7-submission-script', plugin_dir_url(__FILE__) . 'cf7-submission.js', array('jquery'), null, true);
    wp_localize_script('cf7-submission-script', 'cf7Submission', array(
        'ajax_url' => admin_url('admin-ajax.php'), // Localize the script with the AJAX URL
        'county_full_values' => $county_full_values // Pass county_full values to JavaScript
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_cf7_submission_scripts');

?>
