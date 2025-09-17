<?php
/**
 * Plugin Name: Thermo Plugin
 * Description: Simple pill-shaped thermometer/progress bar with milestone markers. Use [thermometer]. Includes site-wide defaults and colors (Settings → Thermometer).
 * Version: 0.0.1
 * Author: Christopher Platt
 * License: 
 */

if (!defined('ABSPATH')) { exit; }

// Define paths
if (!defined('THERMO_URL')) { define('THERMO_URL', plugin_dir_url(__FILE__)); }
if (!defined('THERMO_VER')) { define('THERMO_VER', '0.0.1'); }

// ---- Options (site-wide) ----------------------------------------------------
function thermo_default_options() {
    return [
        'default_target'       => 1000,
        'default_unit'         => '',
        'default_milestones'   => '', // e.g. 250,500,750
        'default_show_numbers' => 'true',
        'fill_from'            => '#5b9cff',
        'fill_to'              => '#3ac8a8',
    ];
}

function thermo_get_options() {
    $opts = get_option('thermo_options', []);
    return wp_parse_args($opts, thermo_default_options());
}

function thermo_sanitize_options($input) {
    $defaults = thermo_default_options();
    $out = [];
    $out['default_target']       = max(0, floatval($input['default_target'] ?? $defaults['default_target']));
    $out['default_unit']         = sanitize_text_field($input['default_unit'] ?? $defaults['default_unit']);
    $out['default_milestones']   = sanitize_text_field($input['default_milestones'] ?? $defaults['default_milestones']);
    $out['default_show_numbers'] = (isset($input['default_show_numbers']) && $input['default_show_numbers'] === 'false') ? 'false' : 'true';

    // Color validation – accept 3/6 hex or rgb(a)
    $color = function($v, $fallback) {
        $v = trim((string)$v);
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v)) return $v;
        if (preg_match('/^rgba?\\([^\\)]+\\)$/i', $v)) return $v;
        return $fallback;
    };
    $out['fill_from'] = $color($input['fill_from'] ?? $defaults['fill_from'], $defaults['fill_from']);
    $out['fill_to']   = $color($input['fill_to']   ?? $defaults['fill_to'],   $defaults['fill_to']);

    return $out;
}

function thermo_register_settings() {
    register_setting('thermo_group', 'thermo_options', [
        'type'              => 'array',
        'sanitize_callback' => 'thermo_sanitize_options',
        'default'           => thermo_default_options(),
    ]);

    add_settings_section('thermo_section_main', 'Defaults', function(){
        echo '<p>Set site-wide defaults for the thermometer shortcode and pick the gradient colors.</p>';
    }, 'thermo');

    add_settings_field('default_target', 'Default target', 'thermo_field_default_target', 'thermo', 'thermo_section_main');
    add_settings_field('default_unit', 'Default unit', 'thermo_field_default_unit', 'thermo', 'thermo_section_main');
    add_settings_field('default_milestones', 'Default milestones', 'thermo_field_default_milestones', 'thermo', 'thermo_section_main');
    add_settings_field('default_show_numbers', 'Show numbers by default', 'thermo_field_default_show_numbers', 'thermo', 'thermo_section_main');

    add_settings_section('thermo_section_colors', 'Colors', function(){
        echo '<p>Set the pill fill gradient colors.</p>';
    }, 'thermo');
    add_settings_field('fill_from', 'Fill from', 'thermo_field_fill_from', 'thermo', 'thermo_section_colors');
    add_settings_field('fill_to', 'Fill to', 'thermo_field_fill_to', 'thermo', 'thermo_section_colors');
}
add_action('admin_init', 'thermo_register_settings');

function thermo_add_options_page() {
    add_options_page('Thermometer', 'Thermometer', 'manage_options', 'thermo', 'thermo_render_options_page');
}
add_action('admin_menu', 'thermo_add_options_page');

function thermo_render_options_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1>Smith House Thermometer</h1>
        <form method="post" action="options.php">
            <?php settings_fields('thermo_group'); do_settings_sections('thermo'); submit_button(); ?>
        </form>
        <p><em>Tip:</em> Shortcode attributes always override these defaults.</p>
    </div>
    <?php
}

