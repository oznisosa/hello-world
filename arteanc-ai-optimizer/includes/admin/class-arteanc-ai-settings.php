<?php

if (!defined('ABSPATH')) {
    exit;
}

class Arteanc_AI_Settings {
    /** @var string */
    private $option_key;

    public function __construct(string $option_key) {
        $this->option_key = $option_key;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page(): void {
        add_menu_page(
            'Arteanc AI Optimizer',
            'Arteanc AI Optimizer',
            'manage_options',
            'arteanc-ai-optimizer',
            [$this, 'render_settings_general_page'],
            'dashicons-art',
            56
        );

        add_submenu_page(
            'arteanc-ai-optimizer',
            'Instrucciones Globales',
            'Instrucciones Globales',
            'manage_options',
            'arteanc-global-prompts',
            [$this, 'render_global_prompts_page']
        );
    }

    public function register_settings(): void {
        register_setting($this->option_key, $this->option_key);
    }

    public function render_global_prompts_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['arteanc_global_prompts']) && check_admin_referer('arteanc_global_prompts_action', 'arteanc_global_prompts_nonce')) {
            update_option('arteanc_global_prompts', wp_kses_post($_POST['arteanc_global_prompts']));
            echo '<div class="updated"><p><strong>✅ Instrucciones globales guardadas correctamente.</strong></p></div>';
        }

        $global_prompts = get_option('arteanc_global_prompts', '');
        ?>
        <div class="wrap">
            <h1>🧠 Instrucciones Globales</h1>
            <form method="post">
                <?php wp_nonce_field('arteanc_global_prompts_action', 'arteanc_global_prompts_nonce'); ?>
                <textarea name="arteanc_global_prompts" rows="15" style="width:100%;"><?php echo esc_textarea($global_prompts); ?></textarea>
                <p><input type="submit" class="button-primary" value="Guardar Instrucciones Globales"></p>
            </form>
        </div>
        <?php
    }

    public function render_settings_general_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['arteanc_settings']) && check_admin_referer('arteanc_ai_settings_action', 'arteanc_ai_settings_nonce')) {
            $settings = [
                'api_key' => sanitize_text_field($_POST['arteanc_settings']['api_key']),
                'max_title_length' => intval($_POST['arteanc_settings']['max_title_length']),
            ];

            update_option($this->option_key, $settings);
            echo '<div class="updated"><p><strong>✅ Configuración guardada correctamente.</strong></p></div>';
        }

        $settings = get_option($this->option_key, [
            'api_key' => '',
            'max_title_length' => 80,
        ]);
        ?>
        <div class="wrap">
            <h1>⚙️ Configuración General</h1>
            <form method="post">
                <?php wp_nonce_field('arteanc_ai_settings_action', 'arteanc_ai_settings_nonce'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th>🔑 OpenAI API Key</th>
                        <td><input type="text" name="arteanc_settings[api_key]" value="<?php echo esc_attr($settings['api_key']); ?>" style="width:400px;"></td>
                    </tr>
                    <tr valign="top">
                        <th>📝 Longitud máxima del título</th>
                        <td><input type="number" name="arteanc_settings[max_title_length]" value="<?php echo esc_attr($settings['max_title_length']); ?>" min="30" max="120"></td>
                    </tr>
                </table>
                <p><input type="submit" class="button-primary" value="Guardar Configuración"></p>
            </form>
        </div>
        <?php
    }
}
