<?php

if (!defined('ABSPATH')) {
    exit;
}

class Arteanc_AI_Product_Optimizer {
    /** @var string */
    private $option_key;

    public function __construct(string $option_key) {
        $this->option_key = $option_key;
    }

    public function register_hooks(): void {
        add_action('add_meta_boxes_product', [$this, 'add_meta_box']);
        add_action('admin_post_arteanc_ai_optimize_one', [$this, 'optimize_product_now']);
        add_filter('bulk_actions-edit-product', [$this, 'register_bulk_action']);
        add_filter('handle_bulk_actions-edit-product', [$this, 'handle_bulk_action'], 10, 3);
        add_action('admin_notices', [$this, 'bulk_admin_notice']);
    }

    public function add_meta_box(): void {
        add_meta_box('arteanc_ai_optimizer_box', 'Arteanc AI Optimizer', [$this, 'render_meta_box'], 'product', 'side');
    }

    public function render_meta_box($post): void {
        echo '<p>Optimiza título y descripción con IA usando las imágenes del producto.</p>';
        echo '<a href="' . esc_url(admin_url('admin-post.php?action=arteanc_ai_optimize_one&post_id=' . $post->ID)) . '" class="button button-primary">Optimizar ahora (IA con imágenes)</a>';
    }

    public function optimize_product_now(): void {
        if (!isset($_GET['post_id'])) {
            return;
        }

        $product_id = intval($_GET['post_id']);
        $this->process_product($product_id, true);
    }

    public function register_bulk_action($bulk_actions) {
        $bulk_actions['optimize_ai'] = 'Optimizar con IA (Arteanc)';
        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'optimize_ai') {
            return $redirect_to;
        }

        foreach ($post_ids as $id) {
            $this->process_product(intval($id), false);
        }