// Field callbacks
function thermo_field_default_target() { $o = thermo_get_options(); ?>
    <input type="number" step="0.01" min="0" name="thermo_options[default_target]" value="<?php echo esc_attr($o['default_target']); ?>" />
<?php }
function thermo_field_default_unit() { $o = thermo_get_options(); ?>
    <input type="text" name="thermo_options[default_unit]" value="<?php echo esc_attr($o['default_unit']); ?>" placeholder="$ or %" />
<?php }
function thermo_field_default_milestones() { $o = thermo_get_options(); ?>
    <input type="text" name="thermo_options[default_milestones]" value="<?php echo esc_attr($o['default_milestones']); ?>" placeholder="e.g. 250,500,750" />
    <p class="description">Comma-separated list; each must be between 0 and target.</p>
<?php }
function thermo_field_default_show_numbers() { $o = thermo_get_options(); ?>
    <label><input type="checkbox" name="thermo_options[default_show_numbers]" value="true" <?php checked($o['default_show_numbers'], 'true'); ?> /> Show current/target legend</label>
<?php }
function thermo_field_fill_from() { $o = thermo_get_options(); ?>
    <input type="text" class="regular-text" name="thermo_options[fill_from]" value="<?php echo esc_attr($o['fill_from']); ?>" placeholder="#5b9cff" />
<?php }
function thermo_field_fill_to() { $o = thermo_get_options(); ?>
    <input type="text" class="regular-text" name="thermo_options[fill_to]" value="<?php echo esc_attr($o['fill_to']); ?>" placeholder="#3ac8a8" />
<?php }

// ---- Assets -----------------------------------------------------------------
function thermo_enqueue_assets() {
    $opts = thermo_get_options();
    echo THERMO_URL;
    wp_enqueue_style('thermo-css', THERMO_URL . 'assets/thermometer.css', [], THERMO_VER);
    $inline = ":root{--thermo-fill-from: {$opts['fill_from']}; --thermo-fill-to: {$opts['fill_to']};}";
    wp_add_inline_style('thermo-css', $inline);
    wp_enqueue_script('thermo-js', THERMO_URL . 'assets/thermometer.js', [], THERMO_VER, true);
}
add_action('wp_enqueue_scripts', 'thermo_enqueue_assets');

// ---- Helpers ----------------------------------------------------------------
function thermo_parse_milestones($raw, $target) {
    $milestones = array_filter(array_map('trim', explode(',', (string)$raw)), function($v){ return $v !== ''; });
    $milestones = array_map(function($v){ return floatval(preg_replace('/[^\\d.\\-]/', '', $v)); }, $milestones);
    $milestones = array_filter($milestones, function($v) use ($target){ return $v >= 0 && $v <= $target; });
    $milestones = array_values(array_unique($milestones));
    sort($milestones);
    return $milestones;
}

// ---- Shortcode ---------------------------------------------------------------
// [thermometer target="10000" current="2500" milestones="1000,2500,5000,7500" label="Fundraiser" unit="$" show_numbers="true"]
function thermometer_shortcode($atts) {
    $opts = thermo_get_options();

    // Defaults come from site options; attributes override
    $atts = shortcode_atts([
        'target'        => $opts['default_target'],
        'current'       => 0,
        'milestones'    => $opts['default_milestones'],
        'label'         => '',
        'unit'          => $opts['default_unit'],
        'show_numbers'  => $opts['default_show_numbers'],
    ], $atts, 'thermometer');

    $target  = max(0, floatval($atts['target']));
    $current = max(0, floatval($atts['current']));
    if ($target <= 0) { $target = 1; }

    $milestones = thermo_parse_milestones($atts['milestones'], $target);
    $percent = min(100, max(0, ($current / $target) * 100));
    $id = function_exists('wp_unique_id') ? wp_unique_id('thermo-') : ('thermo-' . uniqid());

    $label = sanitize_text_field($atts['label']);
    $unit  = sanitize_text_field($atts['unit']);
    $show_numbers = filter_var($atts['show_numbers'], FILTER_VALIDATE_BOOLEAN);

    $fmt = function($n) { return rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.'); };
    $current_label = $unit . $fmt($current);
    $target_label  = $unit . $fmt($target);

    ob_start(); ?>
    <div class="thermo" id="<?php echo esc_attr($id); ?>" data-percent="<?php echo esc_attr($percent); ?>" data-current="<?php echo esc_attr($current); ?>" data-target="<?php echo esc_attr($target); ?>">
        <?php if ($label) : ?><div class="thermo-title"><?php echo esc_html($label); ?></div><?php endif; ?>
        <div class="thermo-bar" role="progressbar" aria-label="<?php echo esc_attr($label ? $label : 'Progress'); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr($fmt($target)); ?>" aria-valuenow="<?php echo esc_attr($fmt($current)); ?>">
            <div class="thermo-fill" style="width: 0%"></div>
            <?php foreach ($milestones as $m):
                $p = ($m / $target) * 100;
                $met = $current >= $m ? ' met' : '';
            ?>
                <div class="thermo-marker<?php echo $met; ?>" style="left: <?php echo esc_attr($p); ?>%" aria-hidden="true">
                    <span class="thermo-marker-dot"></span>
                    <span class="thermo-marker-label"><?php echo esc_html($unit . $fmt($m)); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($show_numbers): ?>
            <div class="thermo-legend">
                <span class="thermo-current"><?php echo esc_html($current_label); ?></span>
                <span class="thermo-sep">/</span>
                <span class="thermo-target"><?php echo esc_html($target_label); ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('thermometer', 'thermometer_shortcode');
