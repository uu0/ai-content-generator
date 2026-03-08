<?php
/**
 * 后台任务处理类
 */
class AI_Content_Generator_Background {

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
        // 定时任务钩子
        add_action('ai_cg_hourly_check', array($this, 'hourly_check_posts'));

        // 添加检查按钮到文章列表
        add_filter('post_row_actions', array($this, 'add_post_row_actions'), 10, 2);

        // AJAX处理生成请求
        add_action('wp_ajax_ai_cg_generate_summary', array($this, 'ajax_generate_summary'));
        add_action('wp_ajax_ai_cg_generate_featured_image', array($this, 'ajax_generate_featured_image'));
        add_action('wp_ajax_ai_cg_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_ai_cg_clear_stats', array($this, 'ajax_clear_stats'));
    }

    /**
     * 每小时检查文章
     */
    public function hourly_check_posts() {
        if (!get_option('ai_cg_auto_check_enabled', false)) {
            return;
        }

        // 查找没有摘要的文章
        if (get_option('ai_cg_summary_enabled', false)) {
            $posts_without_summary = $this->get_posts_without_summary(5);
            foreach ($posts_without_summary as $post) {
                $this->generate_summary_for_post($post->ID);
            }
        }

        // 查找没有特色图片的文章
        if (get_option('ai_cg_featured_image_enabled', false)) {
            $posts_without_image = $this->get_posts_without_featured_image(5);
            foreach ($posts_without_image as $post) {
                $this->generate_featured_image_for_post($post->ID);
            }
        }
    }

    /**
     * 获取没有摘要的文章
     */
    private function get_posts_without_summary($limit = 10) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
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
            )
        );

        // 应用排除规则
        $excluded_categories = get_option('ai_cg_excluded_categories', '');
        $excluded_category_ids = array_filter(array_map('trim', explode(',', $excluded_categories)));
        if (!empty($excluded_category_ids)) {
            $args['category__not_in'] = $excluded_category_ids;
        }

        $excluded_posts = get_option('ai_cg_excluded_posts', '');
        $excluded_post_ids = array_filter(array_map('trim', explode(',', $excluded_posts)));
        if (!empty($excluded_post_ids)) {
            $args['post__not_in'] = $excluded_post_ids;
        }

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * 获取没有特色图片的文章
     */
    private function get_posts_without_featured_image($limit = 10) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
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
            )
        );

        // 应用排除规则
        $excluded_categories = get_option('ai_cg_excluded_categories', '');
        $excluded_category_ids = array_filter(array_map('trim', explode(',', $excluded_categories)));
        if (!empty($excluded_category_ids)) {
            $args['category__not_in'] = $excluded_category_ids;
        }

        $excluded_posts = get_option('ai_cg_excluded_posts', '');
        $excluded_post_ids = array_filter(array_map('trim', explode(',', $excluded_posts)));
        if (!empty($excluded_post_ids)) {
            $args['post__not_in'] = $excluded_post_ids;
        }

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * 为文章生成摘要
     */
    private function generate_summary_for_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $api = AI_Content_Generator_API::get_instance();
        $response = $api->generate_summary($post->post_content, $post->post_title);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['choices'][0]['message']['content'])) {
            $summary = sanitize_textarea_field($response['choices'][0]['message']['content']);

            // 更新文章摘要
            wp_update_post(array(
                'ID' => $post_id,
                'post_excerpt' => $summary
            ));

            // 添加元数据记录
            update_post_meta($post_id, '_ai_cg_summary_generated', current_time('mysql'));
            update_post_meta($post_id, '_ai_cg_summary_model', get_option('ai_cg_summary_model'));

            return true;
        }

        return false;
    }

    /**
     * 为文章生成特色图片
     */
    private function generate_featured_image_for_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', '文章不存在');
        }

        $api = AI_Content_Generator_API::get_instance();
        $response = $api->generate_featured_image($post->post_content, $post->post_title);

        if (is_wp_error($response)) {
            error_log('AI Content Generator: 生成图片请求失败 - ' . $response->get_error_message());
            return $response;
        }

        // 检查API返回的数据结构
        if (!isset($response['data']) || !isset($response['data'][0])) {
            error_log('AI Content Generator: API返回数据格式不正确');
            return new WP_Error('invalid_response', 'API返回数据格式不正确');
        }

        // 尝试获取图片URL（兼容不同的返回格式）
        $image_url = null;
        if (isset($response['data'][0]['url'])) {
            $image_url = $response['data'][0]['url'];
        } elseif (isset($response['data'][0]['b64_json'])) {
            // 如果返回base64编码的图片
            $image_url = $this->save_base64_image($response['data'][0]['b64_json'], $post->post_title, $post_id);
        } else {
            error_log('AI Content Generator: 无法从响应中获取图片URL');
            return new WP_Error('no_image_url', '无法从API响应中获取图片URL');
        }

        if (!$image_url) {
            return new WP_Error('invalid_url', '图片URL为空');
        }

        // 如果image_url是临时文件路径（base64生成），直接使用
        if (strpos($image_url, 'http') !== 0) {
            $attachment_id = $this->save_temp_file_to_media($image_url, $post->post_title, $post_id);
        } else {
            // 下载图片到媒体库
            $attachment_id = $this->save_image_to_media($image_url, $post->post_title, $post_id);
        }

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        if (!$attachment_id) {
            return new WP_Error('upload_failed', '图片上传到媒体库失败');
        }

        // 设置为特色图片
        $set_result = set_post_thumbnail($post_id, $attachment_id);

        if (!$set_result) {
            return new WP_Error('set_thumbnail_failed', '设置特色图片失败');
        }

        // 添加元数据记录
        update_post_meta($post_id, '_ai_cg_image_generated', current_time('mysql'));
        update_post_meta($post_id, '_ai_cg_image_model', get_option('ai_cg_image_model'));

        return true;
    }

    /**
     * 保存base64编码的图片
     */
    private function save_base64_image($base64_string, $title, $post_id) {
        // 解码base64
        $image_data = base64_decode($base64_string);
        if (!$image_data) {
            return false;
        }

        // 创建临时文件
        $tmp = tempnam(sys_get_temp_dir(), 'ai_image');
        $filename = sanitize_file_name($title . '-' . time() . '.png');

        // 写入图片数据
        file_put_contents($tmp, $image_data);

        // 准备文件数组
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $tmp
        );

        return $tmp; // 返回临时文件路径
    }

    /**
     * 保存临时文件到媒体库
     */
    private function save_temp_file_to_media($temp_path, $title, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $filename = sanitize_file_name($title . '-' . time() . '.png');

        $file_array = array(
            'name' => $filename,
            'tmp_name' => $temp_path
        );

        $id = media_handle_sideload($file_array, $post_id, 'AI Generated Image - ' . $title);

        @unlink($temp_path); // 清理临时文件

        if (is_wp_error($id)) {
            error_log('AI Content Generator: 上传临时文件到媒体库失败 - ' . $id->get_error_message());
            return $id;
        }

        return $id;
    }

    /**
     * 保存图片到媒体库
     */
    private function save_image_to_media($image_url, $title, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // 获取图片文件扩展名
        $file_info = pathinfo($image_url);
        $extension = isset($file_info['extension']) ? $file_info['extension'] : 'png';

        // 如果URL中没有扩展名，尝试从响应头获取
        if (empty($extension) || strlen($extension) > 5) {
            $extension = 'png';
        }

        // 生成新的文件名
        $filename = sanitize_file_name($title . '-' . time() . '.' . $extension);

        // 下载图片
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            error_log('AI Content Generator: 下载图片失败 - ' . $tmp->get_error_message());
            return $tmp;
        }

        // 准备文件数组
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $tmp
        );

        // 上传到媒体库
        $id = media_handle_sideload($file_array, $post_id, 'AI Generated Image - ' . $title);

        // 清理临时文件
        @unlink($file_array['tmp_name']);

        if (is_wp_error($id)) {
            error_log('AI Content Generator: 上传到媒体库失败 - ' . $id->get_error_message());
            return $id;
        }

        return $id;
    }

    /**
     * 添加文章行操作
     */
    public function add_post_row_actions($actions, $post) {
        if ($post->post_type !== 'post') {
            return $actions;
        }

        $actions['ai_cg_generate_summary'] = sprintf(
            '<a href="#" class="ai-cg-generate-summary" data-post-id="%d">生成摘要</a>',
            $post->ID
        );

        $actions['ai_cg_generate_image'] = sprintf(
            '<a href="#" class="ai-cg-generate-image" data-post-id="%d">生成图片</a>',
            $post->ID
        );

        return $actions;
    }

    /**
     * AJAX生成摘要
     */
    public function ajax_generate_summary() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error('权限不足');
        }

        $result = $this->generate_summary_for_post($post_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('message' => '摘要生成成功'));
    }

    /**
     * AJAX生成特色图片
     */
    public function ajax_generate_featured_image() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error('权限不足');
        }

        $result = $this->generate_featured_image_for_post($post_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        if ($result === false) {
            wp_send_json_error('特色图片生成失败，请检查API响应');
        }

        wp_send_json_success(array('message' => '特色图片生成成功'));
    }

    /**
     * AJAX测试连接
     */
    public function ajax_test_connection() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        $api_key = get_option('ai_cg_api_key', '');

        if (empty($api_key)) {
            wp_send_json_error('API密钥未配置');
        }

        // 测试调用API
        $api = AI_Content_Generator_API::get_instance();
        $test_response = $api->generate_summary('测试内容', '测试标题');

        if (is_wp_error($test_response)) {
            wp_send_json_error($test_response->get_error_message());
        }

        wp_send_json_success(array('message' => '连接成功'));
    }

    /**
     * AJAX清除统计
     */
    public function ajax_clear_stats() {
        check_ajax_referer('ai_cg_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }

        $stats = AI_Content_Generator_Stats::get_instance();
        $stats->clear_stats();

        wp_send_json_success(array('message' => '统计数据已清除'));
    }
}
