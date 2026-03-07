<?php
/**
 * Token统计类
 */
class AI_Content_Generator_Stats {

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
        add_action('admin_menu', array($this, 'add_stats_page'));
    }

    /**
     * 添加统计页面
     */
    public function add_stats_page() {
        add_submenu_page(
            'ai-content-generator',
            'Token统计',
            'Token统计',
            'manage_options',
            'ai-content-generator-stats',
            array($this, 'render_stats_page')
        );
    }

    /**
     * 渲染统计页面
     */
    public function render_stats_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_cg_token_stats';

        // 获取总数
        $total_stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total_requests,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(total_tokens) as total_tokens
            FROM {$table_name}
        ");

        // 按模型统计
        $model_stats = $wpdb->get_results("
            SELECT
                model,
                action,
                COUNT(*) as request_count,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(total_tokens) as total_tokens
            FROM {$table_name}
            GROUP BY model, action
            ORDER BY total_tokens DESC
        ");

        // 按日期统计（最近30天）
        $daily_stats = $wpdb->get_results("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as request_count,
                SUM(total_tokens) as total_tokens
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");

        include AI_CONTENT_GENERATOR_PLUGIN_DIR . 'templates/stats-page.php';
    }

    /**
     * 记录Token使用
     */
    public function record_token_usage($post_id, $action, $model, $api_response) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_cg_token_stats';

        $usage = $this->parse_usage_from_response($api_response);

        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'action' => $action,
                'model' => $model,
                'input_tokens' => $usage['input_tokens'],
                'output_tokens' => $usage['output_tokens'],
                'total_tokens' => $usage['total_tokens'],
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%d', '%d', '%s')
        );

        return $wpdb->insert_id;
    }

    /**
     * 从API响应中解析Token使用情况
     */
    private function parse_usage_from_response($api_response) {
        $usage = array(
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0
        );

        if (isset($api_response['usage'])) {
            $usage['input_tokens'] = isset($api_response['usage']['prompt_tokens']) ? $api_response['usage']['prompt_tokens'] : 0;
            $usage['output_tokens'] = isset($api_response['usage']['completion_tokens']) ? $api_response['usage']['completion_tokens'] : 0;
            $usage['total_tokens'] = isset($api_response['usage']['total_tokens']) ? $api_response['usage']['total_tokens'] : 0;
        }

        // 图片生成没有token统计，按1次请求计算
        if (isset($api_response['data']) && isset($api_response['data'][0]['url'])) {
            $usage['input_tokens'] = 1;
            $usage['output_tokens'] = 1;
            $usage['total_tokens'] = 1;
        }

        return $usage;
    }

    /**
     * 获取文章的所有Token记录
     */
    public function get_post_token_history($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_cg_token_stats';

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name}
            WHERE post_id = %d
            ORDER BY created_at DESC
        ", $post_id));
    }

    /**
     * 清除统计数据
     */
    public function clear_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_cg_token_stats';
        $wpdb->query("TRUNCATE TABLE {$table_name}");
    }
}
