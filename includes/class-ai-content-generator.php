<?php
/**
 * 主插件类
 */
class AI_Content_Generator {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        // 构造函数
    }

    /**
     * 初始化插件
     */
    public function init() {
        // 加载文本域
        load_plugin_textdomain('ai-content-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // 加载必需的文件
        $this->load_dependencies();

        // 初始化管理类
        $admin = AI_Content_Generator_Admin::get_instance();
        $admin->init();

        // 初始化API类
        $api = AI_Content_Generator_API::get_instance();
        $api->init();

        // 初始化Token统计类
        $stats = AI_Content_Generator_Stats::get_instance();
        $stats->init();

        // 初始化后台任务类
        $background = AI_Content_Generator_Background::get_instance();
        $background->init();
    }

    /**
     * 加载依赖文件
     */
    private function load_dependencies() {
        require_once AI_CONTENT_GENERATOR_PLUGIN_DIR . 'includes/class-ai-content-generator-admin.php';
        require_once AI_CONTENT_GENERATOR_PLUGIN_DIR . 'includes/class-ai-content-generator-api.php';
        require_once AI_CONTENT_GENERATOR_PLUGIN_DIR . 'includes/class-ai-content-generator-stats.php';
        require_once AI_CONTENT_GENERATOR_PLUGIN_DIR . 'includes/class-ai-content-generator-background.php';
    }

    /**
     * 激活插件
     */
    public static function activate() {
        // 创建数据表
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Token统计表
        $table_name = $wpdb->prefix . 'ai_cg_token_stats';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            model varchar(100) NOT NULL,
            input_tokens int(11) NOT NULL DEFAULT 0,
            output_tokens int(11) NOT NULL DEFAULT 0,
            total_tokens int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY action (action)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // 设置默认选项
        add_option('ai_cg_api_key', '');
        add_option('ai_cg_summary_enabled', 0);
        add_option('ai_cg_featured_image_enabled', 0);
        add_option('ai_cg_summary_model', 'deepseek-chat');
        add_option('ai_cg_image_model', 'stable-diffusion-3');
        add_option('ai_cg_auto_check_enabled', 0);

        // 设置定时任务
        if (!wp_next_scheduled('ai_cg_hourly_check')) {
            wp_schedule_event(time(), 'hourly', 'ai_cg_hourly_check');
        }

        flush_rewrite_rules();
    }

    /**
     * 停用插件
     */
    public static function deactivate() {
        // 清除定时任务
        wp_clear_scheduled_hook('ai_cg_hourly_check');

        flush_rewrite_rules();
    }
}
