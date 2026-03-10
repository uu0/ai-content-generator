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
     * 获取 Base URL
     */
    private function get_base_url() {
        $base_url = get_option('ai_cg_base_url', 'https://api.siliconflow.cn/v1');
        return rtrim($base_url, '/');
    }

    /**
     * 获取 API 类型
     */
    private function get_api_type() {
        return get_option('ai_cg_api_type', 'openai');
    }

    /**
     * 测试 API 连接
     */
    public function test_api_connection($base_url = null, $api_key = null, $api_type = null, $model = null) {
        $base_url = $base_url ?: $this->get_base_url();
        $api_key = $api_key ?: $this->get_api_key();
        $api_type = $api_type ?: $this->get_api_type();
        $model = $model ?: get_option('ai_cg_summary_model', 'deepseek-chat');

        if (empty($api_key) || empty($model)) {
            return new WP_Error('missing_config', 'API Key 或模型名称未配置');
        }

        // 根据 API 类型构建请求
        if ($api_type === 'claude') {
            return $this->test_claude_api($base_url, $api_key, $model);
        } else {
            return $this->test_openai_api($base_url, $api_key, $model);
        }
    }

    /**
     * 测试 OpenAI 格式 API
     */
    private function test_openai_api($base_url, $api_key, $model) {
        $endpoint = $base_url . '/chat/completions';

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Hello'
                )
            ),
            'max_tokens' => 10
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : '未知错误';
            return new WP_Error('api_error', $error_message . ' (HTTP ' . $status_code . ')');
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', 'API 返回格式不正确');
        }

        return array('success' => true, 'message' => 'API 连接成功');
    }

    /**
     * 测试 Claude 格式 API
     */
    private function test_claude_api($base_url, $api_key, $model) {
        $endpoint = $base_url . '/messages';

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Hello'
                )
            ),
            'max_tokens' => 10
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : '未知错误';
            return new WP_Error('api_error', $error_message . ' (HTTP ' . $status_code . ')');
        }

        if (!isset($data['content'][0]['text'])) {
            return new WP_Error('invalid_response', 'API 返回格式不正确');
        }

        return array('success' => true, 'message' => 'API 连接成功');
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

        // 使用自定义提示词或默认提示词
        $custom_prompt = get_option('ai_cg_summary_prompt', '');
        if (!empty($custom_prompt)) {
            // 使用自定义提示词，替换占位符
            $prompt = $custom_prompt;

            // 替换占位符（如果存在）
            if (!empty($post_title)) {
                $prompt = str_replace('{title}', $post_title, $prompt);
            }
            $content_text = wp_trim_words($post_content, 500);
            $prompt = str_replace('{content}', $content_text, $prompt);

            // 如果自定义提示词中没有 {content} 占位符，自动在末尾追加文章内容
            if (strpos($custom_prompt, '{content}') === false) {
                if (!empty($post_title)) {
                    $prompt .= "\n\n文章标题：{$post_title}";
                }
                $prompt .= "\n\n文章内容：\n" . $content_text;
            }
        } else {
            // 使用默认提示词
            $prompt = "请为以下文章生成一个简洁的摘要（100-200字）：\n\n";
            if (!empty($post_title)) {
                $prompt .= "文章标题：{$post_title}\n\n";
            }
            $prompt .= "文章内容：\n" . wp_trim_words($post_content, 500);
        }

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

        // 使用自定义提示词或默认提示词
        $custom_prompt = get_option('ai_cg_image_prompt', '');
        if (!empty($custom_prompt)) {
            // 使用自定义提示词，替换占位符
            $prompt = $custom_prompt;

            // 替换占位符（如果存在）
            if (!empty($post_title)) {
                $prompt = str_replace('{title}', $post_title, $prompt);
            }
            $content_preview = wp_trim_words($post_content, 100);
            $prompt = str_replace('{content}', $content_preview, $prompt);

            // 如果自定义提示词中没有 {content} 占位符，自动在末尾追加内容上下文
            if (strpos($custom_prompt, '{content}') === false) {
                if (!empty($post_title)) {
                    // 优先使用标题
                    $prompt .= " Topic: {$post_title}";
                } else {
                    // 其次使用内容预览
                    $prompt .= " Topic: {$content_preview}";
                }
            }
        } else {
            // 使用默认提示词
            // 提取关键信息生成提示词
            $content_preview = wp_trim_words($post_content, 100);
            $prompt = "Create a professional, modern, and visually appealing image related to: ";

            if (!empty($post_title)) {
                $prompt .= $post_title;
            } else {
                $prompt .= $content_preview;
            }

            $prompt .= ". Style: clean, minimalist, professional. High quality, 4K resolution.";
        }

        $response = $this->call_image_api($model, $prompt);

        return $response;
    }

    /**
     * 调用聊天API
     */
    private function call_chat_api($model, $prompt, $max_tokens = 500, $temperature = 0.7) {
        $api_key = $this->get_api_key();
        $base_url = $this->get_base_url();
        $api_type = $this->get_api_type();

        if ($api_type === 'claude') {
            return $this->call_claude_chat_api($base_url, $api_key, $model, $prompt, $max_tokens, $temperature);
        } else {
            return $this->call_openai_chat_api($base_url, $api_key, $model, $prompt, $max_tokens, $temperature);
        }
    }

    /**
     * 调用 OpenAI 格式聊天 API
     */
    private function call_openai_chat_api($base_url, $api_key, $model, $prompt, $max_tokens, $temperature) {
        $endpoint = $base_url . '/chat/completions';

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $max_tokens,
            'temperature' => $temperature
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
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }

        // 记录Token统计
        $stats = AI_Content_Generator_Stats::get_instance();
        $stats->record_token_usage(0, 'generate_summary', $model, $data);

        return $data;
    }

    /**
     * 调用 Claude 格式聊天 API
     */
    private function call_claude_chat_api($base_url, $api_key, $model, $prompt, $max_tokens, $temperature) {
        $endpoint = $base_url . '/messages';

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $max_tokens,
            'temperature' => $temperature
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 120
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }

        // 转换 Claude 响应格式为 OpenAI 格式（兼容现有代码）
        if (isset($data['content'][0]['text'])) {
            $data['choices'] = array(
                array(
                    'message' => array(
                        'content' => $data['content'][0]['text']
                    )
                )
            );
        }

        // 记录Token统计
        $stats = AI_Content_Generator_Stats::get_instance();
        $stats->record_token_usage(0, 'generate_summary', $model, $data);

        return $data;
    }

    /**
     * Markdown转HTML（简化版）
     */
    private function markdown_to_html($markdown) {
        // 如果已经包含HTML标签，直接返回
        if (preg_match('/<[^>]+>/', $markdown)) {
            return $markdown;
        }

        $html = $markdown;

        // 标题转换 (将markdown标题转换为HTML h2-h4，wordpress中h1保留给页面标题)
        $html = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#\s+(.+)$/m', '<h2>$1</h2>', $html); // h1转换为h2

        // 代码块转换
        $html = preg_replace('/```\s*([\s\S]*?)```/', '<pre><code>$1</code></pre>', $html);
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // 加粗和斜体
        $html = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // 分割线
        $html = preg_replace('/^-{3,}$/m', '<hr />', $html);

        // 列表转换（无序列表）
        $html = preg_replace('/^\* (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

        // 有序列表
        $html = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $html);

        // 段落换行
        $html = preg_replace('/\n\n/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';

        // 清理多余的段落标签
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);
        $html = preg_replace('/<p>(<ul>)/', '$1', $html);
        $html = preg_replace('/(<\/ul>)<\/p>/', '$1', $html);
        $html = preg_replace('/<p>(<h[2-4]>)/', '$1', $html);
        $html = preg_replace('/(<\/h[2-4]>)<\/p>/', '$1', $html);
        $html = preg_replace('/<p>(<pre>)/', '$1', $html);
        $html = preg_replace('/(<\/pre>)<\/p>/', '$1', $html);

        return $html;
    }

    /**
     * 调用图片生成API
     */
    private function call_image_api($model, $prompt) {
        $api_key = $this->get_api_key();
        $base_url = $this->get_base_url();
        $endpoint = $base_url . '/images/generations';

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
     * 模型黑名单 - 这些模型会导致错误或不支持必要功能
     */
    private static $model_blacklist = array(
        // Embedding模型（不支持聊天）
        'BAAI/bge-*',
        'deepseek-ai/deepseek-embedding-*',
        'intfloat/*embedding*',
        'moka-ai/*embedding*',
        'BAAI/bge-m3',
        'BAAI/bge-large-*',
        'BAAI/bge-base-*',
        // Rerank模型（用于搜索排序，不支持聊天）
        'BAAI/bge-reranker-*',
        // Audio/语音模型
        'openai/whisper-*',
        'pyannote/*',
        // 其他不支持的模型
        '*thumbnail*', '*upscale*', '*edit*',
    );

    /**
     * 图片模型关键词列表
     */
    private static $image_model_keywords = array(
        'flux', 'stable', 'diffusion', 'sd', 'sdxl',
        'midjourney', 'dall-e', 'imagen', 'stylegan',
        'qwen-image', 'image-edit', 'qwen/image'
    );

    /**
     * 聊天模型关键词列表（用于排除错误的图片模型分类）
     */
    private static $chat_model_keywords = array(
        'chat', 'instruct', 'llm', 'gpt', 'deepseek', 'llama', 'mistral', 'gemma',
        'glm', 'kimi', 'pro/zai-org', 'pro/moonshotai',
        'qwen-instruct', 'qwen-chat', 'qwen-plus', 'qwen-turbo', 'qwen-max'
    );

    /**
     * 从API获取可用模型列表（改进版）
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
            error_log('AI Content Generator: 获取模型列表失败 - ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('AI Content Generator: API响应解析失败 - ' . json_last_error_msg());
            return new WP_Error('json_decode_error', '无法解析API响应: ' . json_last_error_msg());
        }

        if (!isset($data['data'])) {
            error_log('AI Content Generator: API返回数据格式不正确 - ' . print_r($data, true));
            return new WP_Error('invalid_response', 'API返回数据格式不正确');
        }

        // 分类模型
        $models = $data['data'];
        $chat_models = array();
        $image_models = array();
        $skipped_models = 0;

        foreach ($models as $model) {
            $model_id = $model['id'];
            $display_name = isset($model['display_name']) ? $model['display_name'] : $model_id;

            // 检查是否在黑名单中
            if ($this->is_model_blacklisted($model_id)) {
                $skipped_models++;
                continue;
            }

            // 改进的模型类型判断
            $model_type = $this->determine_model_type($model);

            if ($model_type === 'image') {
                // 图片生成模型
                $image_models[$model_id] = $display_name;
            } elseif ($model_type === 'chat') {
                // 聊天模型
                $chat_models[$model_id] = $display_name;
            } else {
                // 未知类型，默认为聊天模型
                $chat_models[$model_id] = $display_name;
                error_log('AI Content Generator: 模型类型未知，归类为聊天 - ' . $model_id);
            }
        }

        // 按名称排序
        asort($chat_models);
        asort($image_models);

        error_log('AI Content Generator: 模型列表获取成功 - 聊天模型: ' . count($chat_models) . ', 图片模型: ' . count($image_models) . ', 跳过模型: ' . $skipped_models);

        return array(
            'success' => true,
            'chat' => $chat_models,
            'image' => $image_models,
            'total' => count($chat_models) + count($image_models),
            'skipped' => $skipped_models
        );
    }

    /**
     * 判断模型是否在黑名单中
     */
    private function is_model_blacklisted($model_id) {
        $model_id_lower = strtolower($model_id);

        foreach (self::$model_blacklist as $pattern) {
            $pattern_lower = strtolower($pattern);
            // 将 * 替换为正则表达式的 .*
            $regex = str_replace('*', '.*', preg_quote($pattern_lower, '/'));

            if (preg_match('/' . $regex . '/i', $model_id_lower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 改进的模型类型判断
     */
    private function determine_model_type($model) {
        $model_id = strtolower($model['id']);
        $display_name = isset($model['display_name']) ? strtolower($model['display_name']) : '';

        // 步骤1: 检查是否明确是图片模型（通过关键词）
        foreach (self::$image_model_keywords as $keyword) {
            if (strpos($model_id, $keyword) !== false || strpos($display_name, $keyword) !== false) {
                return 'image';
            }
        }

        // 步骤2: 检查是否明确是聊天模型（通过关键词）
        foreach (self::$chat_model_keywords as $keyword) {
            if (strpos($model_id, $keyword) !== false || strpos($display_name, $keyword) !== false) {
                return 'chat';
            }
        }

        // 步骤3: 使用API返回的capabilities字段（如果存在）
        if (isset($model['capabilities']) && is_array($model['capabilities'])) {
            if (in_array('image', $model['capabilities'])) {
                return 'image';
            }
            if (in_array('text', $model['capabilities']) || in_array('chat', $model['capabilities'])) {
                return 'chat';
            }
        }

        // 步骤4: 使用object字段判断（如果存在）
        if (isset($model['object'])) {
            if ($model['object'] === 'image-generation') {
                return 'image';
            }
            if ($model['object'] === 'text-generation' || $model['object'] === 'chat') {
                return 'chat';
            }
        }

        // 步骤5: 根据常见命名规则判断
        // 如果包含 embedding, rerank, audio, whisper 等关键词，跳过
        $skip_keywords = array('embedding', 'rerank', 'audio', 'whisper', 'tts', 'stt', 'asr');
        foreach ($skip_keywords as $keyword) {
            if (strpos($model_id, $keyword) !== false) {
                return 'skip';
            }
        }

        // 步骤6: 检查是否包含多模态模型的特征
        // GLM系列、Kimi系列等多模态模型默认归类为聊天模型
        // 注意：Qwen系列既有聊天模型也有图片模型，不在此处默认归类，已在步骤1和步骤2中精确识别
        $multimodal_keywords = array('glm', 'kimi', 'deepseek-vl', 'gpt-4-vision', 'claude-3');
        foreach ($multimodal_keywords as $keyword) {
            if (strpos($model_id, $keyword) !== false || strpos($display_name, $keyword) !== false) {
                return 'chat';
            }
        }

        // 默认未知
        return 'unknown';
    }

    /**
     * 获取可用模型列表（从缓存或API） - 修复缓存逻辑
     */
    public function get_available_models($force_refresh = false) {
        $cache_key = 'ai_cg_available_models';
        $timestamp_key = 'ai_cg_models_last_update';

        // 检查缓存是否存在
        $cached_models = get_option($cache_key, null);
        $last_update = get_option($timestamp_key, 0);

        // 缓存过期时间：24小时
        $cache_expires = 24 * 60 * 60;

        // 判断是否需要刷新
        $should_refresh = $force_refresh ||
                         empty($cached_models) ||
                         (current_time('timestamp') - $last_update > $cache_expires);

        if ($should_refresh) {
            $result = $this->fetch_models_from_api();
            if (!is_wp_error($result) && isset($result['success'])) {
                // 正确存储缓存数据
                $cache_data = array(
                    'chat' => $result['chat'],
                    'image' => $result['image'],
                    'total' => $result['total'],
                    'last_updated' => current_time('timestamp')
                );
                update_option($cache_key, $cache_data);
                update_option($timestamp_key, current_time('timestamp'));

                error_log('AI Content Generator: 模型列表已刷新 - 总计: ' . $result['total'] . ' 个模型');
                return $cache_data;
            } else {
                error_log('AI Content Generator: 刷新模型列表失败，使用缓存');
            }
        }

        // 返回缓存数据
        if ($cached_models && is_array($cached_models) && isset($cached_models['chat']) && isset($cached_models['image'])) {
            return $cached_models;
        }

        // 如果所有方法都失败，返回默认模型列表
        error_log('AI Content Generator: 缓存无效，使用默认模型列表');
        return $this->get_available_models_legacy();
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

    /**
     * AI润色功能
     */
    public function polish_content($content, $style = 'normal') {
        $api_key = $this->get_api_key();
        $model = get_option('ai_cg_polish_model', 'deepseek-chat');

        if (empty($api_key) || empty($model)) {
            return new WP_Error('no_api_key', 'API密钥或润色模型未配置');
        }

        // 根据风格选择提示词（要求返回HTML格式，保留原有结构）
        $style_prompts = array(
            'formal' => get_option('ai_cg_polish_prompt_formal', '请将以下内容改写为正式、专业的书面语风格，保持原意不变，保留原有的HTML结构，并返回WordPress富文本编辑器可识别的HTML格式。如果内容中包含Markdown格式，请先转换为HTML格式：\n\n重要要求：\n1. 【严禁使用Markdown格式】必须返回纯HTML格式\n2. 保留所有原有的HTML标签结构（如h2、h3、p、ul、ol、li、table、pre、code、strong、em等）\n3. 只改写文本内容为正式风格，不添加或删除任何标签\n4. 保持标题层级和段落结构\n5. 确保HTML格式正确，可直接在WordPress编辑器中使用\n6. 如果检测到Markdown格式（如 **text**, `code`, ## 标题等）必须转换为对应的HTML格式\n\n润色后的内容：\n\n'),
            'casual' => get_option('ai_cg_polish_prompt_casual', '请将以下内容改写为轻松、友好的口语风格，保持原意不变，保留原有的HTML结构，并返回WordPress富文本编辑器可识别的HTML格式。如果内容中包含Markdown格式，请先转换为HTML格式：\n\n重要要求：\n1. 【严禁使用Markdown格式】必须返回纯HTML格式\n2. 保留所有原有的HTML标签结构（如h2、h3、p、ul、ol、li、blockquote等）\n3. 只改写文本内容为轻松风格，不添加或删除任何标签\n4. 保持标题层级和段落结构\n5. 确保HTML格式正确，可直接在WordPress编辑器中使用\n6. 如果检测到Markdown格式（如 **text**, `code`, ## 标题等）必须转换为对应的HTML格式\n\n润色后的内容：\n\n'),
            'creative' => get_option('ai_cg_polish_prompt_creative', '请将以下内容改写为富有创意和吸引力的风格，保持原意不变，保留原有的HTML结构，并返回WordPress富文本编辑器可识别的HTML格式。如果内容中包含Markdown格式，请先转换为HTML格式：\n\n重要要求：\n1. 【严禁使用Markdown格式】必须返回纯HTML格式\n2. 保留所有原有的HTML标签结构（如h2、h3、p、ul、ol、li等）\n3. 只改写文本内容为创意风格，不添加或删除任何标签\n4. 保持标题层级和段落结构\n5. 确保HTML格式正确，可直接在WordPress编辑器中使用\n6. 如果检测到Markdown格式（如 **text**, `code`, ## 标题等）必须转换为对应的HTML格式\n\n润色后的内容：\n\n'),
            'normal' => get_option('ai_cg_polish_prompt_normal', '请对以下内容进行润色，改善表达流畅度和可读性，保持原意不变，保留原有的HTML结构，并返回WordPress富文本编辑器可识别的HTML格式。如果内容中包含Markdown格式，请先转换为HTML格式：\n\n重要要求：\n1. 【严禁使用Markdown格式】必须返回纯HTML格式\n2. 保留所有原有的HTML标签结构（如h2、h3、p、ul、ol、li、table、pre、code、strong、em等）\n3. 只改善文本表达流畅度，不添加或删除任何标签\n4. 保持标题层级和段落结构\n5. 确保HTML格式正确，可直接在WordPress编辑器中使用\n6. 如果检测到Markdown格式（如 **text**, `code`, ## 标题等）必须转换为对应的HTML格式\n\n润色后的内容：\n\n')
        );

        $prompt = isset($style_prompts[$style]) ? $style_prompts[$style] : $style_prompts['normal'];
        $prompt .= $content;

        // 计算所需的最大token数（内容越长，需要的token越多）
        $content_length = mb_strlen($content);
        $max_tokens = max(2000, min(8000, $content_length * 2)); // 根据内容长度动态调整

        $response = $this->call_chat_api($model, $prompt, $max_tokens, 0.7);

        if (is_wp_error($response)) {
            return $response;
        }

        // 检查响应并添加markdown转HTML转换
        if (isset($response['choices'][0]['message']['content'])) {
            $polished_content = $response['choices'][0]['message']['content'];
            // 检测是否包含markdown格式，如果是则转换为HTML
            if ($this->has_markdown_format($polished_content)) {
                $polished_content = $this->markdown_to_html($polished_content);
                error_log('AI Content Generator: 检测到Markdown格式，已转换为HTML - polish');
            }
            $response['choices'][0]['message']['content'] = $polished_content;
        }

        return $response;
    }

    /**
     * AI排版功能
     */
    public function reformat_content($content, $format_type = 'standard') {
        $api_key = $this->get_api_key();
        $model = get_option('ai_cg_reformat_model', 'deepseek-chat');

        if (empty($api_key) || empty($model)) {
            return new WP_Error('no_api_key', 'API密钥或排版模型未配置');
        }

        // 根据格式类型选择提示词（要求返回HTML格式，不修改文字内容）
        $format_prompts = array(
            'standard' => get_option('ai_cg_reformat_prompt_standard', '请对以下内容进行排版优化，只调整HTML标签和格式，不修改任何文字内容，并返回WordPress富文本编辑器可识别的HTML格式。如果内容中包含Markdown格式，请转换为HTML格式：\n\n重要规则：\n1. 【严禁修改文字内容】保持所有原有文本完全不变\n2. 【严禁使用Markdown格式】必须返回纯HTML格式\n3. 【标题层级规范】将最大的标题设为<h2>，次级标题设为<h3>，依次降级（h2 > h3 > h4 > h5），不要出现<h1>\n4. 【表格处理】确保表格使用<table>、<thead>、<tbody>、<tr>、<th>、<td>标签\n5. 【代码处理】使用<pre><code>...</code></pre>标签包裹代码块，行内代码使用<code>\n6. 【列表处理】使用<ul>表示无序列表，<ol>表示有序列表，<li>表示列表项\n7. 【标签优化】确保所有标签正确闭合，使用<strong>和<em>进行强调\n8. 【段落处理】使用<p>标签包裹段落文本\n9. 如果检测到Markdown格式（如 **text**, `code`, ## 标题, - 列表等）必须转换为对应的HTML格式\n\n请只调整HTML标签结构，保持所有文字内容完全不变，直接返回排版后的HTML：\n\n'),
            'blog' => get_option('ai_cg_reformat_prompt_blog', '请将以下内容排版为博客文章格式，只调整HTML标签和格式，不修改任何文字内容，并返回WordPress富文本编辑器可识别的HTML格式。如果内容中包含Markdown格式，请转换为HTML格式：\n\n重要规则：\n1. 【严禁修改文字内容】保持所有原有文本完全不变\n2. 【严禁使用Markdown格式】必须返回纯HTML格式\n3. 【标题层级规范】文章主标题设为<h2>，次级标题设为<h3>，小标题设为<h4>\n4. 【表格处理】确保表格使用完整的HTML标签\n5. 【代码处理】代码块使用<pre><code>包裹\n6. 【列表处理】使用<ul>和<ol>标签\n7. 【博客特色】使用<blockquote>强调重点内容，保持流畅阅读体验\n8. 如果检测到Markdown格式（如 **text**, `code`, ## 标题, - 列表等）必须转换为对应的HTML格式\n\n请只调整HTML标签结构用于博客，保持所有文字内容完全不变，直接返回排版后的HTML：\n\n'),
            'technical' => get_option('ai_cg_reformat_prompt_technical', '请将以下内容排版为技术文档格式，只调整HTML标签和格式，不修改任何文字内容，并返回WordPress富文本编辑器可识别的HTML格式。如果内容中包含Markdown格式，请转换为HTML格式：\n\n重要规则：\n1. 【严禁修改文字内容】保持所有原有文本完全不变\n2. 【严禁使用Markdown格式】必须返回纯HTML格式\n3. 【标题层级规范】章节标题设为<h2>，子章节设为<h3>，小节设为<h4>\n4. 【表格处理】使用标准的<table>结构\n5. 【代码处理】代码块使用<pre><code>...</code></pre>，行内代码使用<code>\n6. 【列表处理】技术要点使用<ul>或<ol>列表清晰展示\n7. 【注释处理】使用<blockquote>添加注释或说明\n8. 【层级结构】严格使用h2->h3->h4层级\n9. 如果检测到Markdown格式（如 **text**, `code`, ## 标题, - 列表等）必须转换为对应的HTML格式\n\n请只调整HTML标签结构用于技术文档，保持所有文字内容完全不变，直接返回排版后的HTML：\n\n')
        );

        $prompt = isset($format_prompts[$format_type]) ? $format_prompts[$format_type] : $format_prompts['standard'];
        $prompt .= $content;

        // 计算所需的最大token数
        $content_length = mb_strlen($content);
        $max_tokens = max(2000, min(8000, $content_length * 2));

        $response = $this->call_chat_api($model, $prompt, $max_tokens, 0.3); // 降低temperature以保持更严格的格式

        if (is_wp_error($response)) {
            return $response;
        }

        // 检查响应并添加markdown转HTML转换
        if (isset($response['choices'][0]['message']['content'])) {
            $formatted_content = $response['choices'][0]['message']['content'];
            // 检测是否包含markdown格式，如果是则转换为HTML
            if ($this->has_markdown_format($formatted_content)) {
                $formatted_content = $this->markdown_to_html($formatted_content);
                error_log('AI Content Generator: 检测到Markdown格式，已转换为HTML - reformat');
            }
            $response['choices'][0]['message']['content'] = $formatted_content;
        }

        return $response;
    }

    /**
     * 检测内容是否包含Markdown格式
     */
    private function has_markdown_format($content) {
        // 检查常见的Markdown标记
        $markdown_patterns = array(
            '/^#+\s+/',  // 标题（# 或 ## 或 ### 等）
            '/\*\*[^*]+\*\*/',  // 加粗
            '/`[^`]+`/',  // 行内代码
            '/```\s*[\s\S]*?```/',  // 代码块
            '/^\*[^*]+[^\s]/m',  // 无序列表（* 符号）
            '/^-[^-]+[^\s]/m',  // 无序列表（- 符号）
            '/^\d+\.\s+/m',  // 有序列表
            '/\[([^\]]+)\]\(([^)]+)\)/',  // 链接
            '/!\[([^\]]*)\]\(([^)]+)\)/',  // 图片
        );

        foreach ($markdown_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查文章/页面是否应该被排除
     */
    public function is_post_excluded($post_id) {
        // 检查文章/页面ID是否在排除列表中
        $excluded_posts = get_option('ai_cg_excluded_posts', '');
        $excluded_post_ids = array_filter(array_map('trim', explode(',', $excluded_posts)));

        if (in_array(strval($post_id), $excluded_post_ids)) {
            return true;
        }

        // 获取文章/页面对象
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // 检查文章分类是否在排除列表中
        if ($post->post_type === 'post') {
            $excluded_categories = get_option('ai_cg_excluded_categories', '');
            $excluded_category_ids = array_filter(array_map('trim', explode(',', $excluded_categories)));

            if (!empty($excluded_category_ids)) {
                $post_categories = wp_get_post_categories($post_id);
                foreach ($post_categories as $cat_id) {
                    if (in_array(strval($cat_id), $excluded_category_ids)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
