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

        // 使用自定义提示词或默认提示词
        $custom_prompt = get_option('ai_cg_summary_prompt', '');
        if (!empty($custom_prompt)) {
            // 使用自定义提示词，替换占位符
            $prompt = $custom_prompt;
            if (!empty($post_title)) {
                $prompt = str_replace('{title}', $post_title, $prompt);
            }
            $prompt = str_replace('{content}', wp_trim_words($post_content, 500), $prompt);
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
            if (!empty($post_title)) {
                $prompt = str_replace('{title}', $post_title, $prompt);
            }
            $prompt = str_replace('{content}', wp_trim_words($post_content, 100), $prompt);
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

    /**
     * 从文章内容提取所有图片ID并生成描述并重命名
     * 用于特殊页面或有大量图片的文章
     */
    public function generate_descriptions_and_rename_images($post_id) {
        $api_key = $this->get_api_key();
        $model = get_option('ai_cg_image_description_model', 'deepseek-chat');

        if (empty($api_key) || empty($model)) {
            return new WP_Error('no_api_key', 'API密钥或模型未配置');
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', '文章不存在');
        }

        // 提取文章内容中的所有图片
        $images = $this->extract_images_from_content($post->post_content);
        if (empty($images)) {
            return new WP_Error('no_images', '文章中没有找到图片');
        }

        $results = array();
        $renamed_count = 0;

        // 使用提示词模板
        $prompt_template = get_option('ai_cg_image_description_prompt', '请为这张图片生成一句10字左右的描述：');

        // 为每张图片生成描述
        foreach ($images as $image) {
            $attachment_id = $image['attachment_id'];
            $image_path = get_attached_file($attachment_id);

            if (!$image_path || !file_exists($image_path)) {
                $results[] = array(
                    'attachment_id' => $attachment_id,
                    'status' => 'error',
                    'message' => '图片文件不存在'
                );
                continue;
            }

            // 尝试从文章内容和上下文生成描述
            $context = wp_trim_words($post->post_content, 100);
            if (!empty($image['alt'])) {
                $context .= ' Alt文本: ' . $image['alt'];
            }
            if (!empty($image['title'])) {
                $context .= ' 标题: ' . $image['title'];
            }

            // 构建提示词（不使用图片，仅使用上下文信息）
            $prompt = $prompt_template . "\n\n上下文：" . $context;

            // 调用聊天API生成描述
            $response = $this->call_chat_api($model, $prompt);

            if (is_wp_error($response)) {
                $results[] = array(
                    'attachment_id' => $attachment_id,
                    'status' => 'error',
                    'message' => $response->get_error_message()
                );
                continue;
            }

            // 提取描述（限制10字左右）
            $description = $response['choices'][0]['message']['content'];
            $description = wp_trim_words($description, 3, ''); // 大约10-15个字

            // 替换文件名
            $rename_result = $this->rename_image_file($attachment_id, $description);
            if (is_wp_error($rename_result)) {
                $results[] = array(
                    'attachment_id' => $attachment_id,
                    'status' => 'error',
                    'message' => '重命名失败: ' . $rename_result->get_error_message()
                );
                continue;
            }

            $results[] = array(
                'attachment_id' => $attachment_id,
                'status' => 'success',
                'description' => $description,
                'new_filename' => $rename_result
            );
            $renamed_count++;
        }

        return array(
            'success' => true,
            'total' => count($images),
            'renamed' => $renamed_count,
            'results' => $results
        );
    }

    /**
     * 从HTML内容中提取所有图片
     */
    private function extract_images_from_content($content) {
        $images = array();

        // 匹配 <img> 标签
        if (preg_match_all('/<img[^>]+>/i', $content, $matches)) {
            foreach ($matches[0] as $img_tag) {
                // 提取src
                if (preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_match)) {
                    $src = $src_match[1];
                    $attachment_id = $this->get_attachment_id_from_url($src);

                    // 跳过外部图片和已处理的图片
                    if (!$attachment_id || isset($images[$attachment_id])) {
                        continue;
                    }

                    // 提取alt
                    $alt = '';
                    if (preg_match('/alt=["\']([^"\']*)["\']/i', $img_tag, $alt_match)) {
                        $alt = $alt_match[1];
                    }

                    // 提取title
                    $title = '';
                    if (preg_match('/title=["\']([^"\']*)["\']/i', $img_tag, $title_match)) {
                        $title = $title_match[1];
                    }

                    $images[$attachment_id] = array(
                        'attachment_id' => $attachment_id,
                        'src' => $src,
                        'alt' => $alt,
                        'title' => $title
                    );
                }
            }
        }

        // 匹配 [gallery] shortcode
        if (preg_match_all('/\[gallery[^\]]*\]/i', $content, $gallery_matches)) {
            foreach ($gallery_matches[0] as $gallery_shortcode) {
                if (preg_match('/ids=["\']([^"\']+)["\']/i', $gallery_shortcode, $id_match)) {
                    $ids = explode(',', $id_match[1]);
                    foreach ($ids as $id) {
                        $id = trim($id);
                        if ($id && !isset($images[$id])) {
                            $images[$id] = array(
                                'attachment_id' => $id,
                                'src' => wp_get_attachment_url($id),
                                'alt' => '',
                                'title' => ''
                            );
                        }
                    }
                }
            }
        }

        return array_values($images);
    }

    /**
     * 从URL获取attachment ID
     */
    private function get_attachment_id_from_url($url) {
        // 去除查询参数
        $url = strtok($url, '?');

        // 转换为相对路径
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];

        if (strpos($url, $base_url) === 0) {
            $relative_path = substr($url, strlen($base_url));
            $relative_path = ltrim($relative_path, '/');

            // 查找附件
            global $wpdb;
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
                $relative_path
            ));

            return $attachment_id ? $attachment_id : false;
        }

        return false;
    }

    /**
     * 重命名图片文件
     */
    private function rename_image_file($attachment_id, $description) {
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return new WP_Error('invalid_attachment', '无效的附件');
        }

        // 获取当前路径
        $old_path = get_attached_file($attachment_id);
        if (!file_exists($old_path)) {
            return new WP_Error('file_not_found', '文件不存在');
        }

        // 获取文件信息
        $path_info = pathinfo($old_path);
        $extension = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';

        // 清理描述字符串（移除特殊字符，只保留中英文和数字）
        $clean_description = preg_replace('/[^a-zA-Z0-9\u4e00-\u9fa5\s]/', '', $description);
        $clean_description = trim($clean_description);
        $clean_description = mb_substr($clean_description, 0, 50, 'UTF-8'); // 限制长度

        if (empty($clean_description)) {
            return new WP_Error('invalid_description', '描述无效');
        }

        // 构建新文件名
        $directory = $path_info['dirname'];
        $new_filename = $clean_description . $extension;
        $new_path = $directory . '/' . $new_filename;

        // 如果文件已存在，添加序号
        $counter = 1;
        while (file_exists($new_path)) {
            $new_filename = $clean_description . '-' . $counter . $extension;
            $new_path = $directory . '/' . $new_filename;
            $counter++;
        }

        // 重命名文件
        if (!rename($old_path, $new_path)) {
            return new WP_Error('rename_failed', '重命名失败');
        }

        // 更新数据库中的文件路径
        update_post_meta($attachment_id, '_wp_attached_file', $new_filename);

        // 更新媒体库记录
        wp_update_post(array(
            'ID' => $attachment_id,
            'post_title' => $description,
            'post_name' => sanitize_title($description)
        ));

        // 重新生成缩略图（WordPress会自动处理）
        $metadata = wp_generate_attachment_metadata($attachment_id, $new_path);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return $new_filename;
    }

    /**
     * 创建图片缩略图
     */
    private function get_thumbnail($image_path, $max_width = 1024, $max_height = 1024) {
        // 检查图片信息
        $image_info = @getimagesize($image_path);
        if (!$image_info) {
            return new WP_Error('invalid_image', '无法读取图片信息');
        }

        list($width, $height) = $image_info;

        // 如果图片已经足够小，直接返回原路径
        if ($width <= $max_width && $height <= $max_height) {
            return $image_path;
        }

        // 创建缩略图
        $editor = wp_get_image_editor($image_path);
        if (is_wp_error($editor)) {
            return $editor;
        }

        $editor->resize($max_width, $max_height, false);
        $thumbnail_path = $editor->generate_filename('-thumb');

        $saved = $editor->save($thumbnail_path);
        if (is_wp_error($saved)) {
            return $saved;
        }

        return $thumbnail_path;
    }

    /**
     * 将图片转为base64编码
     */
    private function encode_image_to_base64($image_path) {
        $image_data = file_get_contents($image_path);
        if ($image_data === false) {
            return new WP_Error('file_read_error', '无法读取图片文件');
        }

        $mimetype = mime_content_type($image_path);
        if (!$mimetype) {
            return new WP_Error('mime_error', '无法确定图片类型');
        }

        return 'data:' . $mimetype . ';base64,' . base64_encode($image_data);
    }

    /**
     * 调用视觉API（支持图片理解的模型）
     */
    private function call_vision_api($model, $prompt, $image_data) {
        $api_key = $this->get_api_key();
        $endpoint = 'https://api.siliconflow.cn/v1/chat/completions';

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => $prompt
                        ),
                        array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => $image_data
                            )
                        )
                    )
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

        $body_data = wp_remote_retrieve_body($response);
        $data = json_decode($body_data, true);

        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }

        // 记录Token统计
        $stats = AI_Content_Generator_Stats::get_instance();
        $stats->record_token_usage(0, 'generate_image_description', $model, $data);

        return $data;
    }

    /**
     * AI润色功能
     */
    public function polish_content($content, $style = 'normal') {
        $api_key = $this->get_api_key();
        $model = get_option('ai_cg_summary_model', 'deepseek-chat');

        if (empty($api_key) || empty($model)) {
            return new WP_Error('no_api_key', 'API密钥或模型未配置');
        }

        // 根据风格选择提示词（要求返回HTML格式，保留原有结构）
        $style_prompts = array(
            'formal' => get_option('ai_cg_polish_prompt_formal', '请将以下内容改写为正式、专业的书面语风格，保持原意不变，保留原有的HTML结构，并返回WordPress富文本编辑器可识别的HTML格式：\n\n要求：\n1. 保留所有原有的HTML标签结构（如h2、h3、p、ul、ol、li、table、pre、code等）\n2. 只改写文本内容为正式风格，不添加或删除任何标签\n3. 保持标题层级和段落结构\n4. 确保HTML格式正确，可直接在WordPress编辑器中使用\n\n润色后的内容：\n\n'),
            'casual' => get_option('ai_cg_polish_prompt_casual', '请将以下内容改写为轻松、友好的口语风格，保持原意不变，保留原有的HTML结构，并返回WordPress富文本编辑器可识别的HTML格式：\n\n要求：\n1. 保留所有原有的HTML标签结构（如h2、h3、p、ul、ol、li、blockqoute等）\n2. 只改写文本内容为轻松风格，不添加或删除任何标签\n3. 保持标题层级和段落结构\n4. 确保HTML格式正确，可直接在WordPress编辑器中使用\n\n润色后的内容：\n\n'),
            'creative' => get_option('ai_cg_polish_prompt_creative', '请将以下内容改写为富有创意和吸引力的风格，保持原意不变，保留原有的HTML结构，并返回WordPress富文本编辑器可识别的HTML格式：\n\n要求：\n1. 保留所有原有的HTML标签结构（如h2、h3、p、ul、ol、li等）\n2. 只改写文本内容为创意风格，不添加或删除任何标签\n3. 保持标题层级和段落结构\n4. 确保HTML格式正确，可直接在WordPress编辑器中使用\n\n润色后的内容：\n\n'),
            'normal' => get_option('ai_cg_polish_prompt_normal', '请对以下内容进行润色，改善表达流畅度和可读性，保持原意不变，保留原有的HTML结构，并返回WordPress富文本编辑器可识别的HTML格式：\n\n要求：\n1. 保留所有原有的HTML标签结构（如h2、h3、p、ul、ol、li、table、pre、code等）\n2. 只改善文本表达流畅度，不添加或删除任何标签\n3. 保持标题层级和段落结构\n4. 确保HTML格式正确，可直接在WordPress编辑器中使用\n\n润色后的内容：\n\n')
        );

        $prompt = isset($style_prompts[$style]) ? $style_prompts[$style] : $style_prompts['normal'];
        $prompt .= $content;

        $response = $this->call_chat_api($model, $prompt);

        return $response;
    }

    /**
     * AI排版功能
     */
    public function reformat_content($content, $format_type = 'standard') {
        $api_key = $this->get_api_key();
        $model = get_option('ai_cg_summary_model', 'deepseek-chat');

        if (empty($api_key) || empty($model)) {
            return new WP_Error('no_api_key', 'API密钥或模型未配置');
        }

        // 根据格式类型选择提示词（要求返回HTML格式，不修改文字内容）
        $format_prompts = array(
            'standard' => get_option('ai_cg_reformat_prompt_standard', '请对以下内容进行排版优化，只调整HTML标签和格式，不修改任何文字内容，并返回WordPress富文本编辑器可识别的HTML格式：\n\n重要规则：\n1. 【严禁修改文字内容】保持所有原有文本完全不变\n2. 【标题层级规范】将最大的标题设为<h2>，次级标题设为<h3>，依次降级（h2 > h3 > h4 > h5），不要出现<h1>\n3. 【表格处理】确保表格使用<table>、<thead>、<tbody>、<tr>、<th>、<td>标签\n4. 【代码处理】使用<pre><code>...</code></pre>标签包裹代码块，区分行内代码\n5. 【列表处理】使用<ul>表示无序列表，<ol>表示有序列表，<li>表示列表项\n6. 【标签优化】确保所有标签正确闭合，使用<strong>和<em>进行强调\n7. 【段落处理】使用<p>标签包裹段落文本\n8. 【格式规范】仅使用HTML格式，不使用Markdown\n\n请只调整HTML标签结构，保持所有文字内容完全不变，直接返回排版后的HTML：\n\n'),
            'blog' => get_option('ai_cg_reformat_prompt_blog', '请将以下内容排版为博客文章格式，只调整HTML标签和格式，不修改任何文字内容，并返回WordPress富文本编辑器可识别的HTML格式：\n\n重要规则：\n1. 【严禁修改文字内容】保持所有原有文本完全不变\n2. 【标题层级规范】文章主标题设为<h2>，次级标题设为<h3>，小标题设为<h4>\n3. 【表格处理】确保表格使用完整的HTML标签\n4. 【代码处理】代码块使用<pre><code>包裹\n5. 【列表处理】使用<ul>和<ol>标签\n6. 【博客特色】使用<blockquote>强调重点内容，保持流畅阅读体验\n7. 【格式规范】仅使用HTML格式，不使用Markdown\n\n请只调整HTML标签结构用于博客，保持所有文字内容完全不变，直接返回排版后的HTML：\n\n'),
            'technical' => get_option('ai_cg_reformat_prompt_technical', '请将以下内容排版为技术文档格式，只调整HTML标签和格式，不修改任何文字内容，并返回WordPress富文本编辑器可识别的HTML格式：\n\n重要规则：\n1. 【严禁修改文字内容】保持所有原有文本完全不变\n2. 【标题层级规范】章节标题设为<h2>，子章节设为<h3>，小节设为<h4>\n3. 【表格处理】使用标准的<table>结构\n4. 【代码处理】代码块使用<pre><code>...</code></pre>，行内代码使用<code>\n5. 【列表处理】技术要点使用<ul>或<ol>列表清晰展示\n6. 【注释处理】使用<blockquote>添加注释或说明\n7. 【层级结构】严格使用h2->h3->h4层级\n8. 【格式规范】仅使用HTML格式，不使用Markdown\n\n请只调整HTML标签结构用于技术文档，保持所有文字内容完全不变，直接返回排版后的HTML：\n\n')
        );

        $prompt = isset($format_prompts[$format_type]) ? $format_prompts[$format_type] : $format_prompts['standard'];
        $prompt .= $content;

        $response = $this->call_chat_api($model, $prompt);

        return $response;
    }

    /**
     * 检查文章是否应该被排除
     */
    public function is_post_excluded($post_id) {
        // 检查文章ID是否在排除列表中
        $excluded_posts = get_option('ai_cg_excluded_posts', '');
        $excluded_post_ids = array_filter(array_map('trim', explode(',', $excluded_posts)));

        if (in_array(strval($post_id), $excluded_post_ids)) {
            return true;
        }

        // 检查文章分类是否在排除列表中
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

        return false;
    }
}
