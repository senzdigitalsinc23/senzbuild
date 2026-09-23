<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Translation messages for English locale.
 */
return [
    // Common
    'yes'             => 'Yes',
    'no'              => 'No',
    'save'            => 'Save',
    'cancel'          => 'Cancel',
    'delete'          => 'Delete',
    'edit'            => 'Edit',
    'create'          => 'Create',
    'update'          => 'Update',
    'search'          => 'Search',
    'loading'         => 'Loading...',
    'success'         => 'Operation successful',
    'error'           => 'An error occurred',
    'warning'         => 'Warning',
    'information'     => 'Information',

    // Authentication
    'login'           => 'Login',
    'logout'          => 'Logout',
    'register'        => 'Register',
    'email'           => 'Email Address',
    'password'        => 'Password',
    'remember_me'     => 'Remember Me',
    'forgot_password' => 'Forgot Password?',
    'reset_password'  => 'Reset Password',
    'confirm_password'=> 'Confirm Password',
    'name'            => 'Full Name',
    'role'            => 'Role',

    // Validation
    'validation.required'    => 'The :attribute field is required.',
    'validation.email'       => 'The :attribute must be a valid email address.',
    'validation.min'         => 'The :attribute must be at least :min characters.',
    'validation.max'         => 'The :attribute may not be greater than :max characters.',
    'validation.unique'      => 'The :attribute has already been taken.',
    'validation.confirmed'   => 'The :attribute confirmation does not match.',
    'validation.password'    => 'Password must be at least 8 characters with uppercase, lowercase, and number.',
    'validation.integer'     => 'The :attribute must be an integer.',
    'validation.url'         => 'The :attribute must be a valid URL.',

    // Pagination
    'pagination.previous'   => '&laquo; Previous',
    'pagination.next'       => 'Next &raquo;',
    'pagination.showing'    => 'Showing :first to :last of :total results',

    // Time
    'time.just_now'    => 'Just now',
    'time.minutes_ago' => ':count minute ago|:count minutes ago',
    'time.hours_ago'   => ':count hour ago|:count hours ago',
    'time.days_ago'    => ':count day ago|:count days ago',
    'time.weeks_ago'   => ':count week ago|:count weeks ago',

    // Notifications
    'notification.sent'       => 'Notification sent successfully',
    'notification.failed'     => 'Failed to send notification',
    'notification Channels'   => ['mail' => 'Email', 'sms' => 'SMS', 'slack' => 'Slack', 'db' => 'In-App'],

    // API
    'api.unauthorized' => 'Unauthorized access',
    'api.forbidden'    => 'You do not have permission to access this resource',
    'api.not_found'    => 'The requested resource was not found',
    'api.server_error' => 'An internal server error occurred',
    'api.rate_limited' => 'Too many requests. Please try again later.',

    // Users
    'user.created'    => 'User created successfully',
    'user.updated'    => 'User updated successfully',
    'user.deleted'    => 'User deleted successfully',
    'user.profile'    => 'Profile',
    'user.settings'   => 'Settings',

    // Errors
    'error.400' => 'Bad Request',
    'error.401' => 'Unauthorized',
    'error.403' => 'Forbidden',
    'error.404' => 'Not Found',
    'error.419' => 'Page Expired',
    'error.429' => 'Too Many Requests',
    'error.500' => 'Internal Server Error',
    'error.503' => 'Service Unavailable',
];