        return add_query_arg('arteanc_ai_optimized', count($post_ids), $redirect_to);
    }

    public function bulk_admin_notice(): void {
        if (!empty($_GET['arteanc_ai_optimized'])) {
            $n = intval($_GET['arteanc_ai_optimized']);
            echo '<div class="notice notice-success is-dismissible"><p>✅ Optimización IA ejecutada en ' . $n . ' producto(s).</p></div>';
        }
    }

    private function process_product(int $product_id, bool $should_redirect): bool {
        ini_set('max_execution_time', 180);

        $product = wc_get_product($product_id);

        if (!$product) {
            return false;
        }

        $settings = get_option($this->option_key, []);
        $api_key = $settings['api_key'] ?? '';
        $max_title_length = $settings['max_title_length'] ?? 80;

        if (empty($api_key)) {
            wp_die('⚠️ Falta configurar la API Key de OpenAI.');
        }

        $title = $product->get_name();
        $description = wp_strip_all_tags($product->get_description());
        $dimensions_text = $this->get_precise_dimensions_text($product);

        $global_prompt = get_option('arteanc_global_prompts', '');
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

        $prompt = '';
        $footer = '';
        $max_images = 1;

        foreach ($categories as $cat_id) {
            $prompt = $this->get_recursive_meta($cat_id, 'arteanc_category_prompt');
            $footer = $this->get_recursive_meta($cat_id, 'arteanc_category_footer');
            $max_images = intval($this->get_recursive_meta($cat_id, 'arteanc_category_max_images')) ?: $max_images;

            if ($prompt || $footer) {
                break;
            }
        }

        $images_encoded = $this->prepare_image_payload($product, $max_images);

        $this->debug_openai_payload($product_id, $api_key, $global_prompt, $prompt, $dimensions_text, $images_encoded);

        $prompt_final = $this->build_prompt($global_prompt, $prompt, $dimensions_text, $max_title_length);
        $description = $this->apply_category_context_when_empty($description, $title, $categories);

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an expert eCommerce copywriter. Return ONLY valid JSON, no Markdown.'
            ],
            [
                'role' => 'user',
                'content' => array_merge([
                    ['type' => 'text', 'text' => $prompt_final]
                ], $images_encoded)
            ]
        ];

        $start = microtime(true);
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-4o',
                'messages' => $messages,
                'max_tokens' => 700,
                'temperature' => 0.6,
            ]),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            wp_die('❌ Error en conexión con OpenAI: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $raw = $data['choices'][0]['message']['content'] ?? '';

        $raw_basic = trim(str_replace(['```json', '```'], '', $raw));
        update_post_meta($product_id, '_arteanc_debug_raw', $raw_basic);

        $raw_clean = $this->clean_json_string($raw_basic);
        update_post_meta($product_id, '_arteanc_debug_clean', $raw_clean);

        $parsed = json_decode($raw_clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['title'], $parsed['description'])) {
            $parsed = [
                'title' => mb_substr(wp_strip_all_tags($raw_clean), 0, $max_title_length),
                'description' => '<p>' . esc_html($raw_clean) . '</p>',
            ];
        }

        if (isset($parsed['description'])) {
            $parsed['description'] = $this->verify_dimensions_in_text($dimensions_text, $parsed['description']);
        }

        $new_title = sanitize_text_field($parsed['title'] ?? $title);
        $content = wp_kses_post($parsed['description'] ?? '');
        $content = wpautop(force_balance_tags($content));

        if ($footer) {
            $footer = wp_kses_post($footer);
            $content .= "<p style='margin-top:2em; border-top:1px solid #e0e0e0; padding-top:1em; font-size:0.9em; color:#555;'>$footer</p>";
        }

        wp_update_post([
            'ID' => $product_id,
            'post_title' => mb_substr($new_title, 0, $max_title_length),
            'post_content' => $content,
        ]);

        $duration = round(microtime(true) - $start, 2);
        error_log("✅ Arteanc Debug: Optimización completada en {$duration}s para producto $product_id");

        if ($should_redirect) {
            wp_safe_redirect(admin_url('post.php?post=' . $product_id . '&action=edit&arteanc_ai_optimized=1'));
            exit;
        }

        return true;
    }

    private function get_recursive_meta($term_id, $key) {
        $value = get_term_meta($term_id, $key, true);

        if ($value) {
            return $value;
        }

        $term = get_term($term_id, 'product_cat');
        return ($term && $term->parent) ? $this->get_recursive_meta($term->parent, $key) : '';
    }

    private function get_precise_dimensions_text($product): string {
        $cm_to_inch = 0.393701;

        $height_cm = floatval($product->get_height());
        $width_cm = floatval($product->get_width());
        $length_cm = floatval($product->get_length());
        $weight_g = floatval($product->get_weight());

        $height_in = round($height_cm * $cm_to_inch, 2);
        $width_in = round($width_cm * $cm_to_inch, 2);
        $depth_in = round($length_cm * $cm_to_inch, 2);

        return "Height: {$height_in} in, Width: {$width_in} in, Depth: {$depth_in} in, Weight: {$weight_g} g.";
    }

    private function verify_dimensions_in_text(string $original, string $generated): string {
        preg_match_all('/\d+(\.\d+)?/', $original, $original_nums);
        $original_nums = $original_nums[0];

        foreach ($original_nums as $num) {
            if (strpos($generated, $num) === false) {
                $pattern = '/\d+(\.\d+)?/';
                $generated = preg_replace($pattern, $num, $generated, 1);
            }
        }

        return $generated;
    }

    private function prepare_image_payload($product, int $max_images): array {
        $image_ids = $product->get_gallery_image_ids();
        array_unshift($image_ids, $product->get_image_id());

        $image_urls = array_filter(array_map('wp_get_attachment_url', array_slice($image_ids, 0, $max_images)));

        $images_encoded = [];

        foreach ($image_urls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $images_encoded[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $url,
                        'detail' => 'high'
                    ]
                ];
                error_log("✅ Arteanc Debug: Imagen pública añadida correctamente → $url");
            } else {
                error_log("⚠️ Arteanc Debug: Imagen no accesible públicamente o error al verificar → $url");
            }
        }

        error_log('🖼️ Arteanc Debug: Usando ' . count($images_encoded) . " imágenes públicas (máximo $max_images) para producto " . $product->get_id());

        return $images_encoded;
    }

    private function debug_openai_payload(int $product_id, string $api_key, string $global_prompt, string $prompt, string $dimensions_text, array $images_encoded): void {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert eCommerce copywriter. Return ONLY valid JSON, no Markdown.'
                    ],
                    [
                        'role' => 'user',
                        'content' => array_merge([
                            ['type' => 'text', 'text' => "$global_prompt\n\n$prompt\n\nYou are an expert eCommerce copywriter specializing in ancient art and cultural artifacts.\n\nProduct Data:\n{$dimensions_text}\n\nUse the provided images and product data to create optimized title and description. Return valid JSON only."]
                        ], $images_encoded)
                    ]
                ],
                'max_tokens' => 700,
                'temperature' => 0.6,
            ]),
            'timeout' => 120,
        ]);

        $body = wp_remote_retrieve_body($response);

        file_put_contents(
            WP_CONTENT_DIR . '/arteanc_openai_debug.log',
            date('Y-m-d H:i:s') . " | Producto ID: $product_id | OpenAI Raw Response:\n" . $body . "\n\n",
            FILE_APPEND
        );

        $data = json_decode($body, true);

        if (isset($data['error'])) {
            $error_message = $data['error']['message'] ?? 'Error desconocido.';
            error_log('❌ OpenAI Error: ' . $error_message);
            wp_die('❌ Error de OpenAI: ' . esc_html($error_message));
        }
    }

    private function build_prompt(string $global_prompt, string $prompt, string $dimensions_text, int $max_title_length): string {
        return "$global_prompt\n\n$prompt

You are an expert eCommerce copywriter specializing in ancient art and cultural artifacts.

Product Data (do not alter these values):
{$dimensions_text}

Instructions:
- Use all numeric values exactly as provided.
- Do NOT round, alter, or reinterpret any number.
- Keep the same units (inches and grams).
- You may describe the historical, cultural, and artistic context, but the numeric data must stay identical.

Use the provided images and data to understand the item’s appearance, material, and artistic value.
Return ONLY valid JSON like:
{
  \"title\": \"short optimized product title (max $max_title_length characters)\",
  \"description\": \"HTML description (<p>, <h6>, <ul>, <li>) with historical, stylistic, and artistic context that incorporates the above product data.\"
}";
    }

    private function apply_category_context_when_empty(string $description, string $title, array $categories): string {
        if (strlen(trim($title)) >= 3 || !empty($description)) {
            return $description;
        }

        $detected = [];

        foreach ($categories as $cat_id) {
            $cat = get_term($cat_id);

            if (!$cat || empty($cat->name)) {
                continue;
            }

            $cat_name = strtolower($cat->name);

            if (strpos($cat_name, 'precolomb') !== false) {
                $detected[] = 'pre-Columbian artifacts from ancient Peru, possibly Moche, Nazca, or Chavín culture';
            } elseif (strpos($cat_name, 'ceram') !== false) {
                $detected[] = 'handmade ceramic artworks created by Peruvian artisans';
            } elseif (strpos($cat_name, 'joy') !== false) {
                $detected[] = 'handcrafted jewelry inspired by Andean or Peruvian traditions';
            } elseif (strpos($cat_name, 'textil') !== false) {
                $detected[] = 'traditional Peruvian textiles with intricate weaving and cultural motifs';
            } elseif (strpos($cat_name, 'escultur') !== false) {
                $detected[] = 'handcrafted sculptures representing Peruvian heritage or ancient cultures';
            } elseif (strpos($cat_name, 'pintur') !== false) {
                $detected[] = 'artistic paintings or decorative artworks inspired by Peruvian culture';
            } elseif (strpos($cat_name, 'madera') !== false) {
                $detected[] = 'hand-carved wooden artworks or crafts made by Peruvian artisans';
            }
        }

        if (!empty($detected)) {
            $description = 'These images show ' . implode(' and ', $detected) . '. Please analyze their craftsmanship, materials, and artistic style to create an optimized title and description in English. Focus on aesthetics, authenticity, and historical or cultural value.';
        } else {
            $description = 'These images show an artistic or cultural product from Peru. Please analyze its visual characteristics and describe it professionally in English with marketing tone.';
        }

        error_log('🧩 Arteanc Debug: Contexto automático aplicado → ' . $description);

        return $description;
    }

    private function clean_json_string(string $raw): string {
        $raw = trim($raw);
        $raw = str_replace(['```json', '```'], '', $raw);
        $raw = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $raw);

        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $raw = $matches[0];
        }

        return $raw;
    }
}
