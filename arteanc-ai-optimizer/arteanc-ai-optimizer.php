<?php
/*
Plugin Name: Arteanc AI Optimizer
Description: Optimiza títulos y descripciones WooCommerce con OpenAI GPT-4o. Usa prompts globales y específicos por categoría, analiza varias imágenes del producto y agrega texto final automático.
Version: 1.31
Author: Ozni & ChatGPT
*/

if (!defined('ABSPATH')) {
    exit;
}

define('ARTEANC_AI_OPTIMIZER_PATH', plugin_dir_path(__FILE__));
define('ARTEANC_AI_OPTIMIZER_URL', plugin_dir_url(__FILE__));

require_once ARTEANC_AI_OPTIMIZER_PATH . 'includes/class-arteanc-ai-optimizer.php';

Arteanc_AI_Optimizer::instance();
