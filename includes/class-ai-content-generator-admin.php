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
        add_action('wp_ajax_ai_cg_generate_summary', array($this, 'generate_summary_ajax'));
        add_action('wp_ajax_ai_cg_generate_image', array($this, 'generate_image_ajax'));
        add_action('wp_ajax_ai_cg_generate_image_description', array($this, 'generate_image_description_ajax'));
        add_action('wp_ajax_ai_cg_polish_content', array($this, 'polish_content_ajax'));
        add_action('wp_ajax_ai_cg_reformat_content', array($this, 'reformat_content_ajax'));
        add_action('wp_ajax_ai_cg_polish_content', array($this, 'polish_content_ajax'));
        add_action('wp_ajax_ai_cg_preview_operation', array($this, 'preview_operation_ajax'));
        add_action('wp_ajax_ai_cg_undo_operation', array($this, 'undo_operation_ajax'));
        add_action('wp_ajax_ai_cg_batch_exclude', array($this, 'batch_exclude_ajax'));
        add_action('wp_ajax_ai_cg_remove_from_excluded', array($this, 'remove_from_excluded_ajax'));
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
        register_setting('ai_cg_settings', 'ai_cg_image_description_model');
        register_setting('ai_cg_settings', 'ai_cg_auto_check_enabled');

        // 新增：自定义提示词
        register_setting('ai_cg_settings', 'ai_cg_summary_prompt');
        register_setting('ai_cg_settings', 'ai_cg_image_prompt');
        register_setting('ai_cg_settings', 'ai_cg_image_description_prompt');
        register_setting('ai_cg_settings', 'ai_cg_polish_prompt_normal');
        register_setting('ai_cg_settings', 'ai_cg_polish_prompt_formal');
        register_setting('ai_cg_settings', 'ai_cg_polish_prompt_casual');
        register_setting('ai_cg_settings', 'ai_cg_polish_prompt_creative');
        register_setting('ai_cg_settings', 'ai_cg_reformat_prompt_standard');
        register_setting('ai_cg_settings', 'ai_cg_reformat_prompt_blog');
        register_setting('ai_cg_settings', 'ai_cg_reformat_prompt_technical');

        // 新增：排除设置（带回调处理数组）
        register_setting('ai_cg_settings', 'ai_cg_excluded_categories', array(
            'sanitize_callback' => array($this, 'sanitize_excluded_ids')
        ));
        register_setting('ai_cg_settings', 'ai_cg_excluded_pages', array(
            'sanitize_callback' => array($this, 'sanitize_excluded_ids')
        ));
        register_setting('ai_cg_settings', 'ai_cg_excluded_posts');
    }

    /**
     * 清理排除ID（将数组转换为逗号分隔的字符串）
     */
    public function sanitize_excluded_ids($input) {
        if (is_array($input)) {
            // 过滤掉空值和无效值
            $filtered = array_filter(array_map('intval', $input));
            return implode(',', $filtered);
        }
        return '';
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
            } elseif ($_GET['filter'] === 'excluded') {
                // 排除的文章
                $excluded_posts = array_filter(array_map('trim', explode(',', get_option('ai_cg_excluded_posts', ''))));
                $excluded_categories = array_filter(array_map('trim', explode(',', get_option('ai_cg_excluded_categories', ''))));

                if (!empty($excluded_categories)) {
                    $args['category__in'] = $excluded_categories;
                }

                if (!empty($excluded_posts)) {
                    $args['post__in'] = $excluded_posts;
                }
            }
        }

        // 应用排除规则（自动处理时）
        $excluded_categories = get_option('ai_cg_excluded_categories', '');
        $excluded_category_ids = array_filter(array_map('trim', explode(',', $excluded_categories)));
        if (!empty($excluded_category_ids)) {
            $args['category__not_in'] = $excluded_category_ids;
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_cg_nonce')) {
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

    /**
     * AJAX: 生成摘要
     */
    public function generate_summary_ajax() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('文章ID无效');
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('文章不存在');
            return;
        }

        $api = AI_Content_Generator_API::get_instance();

        // 检查是否被排除
        if ($api->is_post_excluded($post_id)) {
            wp_send_json_error('这篇文章已被排除');
            return;
        }

        // 使用自定义提示词或默认提示词
        $custom_prompt = get_option('ai_cg_summary_prompt', '');
        if (!empty($custom_prompt)) {
            // 如果有自定义提示词，需要使用特殊的方法
            $prompt = $custom_prompt;
            $content = $post->post_content;
            if (!empty($post->post_title)) {
                $prompt = str_replace('{title}', $post->post_title, $prompt);
            }
            $prompt = str_replace('{content}', wp_trim_words($content, 500), $prompt);
            $response = $api->generate_summary($content, $post->post_title);
        } else {
            $response = $api->generate_summary($post->post_content, $post->post_title);
        }

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        $summary = $response['choices'][0]['message']['content'];

        // 更新文章摘要
        wp_update_post(array(
            'ID' => $post_id,
            'post_excerpt' => $summary
        ));

        // 记录生成时间
        update_post_meta($post_id, '_ai_cg_summary_generated', current_time('mysql'));

        wp_send_json_success(array('summary' => $summary));
    }

    /**
     * AJAX: 生成图片
     */
    public function generate_image_ajax() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('文章ID无效');
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('文章不存在');
            return;
        }

        $api = AI_Content_Generator_API::get_instance();

        // 检查是否被排除
        if ($api->is_post_excluded($post_id)) {
            wp_send_json_error('这篇文章已被排除');
            return;
        }

        $response = $api->generate_featured_image($post->post_content, $post->post_title);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        $image_url = $response['data'][0]['url'];

        // 下载图片并上传到媒体库
        $sideload = $this->sideload_image($image_url, $post_id);

        if (is_wp_error($sideload)) {
            wp_send_json_error($sideload->get_error_message());
            return;
        }

        // 设置为特色图片
        set_post_thumbnail($post_id, $sideload);

        // 记录生成时间
        update_post_meta($post_id, '_ai_cg_image_generated', current_time('mysql'));

        wp_send_json_success(array('image_url' => wp_get_attachment_url($sideload)));
    }

    /**
     * AJAX: 生成图片描述
     */
    public function generate_image_description_ajax() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error('文章ID无效');
            return;
        }

        $api = AI_Content_Generator_API::get_instance();

        // 检查是否被排除
        if ($api->is_post_excluded($post_id)) {
            wp_send_json_error('这篇文章已被排除');
            return;
        }

        // 调用新的图片描述和重命名方法
        $response = $api->generate_descriptions_and_rename_images($post_id);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        // 记录处理时间
        update_post_meta($post_id, '_ai_cg_image_description_generated', current_time('mysql'));
        update_post_meta($post_id, '_ai_cg_image_description_count', $response['renamed']);

        wp_send_json_success($response);
    }

    /**
     * AJAX: AI润色
     */
    public function polish_content_ajax() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $style = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : 'normal';

        if (!$post_id) {
            wp_send_json_error('文章ID无效');
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('文章不存在');
            return;
        }

        $api = AI_Content_Generator_API::get_instance();

        // 检查是否被排除
        if ($api->is_post_excluded($post_id)) {
            wp_send_json_error('这篇文章已被排除');
            return;
        }

        $response = $api->polish_content($post->post_content, $style);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        $polished_content = $response['choices'][0]['message']['content'];

        // 保存原始内容以便撤回
        $original_content = $post->post_content;
        update_post_meta($post_id, '_ai_cg_original_content', $original_content);
        update_post_meta($post_id, '_ai_cg_operation_type', 'polish');
        update_post_meta($post_id, '_ai_cg_operation_timestamp', current_time('timestamp'));

        // 更新文章内容
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $polished_content
        ));

        // 记录润色时间
        update_post_meta($post_id, '_ai_cg_polished_at', current_time('mysql'));
        update_post_meta($post_id, '_ai_cg_polish_style', $style);

        wp_send_json_success(array('content' => $polished_content));
    }

    /**
     * AJAX: AI排版
     */
    public function reformat_content_ajax() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $format_type = isset($_POST['format_type']) ? sanitize_text_field($_POST['format_type']) : 'standard';

        if (!$post_id) {
            wp_send_json_error('文章ID无效');
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('文章不存在');
            return;
        }

        $api = AI_Content_Generator_API::get_instance();

        // 检查是否被排除
        if ($api->is_post_excluded($post_id)) {
            wp_send_json_error('这篇文章已被排除');
            return;
        }

        $response = $api->reformat_content($post->post_content, $format_type);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        $formatted_content = $response['choices'][0]['message']['content'];

        // 保存原始内容以便撤回
        $original_content = $post->post_content;
        update_post_meta($post_id, '_ai_cg_original_content', $original_content);
        update_post_meta($post_id, '_ai_cg_operation_type', 'reformat');
        update_post_meta($post_id, '_ai_cg_operation_timestamp', current_time('timestamp'));

        // 更新文章内容
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $formatted_content
        ));

        // 记录排版时间
        update_post_meta($post_id, '_ai_cg_reformatted_at', current_time('mysql'));
        update_post_meta($post_id, '_ai_cg_reformat_type', $format_type);

        wp_send_json_success(array('content' => $formatted_content, 'can_undo' => true));
    }

    /**
     * AJAX: 预览操作（只生成内容不保存）
     */
    public function preview_operation_ajax() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $operation_type = isset($_POST['operation_type']) ? sanitize_text_field($_POST['operation_type']) : '';

        if (!$post_id || !$operation_type) {
            wp_send_json_error('参数无效');
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('文章不存在');
            return;
        }

        $api = AI_Content_Generator_API::get_instance();

        // 检查是否被排除
        if ($api->is_post_excluded($post_id)) {
            wp_send_json_error('这篇文章已被排除');
            return;
        }

        // 根据操作类型调用相应的 API
        if ($operation_type === 'polish') {
            $style = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : 'normal';
            $response = $api->polish_content($post->post_content, $style);
        } else if ($operation_type === 'reformat') {
            $format_type = isset($_POST['format_type']) ? sanitize_text_field($_POST['format_type']) : 'standard';
            $response = $api->reformat_content($post->post_content, $format_type);
        } else {
            wp_send_json_error('不支持的操作类型');
            return;
        }

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        $new_content = $response['choices'][0]['message']['content'];

        // 返回预览内容（不保存）
        wp_send_json_success(array(
            'new_content' => $new_content,
            'operation_type' => $operation_type
        ));
    }

    /**
     * AJAX: 撤回操作
     */
    public function undo_operation_ajax() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error('文章ID无效');
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('文章不存在');
            return;
        }

        // 获取保存的原始内容
        $original_content = get_post_meta($post_id, '_ai_cg_original_content', true);
        if (!$original_content) {
            wp_send_json_error('没有可撤回的操作');
            return;
        }

        // 恢复原始内容
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $original_content
        ));

        // 清除撤回标记
        delete_post_meta($post_id, '_ai_cg_original_content');
        delete_post_meta($post_id, '_ai_cg_operation_type');
        delete_post_meta($post_id, '_ai_cg_operation_timestamp');

        // 清除润色和排版时间标记
        delete_post_meta($post_id, '_ai_cg_polished_at');
        delete_post_meta($post_id, '_ai_cg_polish_style');
        delete_post_meta($post_id, '_ai_cg_reformatted_at');
        delete_post_meta($post_id, '_ai_cg_reformat_type');

        wp_send_json_success(array('content' => $original_content));
    }

    /**
     * AJAX: 批量排除文章
     */
    public function batch_exclude_ajax() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
            return;
        }

        $post_ids = isset($_POST['post_ids']) ? explode(',', $_POST['post_ids']) : array();

        if (empty($post_ids)) {
            wp_send_json_error('没有选择文章');
            return;
        }

        // 获取当前排除文章ID列表
        $excluded_posts = get_option('ai_cg_excluded_posts', '');
        $excluded_post_ids = array_filter(array_map('trim', explode(',', $excluded_posts)));

        // 添加新的文章ID
        $added_count = 0;
        foreach ($post_ids as $post_id) {
            $post_id = intval($post_id);
            if ($post_id && !in_array(strval($post_id), $excluded_post_ids)) {
                $excluded_post_ids[] = strval($post_id);
                $added_count++;
            }
        }

        // 保存更新后的列表
        update_option('ai_cg_excluded_posts', implode(',', $excluded_post_ids));

        wp_send_json_success(array(
            'added' => $added_count,
            'total' => count($excluded_post_ids)
        ));
    }

    /**
     * AJAX: 从排除列表中移除文章
     */
    public function remove_from_excluded_ajax() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
            return;
        }

        $post_ids = isset($_POST['post_ids']) ? explode(',', $_POST['post_ids']) : array();

        if (empty($post_ids)) {
            wp_send_json_error('没有选择文章');
            return;
        }

        // 获取当前排除文章ID列表
        $excluded_posts = get_option('ai_cg_excluded_posts', '');
        $excluded_post_ids = array_filter(array_map('trim', explode(',', $excluded_posts)));

        // 移除指定的文章ID
        $removed_count = 0;
        foreach ($post_ids as $post_id) {
            $post_id = strval(intval($post_id));
            $index = array_search($post_id, $excluded_post_ids);
            if ($index !== false) {
                unset($excluded_post_ids[$index]);
                $removed_count++;
            }
        }

        // 保存更新后的列表
        update_option('ai_cg_excluded_posts', implode(',', $excluded_post_ids));

        wp_send_json_success(array(
            'removed' => $removed_count,
            'total' => count($excluded_post_ids)
        ));
    }

    /**
     * 下载图片并上传到媒体库
     */
    private function sideload_image($image_url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // 下载图片
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // 准备上传
        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );

        $id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return $id;
        }

        return $id;
    }
}
