<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once ARTEANC_AI_OPTIMIZER_PATH . 'includes/admin/class-arteanc-ai-settings.php';
require_once ARTEANC_AI_OPTIMIZER_PATH . 'includes/admin/class-arteanc-ai-category-fields.php';
require_once ARTEANC_AI_OPTIMIZER_PATH . 'includes/services/class-arteanc-ai-product-optimizer.php';

class Arteanc_AI_Optimizer {
    const OPTION_KEY = 'arteanc_ai_optimizer_settings';

    /** @var Arteanc_AI_Settings */
    private $settings;

    /** @var Arteanc_AI_Category_Fields */
    private $category_fields;

    /** @var Arteanc_AI_Product_Optimizer */
    private $product_optimizer;

    /** @var self */
    private static $instance;

    private function __construct() {
        $this->settings = new Arteanc_AI_Settings(self::OPTION_KEY);
        $this->category_fields = new Arteanc_AI_Category_Fields();
        $this->product_optimizer = new Arteanc_AI_Product_Optimizer(self::OPTION_KEY);

        $this->settings->register_hooks();
        $this->category_fields->register_hooks();
        $this->product_optimizer->register_hooks();
    }

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
