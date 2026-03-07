<?php
/**
 * API交互类
 */
class AI_Content_Generator_API {

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
        // 初始化
    }

    /**
     * 获取API密钥
     */
    private function get_api_key() {
        return get_option('ai_cg_api_key', '');
    }

    /**
     * 生成文章摘要
     */
    public function generate_summary($post_content, $post_title = '') {
        $api_key = $this->get_api_key();
        $model = get_option('ai_cg_summary_model', 'deepseek-chat');

        if (empty($api_key) || empty($model)) {
            return new WP_Error('no_api_key', 'API密钥或模型未配置');
        }

        $prompt = "请为以下文章生成一个简洁的摘要（100-200字）：\n\n";
        if (!empty($post_title)) {
            $prompt .= "文章标题：{$post_title}\n\n";
        }
        $prompt .= "文章内容：\n" . wp_trim_words($post_content, 500);

        $response = $this->call_chat_api($model, $prompt);

        return $response;
    }

    /**
     * 生成特色图片
     */
    public function generate_featured_image($post_content, $post_title = '') {
        $api_key = $this->get_api_key();
        $model = get_option('ai_cg_image_model', 'stable-diffusion-3');

        if (empty($api_key) || empty($model)) {
            return new WP_Error('no_api_key', 'API密钥或模型未配置');
        }

        // 提取关键信息生成提示词
        $content_preview = wp_trim_words($post_content, 100);
        $prompt = "Create a professional, modern, and visually appealing image related to: ";

        if (!empty($post_title)) {
            $prompt .= $post_title;
        } else {
            $prompt .= $content_preview;
        }

        $prompt .= ". Style: clean, minimalist, professional. High quality, 4K resolution.";

        $response = $this->call_image_api($model, $prompt);

        return $response;
    }

    /**
     * 调用聊天API
     */
    private function call_chat_api($model, $prompt) {
        $api_key = $this->get_api_key();
        $endpoint = 'https://api.siliconflow.cn/v1/chat/completions';

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 500,
            'temperature' => 0.7
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }

        // 记录Token统计
        $stats = AI_Content_Generator_Stats::get_instance();
        $stats->record_token_usage(0, 'generate_summary', $model, $data);

        return $data;
    }

    /**
     * 调用图片生成API
     */
    private function call_image_api($model, $prompt) {
        $api_key = $this->get_api_key();
        $endpoint = 'https://api.siliconflow.cn/v1/images/generations';

        $body = array(
            'model' => $model,
            'prompt' => $prompt,
            'image_size' => '1024x1024',
            'n' => 1
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 120
        ));

        if (is_wp_error($response)) {
            error_log('AI Content Generator: API请求失败 - ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // 记录原始响应用于调试
        error_log('AI Content Generator: 图片API响应 - ' . print_r($data, true));

        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }

        // 检查返回数据结构
        if (!isset($data['data']) || !isset($data['data'][0])) {
            return new WP_Error('invalid_response', 'API返回数据格式不正确: ' . print_r($data, true));
        }

        // 记录Token统计（图片生成通常按次数统计）
        $stats = AI_Content_Generator_Stats::get_instance();
        $stats->record_token_usage(0, 'generate_featured_image', $model, $data);

        return $data;
    }

    /**
     * 从API获取可用模型列表
     */
    public function fetch_models_from_api() {
        $api_key = $this->get_api_key();

        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'API密钥未配置');
        }

        $endpoint = 'https://api.siliconflow.cn/v1/models';

        $response = wp_remote_get($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', '无法解析API响应');
        }

        if (!isset($data['data'])) {
            return new WP_Error('invalid_response', 'API返回数据格式不正确');
        }

        // 分类模型
        $models = $data['data'];
        $chat_models = array();
        $image_models = array();

        foreach ($models as $model) {
            $model_id = $model['id'];
            $display_name = isset($model['display_name']) ? $model['display_name'] : $model_id;

            // 根据 ID 或 display_name 判断模型类型
            if (strpos(strtolower($model_id), 'flux') !== false ||
                strpos(strtolower($model_id), 'stable') !== false ||
                strpos(strtolower($model_id), 'sd') !== false ||
                strpos(strtolower($display_name), 'flux') !== false ||
                strpos(strtolower($display_name), 'stable') !== false ||
                strpos(strtolower($display_name), 'diffusion') !== false) {
                // 图片生成模型
                $image_models[$model_id] = $display_name;
            } else {
                // 聊天模型
                $chat_models[$model_id] = $display_name;
            }
        }

        // 按名称排序
        asort($chat_models);
        asort($image_models);

        return array(
            'success' => true,
            'chat' => $chat_models,
            'image' => $image_models,
            'total' => count($chat_models) + count($image_models)
        );
    }

    /**
     * 获取可用模型列表（从缓存或API）
     */
    public function get_available_models($force_refresh = false) {
        // 如果强制刷新，从API获取
        if ($force_refresh) {
            $result = $this->fetch_models_from_api();
            if (!is_wp_error($result) && isset($result['success'])) {
                // 缓存结果
                update_option('ai_cg_available_models', $result['chat'], $result['image']);
                // 更新时间戳
                update_option('ai_cg_models_last_update', current_time('timestamp'));
                return $result;
            }
        }

        // 尝试从缓存获取
        $cached_models = get_option('ai_cg_available_models', array());
        if (!empty($cached_models)) {
            return $cached_models;
        }

        // 如果没有缓存且不是强制刷新，尝试从API获取
        return $this->fetch_models_from_api();
    }

    /**
     * 获取模型列表（用于后台设置页面）
     */
    public function get_available_models_legacy() {
        // 可以从硅基流动API获取，这里先提供常用模型
        $chat_models = array(
            'deepseek-chat' => 'DeepSeek Chat',
            'deepseek-coder' => 'DeepSeek Coder',
            'Qwen/Qwen2.5-7B-Instruct' => 'Qwen 2.5 7B',
            'Qwen/Qwen2.5-72B-Instruct' => 'Qwen 2.5 72B',
            'meta-llama/Meta-Llama-3.1-8B-Instruct' => 'Llama 3.1 8B',
            'meta-llama/Meta-Llama-3.1-70B-Instruct' => 'Llama 3.1 70B',
        );

        $image_models = array(
            'black-forest-labs/FLUX.1-dev' => 'FLUX.1 Dev',
            'black-forest-labs/FLUX.1-schnell' => 'FLUX.1 Schnell',
            'stabilityai/stable-diffusion-3' => 'Stable Diffusion 3',
            'stabilityai/stable-diffusion-xl-base-1.0' => 'Stable Diffusion XL',
        );

        return array(
            'chat' => $chat_models,
            'image' => $image_models
        );
    }
}
