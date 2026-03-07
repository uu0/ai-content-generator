<?php
/**
 * 管理类
 */
class AI_Content_Generator_Admin {

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
     * 初始化
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_ai_cg_export_logs', array($this, 'export_logs'));
        add_action('wp_ajax_ai_cg_refresh_models', array($this, 'refresh_models_ajax'));
    }

    /**
     * 添加菜单
     */
    public function add_menu() {
        add_menu_page(
            'AI内容生成',
            'AI内容生成',
            'manage_options',
            'ai-content-generator',
            array($this, 'render_manage_page'),
            'dashicons-admin-generic',
            30
        );

        add_submenu_page(
            'ai-content-generator',
            '文章管理',
            '文章管理',
            'manage_options',
            'ai-content-generator',
            array($this, 'render_manage_page')
        );

        add_submenu_page(
            'ai-content-generator',
            '设置',
            '设置',
            'manage_options',
            'ai-content-generator-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting('ai_cg_settings', 'ai_cg_api_key');
        register_setting('ai_cg_settings', 'ai_cg_summary_enabled');
        register_setting('ai_cg_settings', 'ai_cg_featured_image_enabled');
        register_setting('ai_cg_settings', 'ai_cg_summary_model');
        register_setting('ai_cg_settings', 'ai_cg_image_model');
        register_setting('ai_cg_settings', 'ai_cg_auto_check_enabled');
    }

    /**
     * 加载脚本
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'ai-content-generator') === false) {
            return;
        }

        wp_enqueue_script(
            'ai-cg-admin',
            AI_CONTENT_GENERATOR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AI_CONTENT_GENERATOR_VERSION,
            true
        );

        wp_localize_script('ai-cg-admin', 'ai_cg_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_cg_nonce')
        ));

        wp_enqueue_style(
            'ai-cg-admin',
            AI_CONTENT_GENERATOR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AI_CONTENT_GENERATOR_VERSION
        );
    }

    /**
     * 渲染管理页面
     */
    public function render_manage_page() {
        // 处理筛选和分页
        $current_page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;

        // 构建查询参数
        $args = array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        // 应用筛选条件
        if (isset($_GET['filter']) && $_GET['filter'] !== 'all') {
            if ($_GET['filter'] === 'no_summary') {
                $args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'post_excerpt',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => 'post_excerpt',
                        'value' => '',
                        'compare' => '='
                    )
                );
            } elseif ($_GET['filter'] === 'no_image') {
                $args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_thumbnail_id',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_thumbnail_id',
                        'value' => '0',
                        'compare' => '='
                    )
                );
            } elseif ($_GET['filter'] === 'has_summary') {
                $meta_query = array(
                    array(
                        'key' => 'post_excerpt',
                        'value' => '',
                        'compare' => '!='
                    )
                );
                // 结合元数据查询
                $args['meta_query'] = $meta_query;
            } elseif ($_GET['filter'] === 'has_image') {
                $args['meta_query'] = array(
                    array(
                        'key' => '_thumbnail_id',
                        'value' => '0',
                        'compare' => '>'
                    )
                );
            }
        }

        $query = new WP_Query($args);
        $total_posts = $query->found_posts;
        $total_pages = ceil($total_posts / $per_page);

        include AI_CONTENT_GENERATOR_PLUGIN_DIR . 'templates/manage-page.php';
    }

    /**
     * 渲染设置页面
     */
    public function render_settings_page() {
        include AI_CONTENT_GENERATOR_PLUGIN_DIR . 'templates/settings-page.php';
    }

    /**
     * 导出日志
     */
    public function export_logs() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
            return;
        }

        $log_content = $this->collect_logs();

        if (empty($log_content)) {
            wp_send_json_error('未找到日志内容');
            return;
        }

        // 设置导出文件名
        $filename = 'ai-content-generator-logs-' . date('Y-m-d-H-i-s') . '.txt';

        // 发送文件
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($log_content));

        echo $log_content;
        exit;
    }

    /**
     * 收集日志内容
     */
    private function collect_logs() {
        $logs = array();
        $timestamp = current_time('Y-m-d H:i:s');
        $site_url = get_site_url();

        // 添加基本信息
        $logs[] = "=== AI Content Generator 日志导出 ===";
        $logs[] = "导出时间: " . $timestamp;
        $logs[] = "站点URL: " . $site_url;
        $logs[] = "插件版本: " . AI_CONTENT_GENERATOR_VERSION;
        $logs[] = "WordPress版本: " . get_bloginfo('version');
        $logs[] = "PHP版本: " . phpversion();
        $logs[] = "";
        $logs[] = "=== 配置信息 ===";
        $logs[] = "API密钥已配置: " . (empty(get_option('ai_cg_api_key')) ? '否' : '是');
        $logs[] = "摘要生成: " . (get_option('ai_cg_summary_enabled') ? '启用' : '禁用');
        $logs[] = "特色图片生成: " . (get_option('ai_cg_featured_image_enabled') ? '启用' : '禁用');
        $logs[] = "自动检查: " . (get_option('ai_cg_auto_check_enabled') ? '启用' : '禁用');
        $logs[] = "摘要模型: " . get_option('ai_cg_summary_model', '未设置');
        $logs[] = "图片模型: " . get_option('ai_cg_image_model', '未设置');
        $logs[] = "";

        // 添加插件日志
        $debug_log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log_file)) {
            $logs[] = "=== WordPress调试日志 (AI Content Generator相关) ===";
            $debug_content = file_get_contents($debug_log_file);

            // 提取与插件相关的日志
            $lines = explode("\n", $debug_content);
            $ai_cg_logs = array();

            foreach ($lines as $line) {
                if (strpos($line, 'AI Content Generator:') !== false) {
                    $ai_cg_logs[] = $line;
                }
            }

            if (!empty($ai_cg_logs)) {
                // 只显示最近的100条日志
                $recent_logs = array_slice($ai_cg_logs, -100);
                $logs = array_merge($logs, $recent_logs);
            } else {
                $logs[] = "未找到插件相关日志";
            }
            $logs[] = "";
        } else {
            $logs[] = "=== WordPress调试日志 ===";
            $logs[] = "未找到调试日志文件，可能WordPress调试模式未启用。";
            $logs[] = "请启用调试模式: define('WP_DEBUG', true); define('WP_DEBUG_LOG', true);";
            $logs[] = "";
        }

        // 添加最近的使用统计
        $logs[] = "=== 最近Token统计 (最近10条) ===";
        global $wpdb;
        $stats_table = $wpdb->prefix . 'ai_cg_token_stats';
        if ($wpdb->get_var("SHOW TABLES LIKE '$stats_table'") == $stats_table) {
            $recent_stats = $wpdb->get_results(
                "SELECT * FROM $stats_table ORDER BY created_at DESC LIMIT 10",
                ARRAY_A
            );

            if (!empty($recent_stats)) {
                foreach ($recent_stats as $stat) {
                    $logs[] = sprintf(
                        "时间: %s | 类型: %s | 模型: %s | 输入: %d | 输出: %d | 文章ID: %d",
                        $stat['created_at'],
                        $stat['action_type'],
                        $stat['model_used'],
                        $stat['input_tokens'],
                        $stat['output_tokens'],
                        $stat['post_id']
                    );
                }
            } else {
                $logs[] = "暂无Token统计数据";
            }
        } else {
            $logs[] = "统计数据表不存在";
        }
        $logs[] = "";

        // 添加系统状态
        $logs[] = "=== 系统状态 ===";
        $logs[] = "uploads目录可写: " . (is_writable(WP_CONTENT_DIR . '/uploads') ? '是' : '否');
        $logs[] = "插件目录: " . AI_CONTENT_GENERATOR_PLUGIN_DIR;
        $logs[] = "插件URL: " . AI_CONTENT_GENERATOR_PLUGIN_URL;
        $logs[] = "";

        return implode("\n", $logs);
    }

    /**
     * AJAX: 刷新模型列表
     */
    public function refresh_models_ajax() {
        // 检查权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '权限不足'));
        }

        // 检查nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_cg_refresh_models')) {
            wp_send_json_error(array('message' => '安全验证失败'));
        }

        // 获取API实例
        $api = AI_Content_Generator_API::get_instance();

        // 从API获取模型列表（强制刷新）
        $result = $api->get_available_models(true);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }

        // 如果是旧格式返回（包含 chat 和 image 键）
        if (isset($result['chat']) && isset($result['image'])) {
            wp_send_json_success(array(
                'message' => '模型列表刷新成功',
                'chat_models' => $result['chat'],
                'image_models' => $result['image'],
                'total' => isset($result['total']) ? $result['total'] : count($result['chat']) + count($result['image'])
            ));
        } else {
            // 尝试从缓存获取
            $cached_models = get_option('ai_cg_available_models', array());
            if (isset($cached_models['chat']) && isset($cached_models['image'])) {
                wp_send_json_success(array(
                    'message' => '从缓存获取模型列表成功',
                    'chat_models' => $cached_models['chat'],
                    'image_models' => $cached_models['image'],
                    'total' => isset($cached_models['total']) ? $cached_models['total'] : count($cached_models['chat']) + count($cached_models['image'])
                ));
            } else {
                wp_send_json_error(array('message' => '获取模型列表失败'));
            }
        }
    }
}
