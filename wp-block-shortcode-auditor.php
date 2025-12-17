<?php
/**
 * Plugin Name: WP Block and Shortcode Auditor
 * Plugin URI: https://github.com/TABARC-Code/wp-block-shortcode-auditor
 * Description: Scans published content for block and shortcode usage, flags unused registered blocks, and highlights shortcodes that no longer have a handler. Read only, because I am not deleting your builder era.
 * Version: 1.0.0.9
 * Author: TABARC-Code
 * Author URI: https://github.com/TABARC-Code
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Why this exists:
 * People keep plugins installed because "something might use it".
 * And then six months later the site is running 43 plugins, the dashboard wheezes, and nobody remembers why.
 *
 * Blocks and shortcodes are the receipts.
 * If a plugin registers blocks or shortcodes and nothing uses them, that is a decision point.
 *
 * This plugin does not uninstall anything. It does not disable anything. It just shows evidence.
 *
 * TODO: add a scan mode for drafts and private posts (optional), because sometimes the junk lives there.
 * TODO: add a per post list view for a specific shortcode or block.
 * FIXME: scanning is best effort. Builders hide things in meta and JSON blobs. I am not doing archaeology today.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_Block_Shortcode_Auditor' ) ) {

    class WP_Block_Shortcode_Auditor {

        private $screen_slug   = 'wp-block-shortcode-auditor';
        private $export_action = 'wbsa_export_json';

        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
            add_action( 'admin_post_' . $this->export_action, array( $this, 'handle_export_json' ) );
            add_action( 'admin_head-plugins.php', array( $this, 'inject_plugin_list_icon_css' ) );
        }

        private function get_brand_icon_url() {
            return plugin_dir_url( __FILE__ ) . '.branding/tabarc-icon.svg';
        }

        public function add_tools_page() {
            add_management_page(
                __( 'Block and Shortcode Auditor', 'wp-block-shortcode-auditor' ),
                __( 'Block Audit', 'wp-block-shortcode-auditor' ),
                'manage_options',
                $this->screen_slug,
                array( $this, 'render_screen' )
            );
        }

        public function render_screen() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-block-shortcode-auditor' ) );
            }

            $audit = $this->run_audit();

            $export_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=' . $this->export_action ),
                'wbsa_export_json'
            );

            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'WP Block and Shortcode Auditor', 'wp-block-shortcode-auditor' ); ?></h1>
                <p>
                    This screen answers the uncomfortable question: what is actually used.
                    Blocks, shortcodes, and the awkward silence where a shortcode exists in content but no plugin handles it anymore.
                </p>
                <p>
                    <a class="button button-secondary" href="<?php echo esc_url( $export_url ); ?>">
                        <?php esc_html_e( 'Export audit as JSON', 'wp-block-shortcode-auditor' ); ?>
                    </a>
                </p>

                <h2><?php esc_html_e( 'Summary', 'wp-block-shortcode-auditor' ); ?></h2>
                <?php $this->render_summary( $audit ); ?>

                <h2><?php esc_html_e( 'Blocks used in published content', 'wp-block-shortcode-auditor' ); ?></h2>
                <p>
                    Counts are based on scanning published post_content for block comments.
                    If a builder stores content in meta instead, it will slip through. That is on the builder, not on me.
                </p>
                <?php $this->render_used_blocks( $audit ); ?>

                <h2><?php esc_html_e( 'Registered blocks not seen in published content', 'wp-block-shortcode-auditor' ); ?></h2>
                <p>
                    These blocks are registered on the site right now, but were not detected in published post_content during the scan.
                    Some of them are editor tools, some are unused, some are ghosts.
                </p>
                <?php $this->render_unused_registered_blocks( $audit ); ?>

                <h2><?php esc_html_e( 'Shortcodes found in content', 'wp-block-shortcode-auditor' ); ?></h2>
                <p>
                    This is a simple shortcode scanner. It looks for patterns like <code>[thing]</code>.
                    If you have shortcodes inside code blocks or templates, I might count those too. Life is messy.
                </p>
                <?php $this->render_shortcodes( $audit ); ?>

                <h2><?php esc_html_e( 'Shortcodes with no handler', 'wp-block-shortcode-auditor' ); ?></h2>
                <p>
                    These shortcodes appear in content but are not registered with WordPress right now.
                    That usually means a plugin was removed or disabled. Or someone typed a shortcode as a joke and forgot.
                </p>
                <?php $this->render_missing_shortcode_handlers( $audit ); ?>

                <p style="font-size:12px;opacity:0.8;margin-top:2em;">
                    <?php esc_html_e( 'Read only audit. If you remove plugins based on this, test on staging and keep backups. Yes, I am repeating myself.', 'wp-block-shortcode-auditor' ); ?>
                </p>
            </div>
            <?php
        }

        public function inject_plugin_list_icon_css() {
            $icon_url = esc_url( $this->get_brand_icon_url() );
            ?>
            <style>
                .wp-list-table.plugins tr[data-slug="wp-block-shortcode-auditor"] .plugin-title strong::before {
                    content: '';
                    display: inline-block;
                    vertical-align: middle;
                    width: 18px;
                    height: 18px;
                    margin-right: 6px;
                    background-image: url('<?php echo $icon_url; ?>');
                    background-repeat: no-repeat;
                    background-size: contain;
                }
            </style>
            <?php
        }

        public function handle_export_json() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'No.' );
            }

            check_admin_referer( 'wbsa_export_json' );

            $audit = $this->run_audit();

            $payload = array(
                'generated_at' => gmdate( 'c' ),
                'site_url'     => site_url(),
                'audit'        => $audit,
            );

            nocache_headers();
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="block-shortcode-audit.json"' );

            echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
            exit;
        }

        private function run_audit() {
            $post_limit = (int) apply_filters( 'wbsa_scan_post_limit', 800 );
            if ( $post_limit <= 0 ) {
                $post_limit = 800;
            }

            $post_types = (array) apply_filters( 'wbsa_scan_post_types', $this->get_public_content_types() );

            $posts = $this->fetch_posts_for_scan( $post_types, $post_limit );

            $block_counts = array();
            $shortcode_counts = array();

            foreach ( $posts as $p ) {
                $content = isset( $p['post_content'] ) ? (string) $p['post_content'] : '';
                if ( $content === '' ) {
                    continue;
                }

                $blocks = $this->extract_block_names_from_content( $content );
                foreach ( $blocks as $b ) {
                    if ( ! isset( $block_counts[ $b ] ) ) {
                        $block_counts[ $b ] = 0;
                    }
                    $block_counts[ $b ]++;
                }

                $shortcodes = $this->extract_shortcodes_from_content( $content );
                foreach ( $shortcodes as $s ) {
                    if ( ! isset( $shortcode_counts[ $s ] ) ) {
                        $shortcode_counts[ $s ] = 0;
                    }
                    $shortcode_counts[ $s ]++;
                }
            }

            arsort( $block_counts );
            arsort( $shortcode_counts );

            $registered_blocks = $this->get_registered_blocks();
            $used_blocks = array_keys( $block_counts );

            $unused_registered = array();
            foreach ( $registered_blocks as $block_name ) {
                if ( ! in_array( $block_name, $used_blocks, true ) ) {
                    $unused_registered[] = $block_name;
                }
            }

            sort( $unused_registered );

            $registered_shortcodes = $this->get_registered_shortcodes();
            $missing_shortcode_handlers = array();
            foreach ( array_keys( $shortcode_counts ) as $tag ) {
                if ( ! isset( $registered_shortcodes[ $tag ] ) ) {
                    $missing_shortcode_handlers[] = $tag;
                }
            }
            sort( $missing_shortcode_handlers );

            return array(
                'scan' => array(
                    'post_types' => array_values( $post_types ),
                    'post_limit' => $post_limit,
                    'posts_scanned' => count( $posts ),
                ),
                'blocks' => array(
                    'used_counts' => $block_counts,
                    'registered_count' => count( $registered_blocks ),
                    'unused_registered' => $unused_registered,
                ),
                'shortcodes' => array(
                    'used_counts' => $shortcode_counts,
                    'registered_count' => count( $registered_shortcodes ),
                    'missing_handlers' => $missing_shortcode_handlers,
                ),
            );
        }

        private function get_public_content_types() {
            $types = get_post_types( array( 'public' => true ), 'names' );
            if ( ! is_array( $types ) ) {
                $types = array( 'post', 'page' );
            }

            $exclude = array(
                'attachment',
                'revision',
                'nav_menu_item',
                'custom_css',
                'customize_changeset',
                'oembed_cache',
                'user_request',
                'wp_template',
                'wp_template_part',
                'wp_navigation',
            );

            $out = array();
            foreach ( $types as $t ) {
                if ( in_array( $t, $exclude, true ) ) {
                    continue;
                }
                $out[] = $t;
            }

            if ( empty( $out ) ) {
                $out = array( 'post', 'page' );
            }

            return $out;
        }

        private function fetch_posts_for_scan( $post_types, $limit ) {
            global $wpdb;

            $post_types = array_values( array_filter( array_map( 'sanitize_key', (array) $post_types ) ) );
            if ( empty( $post_types ) ) {
                $post_types = array( 'post', 'page' );
            }

            $placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

            // I am scanning published content only by default.
            // Drafts are where people dump experiments. I can add that later as an option.
            $sql = $wpdb->prepare(
                "SELECT ID, post_type, post_content
                 FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_type IN ($placeholders)
                 ORDER BY post_date DESC
                 LIMIT %d",
                array_merge( $post_types, array( (int) $limit ) )
            );

            $rows = $wpdb->get_results( $sql, ARRAY_A );
            if ( ! is_array( $rows ) ) {
                $rows = array();
            }

            return $rows;
        }

        private function extract_block_names_from_content( $content ) {
            // Blocks are stored as HTML comments like:
            // <!-- wp:paragraph -->
            // <!-- wp:acf/my-block {"data":...} -->
            // I want the name part after "wp:" up to whitespace or comment end.
            $found = array();

            if ( preg_match_all( '/<!--\s*wp:([a-z0-9_-]+(?:\/[a-z0-9_-]+)?)\b/i', $content, $m ) ) {
                foreach ( (array) $m[1] as $name ) {
                    $name = strtolower( trim( (string) $name ) );
                    if ( $name !== '' ) {
                        $found[] = $name;
                    }
                }
            }

            return $found;
        }

        private function extract_shortcodes_from_content( $content ) {
            // Quick scan for shortcode tags.
            // This is not a full shortcode parser. I want practical counts.
            // Pattern: [tag ...] or [tag] or [tag/]
            $found = array();

            if ( preg_match_all( '/\[(\/?)([a-zA-Z0-9_-]+)(\s[^\]]*)?\]/', $content, $m ) ) {
                foreach ( (array) $m[2] as $tag ) {
                    $tag = strtolower( trim( (string) $tag ) );
                    if ( $tag === '' ) {
                        continue;
                    }
                    if ( in_array( $tag, array( 'if', 'endif' ), true ) ) {
                        continue;
                    }
                    $found[] = $tag;
                }
            }

            return $found;
        }

        private function get_registered_blocks() {
            $names = array();

            if ( class_exists( 'WP_Block_Type_Registry' ) ) {
                $registry = WP_Block_Type_Registry::get_instance();
                $all = $registry->get_all_registered();
                if ( is_array( $all ) ) {
                    foreach ( $all as $name => $obj ) {
                        $names[] = (string) $name;
                    }
                }
            }

            sort( $names );

            return $names;
        }

        private function get_registered_shortcodes() {
            global $shortcode_tags;
            if ( ! is_array( $shortcode_tags ) ) {
                return array();
            }
            return $shortcode_tags;
        }

        private function human_count_rows( $assoc, $limit ) {
            $out = array();
            $i = 0;
            foreach ( (array) $assoc as $k => $v ) {
                $i++;
                if ( $i > $limit ) {
                    break;
                }
                $out[] = array(
                    'name'  => (string) $k,
                    'count' => (int) $v,
                );
            }
            return $out;
        }

        private function render_summary( $audit ) {
            $posts_scanned = isset( $audit['scan']['posts_scanned'] ) ? (int) $audit['scan']['posts_scanned'] : 0;

            $blocks_used = isset( $audit['blocks']['used_counts'] ) && is_array( $audit['blocks']['used_counts'] ) ? count( $audit['blocks']['used_counts'] ) : 0;
            $blocks_registered = isset( $audit['blocks']['registered_count'] ) ? (int) $audit['blocks']['registered_count'] : 0;
            $blocks_unused = isset( $audit['blocks']['unused_registered'] ) && is_array( $audit['blocks']['unused_registered'] ) ? count( $audit['blocks']['unused_registered'] ) : 0;

            $shortcodes_used = isset( $audit['shortcodes']['used_counts'] ) && is_array( $audit['shortcodes']['used_counts'] ) ? count( $audit['shortcodes']['used_counts'] ) : 0;
            $shortcodes_registered = isset( $audit['shortcodes']['registered_count'] ) ? (int) $audit['shortcodes']['registered_count'] : 0;
            $shortcodes_missing = isset( $audit['shortcodes']['missing_handlers'] ) && is_array( $audit['shortcodes']['missing_handlers'] ) ? count( $audit['shortcodes']['missing_handlers'] ) : 0;

            ?>
            <table class="widefat striped" style="max-width:980px;">
                <tbody>
                    <tr>
                        <th><?php esc_html_e( 'Posts scanned', 'wp-block-shortcode-auditor' ); ?></th>
                        <td><?php echo esc_html( $posts_scanned ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Blocks detected in published content', 'wp-block-shortcode-auditor' ); ?></th>
                        <td><?php echo esc_html( $blocks_used ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Blocks registered on site', 'wp-block-shortcode-auditor' ); ?></th>
                        <td><?php echo esc_html( $blocks_registered ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Registered blocks not detected', 'wp-block-shortcode-auditor' ); ?></th>
                        <td><?php echo esc_html( $blocks_unused ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Shortcodes detected in content', 'wp-block-shortcode-auditor' ); ?></th>
                        <td><?php echo esc_html( $shortcodes_used ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Shortcodes registered on site', 'wp-block-shortcode-auditor' ); ?></th>
                        <td><?php echo esc_html( $shortcodes_registered ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Shortcodes with no handler', 'wp-block-shortcode-auditor' ); ?></th>
                        <td>
                            <?php
                            if ( $shortcodes_missing > 0 ) {
                                echo '<span style="color:#dc3232;font-weight:600;">' . esc_html( $shortcodes_missing ) . '</span>';
                            } else {
                                echo '<span style="color:#46b450;font-weight:600;">0</span>';
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php
        }

        private function render_used_blocks( $audit ) {
            $counts = isset( $audit['blocks']['used_counts'] ) && is_array( $audit['blocks']['used_counts'] ) ? $audit['blocks']['used_counts'] : array();
            if ( empty( $counts ) ) {
                echo '<p>No blocks detected. Either this site is pure classic editor, or the content is being stored somewhere else.</p>';
                return;
            }

            $rows = $this->human_count_rows( $counts, 100 );

            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Block</th><th>Count</th></tr></thead><tbody>';

            foreach ( $rows as $r ) {
                echo '<tr>';
                echo '<td><code>' . esc_html( $r['name'] ) . '</code></td>';
                echo '<td><strong>' . esc_html( $r['count'] ) . '</strong></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            if ( count( $counts ) > 100 ) {
                echo '<p style="font-size:12px;opacity:0.8;">Showing top 100. Export JSON for the full list.</p>';
            }
        }

        private function render_unused_registered_blocks( $audit ) {
            $unused = isset( $audit['blocks']['unused_registered'] ) && is_array( $audit['blocks']['unused_registered'] ) ? $audit['blocks']['unused_registered'] : array();
            if ( empty( $unused ) ) {
                echo '<p>No unused registered blocks detected. Either everything is used, or the scan window is too small.</p>';
                return;
            }

            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Block</th></tr></thead><tbody>';

            $limit = 200;
            $i = 0;
            foreach ( $unused as $name ) {
                $i++;
                if ( $i > $limit ) {
                    break;
                }
                echo '<tr><td><code>' . esc_html( $name ) . '</code></td></tr>';
            }

            echo '</tbody></table>';

            if ( count( $unused ) > $limit ) {
                echo '<p style="font-size:12px;opacity:0.8;">Showing first ' . esc_html( $limit ) . '. Export JSON for the full list.</p>';
            }
        }

        private function render_shortcodes( $audit ) {
            $counts = isset( $audit['shortcodes']['used_counts'] ) && is_array( $audit['shortcodes']['used_counts'] ) ? $audit['shortcodes']['used_counts'] : array();
            if ( empty( $counts ) ) {
                echo '<p>No shortcodes detected in scanned content.</p>';
                return;
            }

            $rows = $this->human_count_rows( $counts, 100 );

            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Shortcode</th><th>Count</th></tr></thead><tbody>';

            foreach ( $rows as $r ) {
                echo '<tr>';
                echo '<td><code>[' . esc_html( $r['name'] ) . ']</code></td>';
                echo '<td><strong>' . esc_html( $r['count'] ) . '</strong></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            if ( count( $counts ) > 100 ) {
                echo '<p style="font-size:12px;opacity:0.8;">Showing top 100. Export JSON for the full list.</p>';
            }
        }

        private function render_missing_shortcode_handlers( $audit ) {
            $missing = isset( $audit['shortcodes']['missing_handlers'] ) && is_array( $audit['shortcodes']['missing_handlers'] ) ? $audit['shortcodes']['missing_handlers'] : array();
            if ( empty( $missing ) ) {
                echo '<p><span style="color:#46b450;font-weight:600;">No missing shortcode handlers detected.</span></p>';
                return;
            }

            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Shortcode</th><th>What it implies</th></tr></thead><tbody>';

            $limit = 200;
            $i = 0;
            foreach ( $missing as $tag ) {
                $i++;
                if ( $i > $limit ) {
                    break;
                }
                echo '<tr>';
                echo '<td><code>[' . esc_html( $tag ) . ']</code></td>';
                echo '<td>Appears in content but no plugin registers it right now</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            if ( count( $missing ) > $limit ) {
                echo '<p style="font-size:12px;opacity:0.8;">Showing first ' . esc_html( $limit ) . '. Export JSON for the full list.</p>';
            }
        }
    }

    new WP_Block_Shortcode_Auditor();
}
