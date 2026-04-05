<?php
/**
 * Plugin Name: Network Site Stats
 * Plugin URI:  https://example.com/network-site-stats
 * Description: Adds a network admin dashboard page that lists each site with post count and storage usage or latest post date.
 * Version:     1.0.0
 * Author:      Codex
 * License:     GPL-2.0-or-later
 * Text Domain: network-site-stats
 * Network:     true
 */

if (! defined('ABSPATH')) {
    exit;
}

final class Network_Site_Stats_Plugin
{
    public function __construct()
    {
        add_action('network_admin_menu', [$this, 'register_menu']);
    }

    public function register_menu()
    {
        add_menu_page(
            __('Network Site Stats', 'network-site-stats'),
            __('Site Stats', 'network-site-stats'),
            'manage_network',
            'network-site-stats',
            [$this, 'render_page'],
            'dashicons-chart-bar',
            30
        );
    }

    public function render_page()
    {
        if (! current_user_can('manage_network')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'network-site-stats'));
        }

        if (! is_multisite()) {
            echo '<div class="notice notice-error"><p>' .
                esc_html__('This plugin requires WordPress Multisite.', 'network-site-stats') .
                '</p></div>';
            return;
        }

        $sites = get_sites([
            'number'   => 0,
            'deleted'  => 0,
            'archived' => 0,
            'spam'     => 0,
        ]);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Network Site Stats', 'network-site-stats') . '</h1>';
        echo '<p>' . esc_html__('Quick overview of all sites in the network for the Super Admin.', 'network-site-stats') . '</p>';

        if (empty($sites)) {
            echo '<p>' . esc_html__('No sites found in this network.', 'network-site-stats') . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Site ID', 'network-site-stats') . '</th>';
        echo '<th>' . esc_html__('Blog Name', 'network-site-stats') . '</th>';
        echo '<th>' . esc_html__('URL', 'network-site-stats') . '</th>';
        echo '<th>' . esc_html__('Post Count', 'network-site-stats') . '</th>';
        echo '<th>' . esc_html__('Storage Used / Latest Post', 'network-site-stats') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($sites as $site) {
            $row = $this->build_site_row($site);

            echo '<tr>';
            echo '<td>' . esc_html((string) $row['id']) . '</td>';
            echo '<td><strong>' . esc_html($row['name']) . '</strong></td>';
            echo '<td><a href="' . esc_url($row['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($row['url']) . '</a></td>';
            echo '<td>' . esc_html((string) $row['post_count']) . '</td>';
            echo '<td>' . esc_html($row['extra']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function build_site_row(WP_Site $site)
    {
        switch_to_blog((int) $site->blog_id);

        $name = get_option('blogname');
        $url = home_url('/');
        $post_count = $this->get_post_count();
        $extra = $this->get_storage_or_latest_post();

        restore_current_blog();

        return [
            'id'         => (int) $site->blog_id,
            'name'       => $name ?: __('(No title)', 'network-site-stats'),
            'url'        => $url,
            'post_count' => $post_count,
            'extra'      => $extra,
        ];
    }

    private function get_post_count()
    {
        $counts = wp_count_posts('post');

        if (! $counts) {
            return 0;
        }

        $total = 0;

        foreach ((array) $counts as $status => $count) {
            if (in_array($status, ['auto-draft', 'trash', 'inherit'], true)) {
                continue;
            }

            $total += (int) $count;
        }

        return $total;
    }

    private function get_storage_or_latest_post()
    {
        if (function_exists('get_space_used')) {
            $space_used_mb = (int) get_space_used();

            if ($space_used_mb > 0) {
                return sprintf(
                    __('Storage: %s', 'network-site-stats'),
                    size_format($space_used_mb * MB_IN_BYTES, 2)
                );
            }
        }

        $latest_posts = get_posts([
            'post_type'        => 'post',
            'post_status'      => 'publish',
            'numberposts'      => 1,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ]);

        if (empty($latest_posts)) {
            return __('No published posts yet.', 'network-site-stats');
        }

        $latest_post = $latest_posts[0];

        return sprintf(
            __('Latest: %1$s (%2$s)', 'network-site-stats'),
            wp_strip_all_tags(get_the_title($latest_post)),
            get_the_date('d/m/Y H:i', $latest_post)
        );
    }
}

new Network_Site_Stats_Plugin();
