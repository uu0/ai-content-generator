<?php
/**
 * 改进的API交互类 - 测试版本
 *
 * 主要改进：
 * 1. 改进模型类型判断逻辑
 * 2. 添加模型过滤器（排除不支持的模型）
 * 3. 修复缓存存储逻辑
 * 4. 添加模型黑名单
 */
class AI_Content_Generator_API_Improved {

    /**
     * 模型黑名单 - 这些模型会导致错误
     */
    private static $model_blacklist = array(
        // Embedding模型
        'BAAI/bge-*',
        'deepseek-ai/deepseek-embedding-*',
        'intfloat/*embedding*',
        'moka-ai/*embedding*',
        // Rerank模型
        'BAAI/bge-reranker-*',
        // Audio模型
        'openai/*audio*',
        'pyannote/*',
        // 其他不支持的模型类型
        '*thumbnail*',
        '*upscale*',
        '*edit*',
    );

    /**
     * 图片模型关键词列表
     */
    private static $image_model_keywords = array(
        'flux', 'stable', 'diffusion', 'sd', 'sdxl',
        'midjourney', 'dall-e', 'imagen', 'stylegan'
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

        foreach ($models as $model) {
            $model_id = $model['id'];
            $display_name = isset($model['display_name']) ? $model['display_name'] : $model_id;

            // 检查是否在黑名单中
            if ($this->is_model_blacklisted($model_id)) {
                error_log('AI Content Generator: 跳过黑名单模型 - ' . $model_id);
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

        error_log('AI Content Generator: 模型列表获取成功 - 聊天模型: ' . count($chat_models) . ', 图片模型: ' . count($image_models));

        return array(
            'success' => true,
            'chat' => $chat_models,
            'image' => $image_models,
            'total' => count($chat_models) + count($image_models)
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
            $regex = str_replace('*', '.*', preg_quote($pattern, '/'));

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

        // 方法1: 检查是否明确是图片模型（通过关键词）
        foreach (self::$image_model_keywords as $keyword) {
            if (strpos($model_id, $keyword) !== false || strpos($display_name, $keyword) !== false) {
                return 'image';
            }
        }

        // 方法2: 检查是否排除图片模型（通过关键词）
        $non_image_keywords = array('chat', 'instruct', 'llm', 'gpt', 'qwen', 'deepseek', 'llama');
        foreach ($non_image_keywords as $keyword) {
            if (strpos($model_id, $keyword) !== false || strpos($display_name, $keyword) !== false) {
                return 'chat';
            }
        }

        // 方法3: 使用API返回的capabilities字段（如果存在）
        if (isset($model['capabilities']) && is_array($model['capabilities'])) {
            if (in_array('image', $model['capabilities'])) {
                return 'image';
            }
            if (in_array('text', $model['capabilities']) || in_array('chat', $model['capabilities'])) {
                return 'chat';
            }
        }

        // 方法4: 使用object字段判断（如果存在）
        if (isset($model['object'])) {
            if ($model['object'] === 'image-generation') {
                return 'image';
            }
            if ($model['object'] === 'text-generation' || $model['object'] === 'chat') {
                return 'chat';
            }
        }

        // 默认返回未知，让调用者处理
        return 'unknown';
    }

    /**
     * 获取可用模型列表（修复缓存逻辑）
     */
    public function get_available_models($force_refresh = false) {
        $cache_key = 'ai_cg_available_models';
        $timestamp_key = 'ai_cg_models_last_update';

        // 检查缓存是否存在
        $cached_models = get_option($cache_key, null);
        $last_update = get_option($timestamp_key, 0);

        // 如果强制刷新，或缓存过期（24小时），或缓存为空，从API获取
        $cache_expires = 24 * 60 * 60; // 24小时
        $should_refresh = $force_refresh || empty($cached_models) || (current_time('timestamp') - $last_update > $cache_expires);

        if ($should_refresh) {
            $result = $this->fetch_models_from_api();
            if (!is_wp_error($result) && isset($result['success'])) {
                // 正确存储缓存
                $cache_data = array(
                    'chat' => $result['chat'],
                    'image' => $result['image'],
                    'last_updated' => current_time('timestamp')
                );
                update_option($cache_key, $cache_data);
                update_option($timestamp_key, current_time('timestamp'));

                error_log('AI Content Generator: 模型列表已刷新');
                return $cache_data;
            } else {
                error_log('AI Content Generator: 刷新模型列表失败，使用缓存');
            }
        }

        // 返回缓存数据
        if ($cached_models && is_array($cached_models)) {
            return $cached_models;
        }

        // 如果所有方法都失败，返回默认模型列表
        return $this->get_default_models();
    }

    /**
     * 获取默认模型列表（兜底方案）
     */
    private function get_default_models() {
        return array(
            'chat' => array(
                'deepseek-chat' => 'DeepSeek Chat',
                'deepseek-coder' => 'DeepSeek Coder',
                'Qwen/Qwen2.5-7B-Instruct' => 'Qwen 2.5 7B',
                'Qwen/Qwen2.5-72B-Instruct' => 'Qwen 2.5 72B',
                'meta-llama/Meta-Llama-3.1-8B-Instruct' => 'Llama 3.1 8B',
                'meta-llama/Meta-Llama-3.1-70B-Instruct' => 'Llama 3.1 70B',
            ),
            'image' => array(
                'black-forest-labs/FLUX.1-dev' => 'FLUX.1 Dev',
                'black-forest-labs/FLUX.1-schnell' => 'FLUX.1 Schnell',
                'stabilityai/stable-diffusion-3' => 'Stable Diffusion 3',
                'stabilityai/stable-diffusion-xl-base-1.0' => 'Stable Diffusion XL',
            ),
            'last_updated' => 0
        );
    }

    /**
     * 测试模型是否可用
     */
    public function test_model($model_id) {
        $api_key = $this->get_api_key();

        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'API密钥未配置');
        }

        // 先判断模型类型
        $models = $this->get_available_models();
        $model_type = 'chat'; // 默认

        if (isset($models['chat']) && isset($models['chat'][$model_id])) {
            $model_type = 'chat';
        } elseif (isset($models['image']) && isset($models['image'][$model_id])) {
            $model_type = 'image';
        }

        // 测试调用
        if ($model_type === 'chat') {
            return $this->test_chat_model($model_id);
        } else {
            return $this->test_image_model($model_id);
        }
    }

    /**
     * 测试聊天模型
     */
    private function test_chat_model($model_id) {
        $endpoint = 'https://api.siliconflow.cn/v1/chat/completions';

        $body = array(
            'model' => $model_id,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Hi'
                )
            ),
            'max_tokens' => 10
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            return array('success' => false, 'error' => $data['error']['message']);
        }

        return array('success' => true);
    }

    /**
     * 测试图片模型
     */
    private function test_image_model($model_id) {
        $endpoint = 'https://api.siliconflow.cn/v1/images/generations';

        $body = array(
            'model' => $model_id,
            'prompt' => 'Test',
            'image_size' => '1024x1024',
            'n' => 1
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            return array('success' => false, 'error' => $data['error']['message']);
        }

        return array('success' => true);
    }

    /**
     * 获取API密钥
     */
    private function get_api_key() {
        return get_option('ai_cg_api_key', '');
    }
}
