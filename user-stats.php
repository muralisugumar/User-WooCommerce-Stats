<?php
/*
Plugin Name: User & WooCommerce Stats
Description: Displays user statistics and WooCommerce metrics under Tools menu
Version: 1.3
Author: Murali Sugumar
Author URI: https://muralisugumar.com
*/

if (!defined('ABSPATH')) {
    exit;
}

class User_WooCommerce_Stats {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_tools_submenu'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    public function add_tools_submenu() {
        add_submenu_page(
            'tools.php',
            'User & WooCommerce Stats',
            'User/Woo Stats',
            'manage_options',
            'user-woo-stats',
            array($this, 'display_stats_page')
        );
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'user_woo_stats_widget',
            'User & WooCommerce Statistics',
            array($this, 'display_dashboard_widget')
        );
    }

    public function display_stats_page() {
        ?>
        <div class="wrap">
            <h1>User & WooCommerce Statistics</h1>
            <?php $this->display_user_statistics(); ?>
            <?php if (class_exists('WooCommerce')) $this->display_woocommerce_statistics(); ?>
        </div>
        <?php
    }

    public function display_dashboard_widget() {
        $this->display_user_statistics();
        if (class_exists('WooCommerce')) $this->display_woocommerce_statistics();
    }

    private function display_user_statistics() {
        $user_count = count_users();
        $total_posts = wp_count_posts()->publish;
        $total_comments = wp_count_comments()->approved;

        echo '<div class="user-stats">';
        echo '<h2>User Statistics</h2>';
        echo '<p>Total Users: ' . $user_count['total_users'] . '</p>';
        
        echo '<h3>Users by Role</h3>';
        foreach ($user_count['avail_roles'] as $role => $count) {
            echo '<p>' . ucfirst($role) . ': ' . $count . '</p>';
        }

        echo '<h3>Content Statistics</h3>';
        echo '<p>Total Published Posts: ' . $total_posts . '</p>';
        echo '<p>Approved Comments: ' . $total_comments . '</p>';
        echo '</div>';
    }

    private function display_woocommerce_statistics() {
        global $wpdb;
        
        // Customers with orders (registered users)
        $customers_with_orders = $wpdb->get_var("
            SELECT COUNT(DISTINCT customer_id)
            FROM {$wpdb->prefix}wc_order_stats
            WHERE customer_id != 0
        ");

        // Guest checkout orders
        $guest_orders = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}wc_order_stats
            WHERE customer_id = 0
        ");

        // Total customers (users with 'customer' role)
        $total_customers = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = '{$wpdb->prefix}capabilities'
            AND um.meta_value LIKE '%customer%'
        ");

        // Customers without orders
        $customers_without_orders = max(0, $total_customers - $customers_with_orders);

        // Net sales calculation
        $net_sales = $wpdb->get_var("
            SELECT SUM(net_total)
            FROM {$wpdb->prefix}wc_order_stats
            WHERE status NOT IN ('wc-refunded', 'wc-failed', 'wc-cancelled')
        ");

        // Order status counts
        $order_statuses = wc_get_order_statuses();
        $status_counts = array();
        foreach ($order_statuses as $status => $label) {
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$wpdb->prefix}posts
                WHERE post_type = 'shop_order'
                AND post_status = %s
            ", $status));
            $status_counts[$status] = $count;
        }

        // User roles with orders
        $user_roles_with_orders = $wpdb->get_results("
            SELECT um.meta_value AS role, COUNT(DISTINCT u.ID) AS count
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            INNER JOIN {$wpdb->prefix}wc_order_stats os ON u.ID = os.customer_id
            WHERE um.meta_key = '{$wpdb->prefix}capabilities'
            GROUP BY um.meta_value
        ");

        echo '<div class="woocommerce-stats">';
        echo '<h2>WooCommerce Statistics</h2>';
        
        echo '<h3>Customer Overview</h3>';
        echo '<p>Registered Customers with Orders: ' . $customers_with_orders . '</p>';
        echo '<p>Registered Customers without Orders: ' . $customers_without_orders . '</p>';
        echo '<p>Guest Checkouts: ' . $guest_orders . '</p>';
        
        echo '<h3>Sales Overview</h3>';
        echo '<p>Net Sales: ' . wc_price($net_sales) . '</p>';
        echo '<p>Total Orders: ' . array_sum($status_counts) . '</p>';

        echo '<h3>Order Status Distribution</h3>';
        foreach ($status_counts as $status => $count) {
            $label = $order_statuses[$status];
            echo '<p>' . $label . ': ' . $count . '</p>';
        }

        echo '<h3>User Roles with Orders</h3>';
        if (!empty($user_roles_with_orders)) {
            foreach ($user_roles_with_orders as $role) {
                $roles = maybe_unserialize($role->role);
                $role_name = key($roles);
                echo '<p>' . ucfirst($role_name) . ': ' . $role->count . '</p>';
            }
        } else {
            echo '<p>No orders found with registered user roles</p>';
        }
        echo '</div>';
    }
}

new User_WooCommerce_Stats();