<?php
/**
 * Plugin Name: AI Content Generator
 * Plugin URI: https://github.com/uu0/ai-content-generator
 * Description: 使用硅基流动API自动生成文章摘要和特色图片，支持Token统计和批量管理
 * Version: 2.0.3
 * Author: uu0
 * License: GPL v2 or later
 * Text Domain: ai-content-generator
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('AI_CONTENT_GENERATOR_VERSION', '2.0.3');
define('AI_CONTENT_GENERATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_CONTENT_GENERATOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// 加载主类
require_once AI_CONTENT_GENERATOR_PLUGIN_DIR . 'includes/class-ai-content-generator.php';

// 初始化插件
function ai_content_generator_init() {
    $plugin = AI_Content_Generator::get_instance();
    $plugin->init();
}
add_action('plugins_loaded', 'ai_content_generator_init');

// 激活钩子
register_activation_hook(__FILE__, array('AI_Content_Generator', 'activate'));

// 停用钩子
register_deactivation_hook(__FILE__, array('AI_Content_Generator', 'deactivate'));
