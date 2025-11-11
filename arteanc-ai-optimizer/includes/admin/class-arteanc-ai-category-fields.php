<?php

if (!defined('ABSPATH')) {
    exit;
}

class Arteanc_AI_Category_Fields {
    public function register_hooks(): void {
        add_action('product_cat_add_form_fields', [$this, 'add_category_fields']);
        add_action('product_cat_edit_form_fields', [$this, 'edit_category_fields']);
        add_action('created_product_cat', [$this, 'save_category_fields']);
        add_action('edited_product_cat', [$this, 'save_category_fields']);
    }

    public function add_category_fields(): void {
        ?>
        <div class="form-field">
            <label for="arteanc_category_prompt">🧠 Prompt de IA personalizado</label>
            <textarea name="arteanc_category_prompt" rows="4" style="width:100%;"></textarea>
            <p class="description">Instrucciones específicas para esta categoría.</p>
        </div>

        <div class="form-field">
            <label for="arteanc_category_footer">📦 Texto final automático</label>
            <textarea name="arteanc_category_footer" rows="4" style="width:100%;"></textarea>
            <p class="description">Texto agregado automáticamente al final de la descripción.</p>
        </div>

        <div class="form-field">
            <label for="arteanc_category_max_images">🖼️ Cantidad máxima de imágenes IA</label>
            <input type="number" name="arteanc_category_max_images" value="1" min="1" max="10" style="width:120px;">
            <p class="description">Número máximo de imágenes que OpenAI analizará para esta categoría.</p>
        </div>
        <?php
    }

    public function edit_category_fields($term): void {
        $prompt = get_term_meta($term->term_id, 'arteanc_category_prompt', true);
        $footer = get_term_meta($term->term_id, 'arteanc_category_footer', true);
        $max_images = get_term_meta($term->term_id, 'arteanc_category_max_images', true) ?: 1;
        ?>
        <tr class="form-field">
            <th><label for="arteanc_category_prompt">🧠 Prompt de IA personalizado</label></th>
            <td><textarea name="arteanc_category_prompt" rows="4" style="width:100%;"><?php echo esc_textarea($prompt); ?></textarea></td>
        </tr>
        <tr class="form-field">
            <th><label for="arteanc_category_footer">📦 Texto final automático</label></th>
            <td><textarea name="arteanc_category_footer" rows="4" style="width:100%;"><?php echo esc_textarea($footer); ?></textarea></td>
        </tr>
        <tr class="form-field">
            <th><label for="arteanc_category_max_images">🖼️ Cantidad máxima de imágenes IA</label></th>
            <td>
                <input type="number" name="arteanc_category_max_images" value="<?php echo esc_attr($max_images); ?>" min="1" max="10" style="width:120px;">
                <p class="description">Número máximo de imágenes que OpenAI analizará para esta categoría.</p>
            </td>
        </tr>
        <?php
    }

    public function save_category_fields($term_id): void {
        if (isset($_POST['arteanc_category_prompt'])) {
            update_term_meta($term_id, 'arteanc_category_prompt', wp_kses_post($_POST['arteanc_category_prompt']));
        }

        if (isset($_POST['arteanc_category_footer'])) {
            update_term_meta($term_id, 'arteanc_category_footer', wp_kses_post($_POST['arteanc_category_footer']));
        }

        if (isset($_POST['arteanc_category_max_images'])) {
            update_term_meta($term_id, 'arteanc_category_max_images', intval($_POST['arteanc_category_max_images']));
        }
    }
}
