<div class="wrap">
    <h1>AI内容生成 - 设置</h1>

    <form method="post" action="options.php">
        <?php settings_fields('ai_cg_settings'); ?>

        <div class="ai-cg-settings-container">
            <div class="ai-cg-setting-section">
                <h2>API配置</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">API密钥</th>
                        <td>
                            <input type="password" name="ai_cg_api_key" value="<?php echo esc_attr(get_option('ai_cg_api_key')); ?>" class="regular-text">
                            <p class="description">
                                请输入您的硅基流动API密钥。<a href="https://cloud.siliconflow.cn/i/H7S7dWHo" target="_blank">访问我的推荐链接获取初始额度</a> 或者 <a href="https://cloud.siliconflow.cn/" target="_blank">访问官网</a> 获取API密钥。
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ai-cg-setting-section">
                <h2>功能开关</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">启用摘要生成</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_cg_summary_enabled" value="1" <?php checked(get_option('ai_cg_summary_enabled'), 1); ?>>
                                启用自动生成文章摘要
                            </label>
                            <p class="description">启用后，系统会自动为没有摘要的文章生成摘要。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">启用特色图片生成</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_cg_featured_image_enabled" value="1" <?php checked(get_option('ai_cg_featured_image_enabled'), 1); ?>>
                                启用自动生成特色图片
                            </label>
                            <p class="description">启用后，系统会自动为没有特色图片的文章生成图片。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">启用自动检查</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_cg_auto_check_enabled" value="1" <?php checked(get_option('ai_cg_auto_check_enabled'), 1); ?>>
                                启用每小时自动检查
                            </label>
                            <p class="description">启用后，系统每小时会自动检查并生成缺失的摘要和图片。</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ai-cg-setting-section">
                <h2>
                    模型配置
                    <button type="button" id="ai-cg-refresh-models" class="button button-secondary" style="margin-left: 10px;">
                        <span class="dashicons dashicons-update"></span>
                        刷新模型列表
                    </button>
                    <span id="ai-cg-models-status" style="margin-left: 10px; font-size: 13px;"></span>
                </h2>

                <div class="notice notice-warning inline" style="margin: 15px 0; padding: 12px; background-color: #fff3cd; border-left: 4px solid #ffc107;">
                    <p><strong>⚠️ 重要提示：</strong></p>
                    <ul style="margin: 8px 0 8px 20px;">
                        <li>请前往 <a href="https://cloud.siliconflow.cn/i/H7S7dWHo" target="_blank" style="color: #d63638; text-decoration: underline;">硅基流动平台</a> 核对模型的具体能力和用途后再选择使用</li>
                        <li>不同模型支持的功能不同（如：文本生成、图片生成、图片编辑等），请仔细区分</li>
                        <li>选择错误的模型可能导致功能异常或API调用失败</li>
                    </ul>
                    <p style="margin-top: 8px; font-size: 13px;">
                        <strong>如遇到设置错误导致无法挽回的问题，请使用 ai-content-generator-database-fix 插件进行修复。</strong>
                    </p>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row">摘要生成模型</th>
                        <td>
                            <select name="ai_cg_summary_model" id="ai-cg-summary-model" class="regular-text">
                                <?php
                                $api = AI_Content_Generator_API::get_instance();
                                $models = $api->get_available_models();
                                $selected_model = get_option('ai_cg_summary_model', 'deepseek-ai/DeepSeek-V3');

                                foreach ($models['chat'] as $model_key => $model_name) :
                                    ?>
                                    <option value="<?php echo esc_attr($model_key); ?>" <?php selected($selected_model, $model_key); ?>>
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">选择用于生成文章摘要的AI模型（必须是文本/对话模型）。共 <strong id="ai-cg-chat-models-count"><?php echo count($models['chat']); ?></strong> 个可用模型。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">图片生成模型</th>
                        <td>
                            <select name="ai_cg_image_model" id="ai-cg-image-model" class="regular-text">
                                <?php
                                $selected_model = get_option('ai_cg_image_model', 'Qwen/Qwen2-VL-7B-Instruct');

                                foreach ($models['image'] as $model_key => $model_name) :
                                    ?>
                                    <option value="<?php echo esc_attr($model_key); ?>" <?php selected($selected_model, $model_key); ?>>
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">选择用于生成特色图片的AI模型（必须是图片生成模型）。共 <strong id="ai-cg-image-models-count"><?php echo count($models['image']); ?></strong> 个可用模型。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">润色功能模型</th>
                        <td>
                            <select name="ai_cg_polish_model" id="ai-cg-polish-model" class="regular-text">
                                <?php
                                $selected_model = get_option('ai_cg_polish_model', 'Qwen/Qwen2.5-122B-Instruct');

                                foreach ($models['chat'] as $model_key => $model_name) :
                                    ?>
                                    <option value="<?php echo esc_attr($model_key); ?>" <?php selected($selected_model, $model_key); ?>>
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">选择用于文章润色的AI模型（必须是文本/对话模型）。支持标准润色、正式风格、轻松风格、创意风格四种模式。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">排版功能模型</th>
                        <td>
                            <select name="ai_cg_reformat_model" id="ai-cg-reformat-model" class="regular-text">
                                <?php
                                $selected_model = get_option('ai_cg_reformat_model', 'Qwen/Qwen2.5-122B-Instruct');

                                foreach ($models['chat'] as $model_key => $model_name) :
                                    ?>
                                    <option value="<?php echo esc_attr($model_key); ?>" <?php selected($selected_model, $model_key); ?>>
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">选择用于文章排版的AI模型（必须是文本/对话模型）。支持标准排版、博客格式、技术文档格式三种模式。</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ai-cg-setting-section">
                <h2>提示词模板</h2>
                <p class="description">自定义AI生成的提示词，让生成结果更符合您的需求。支持使用 <code>{title}</code> 和 <code>{content}</code> 占位符。</p>

                <div class="notice notice-info inline" style="margin: 15px 0; padding: 12px; background-color: #d1e7dd; border-left: 4px solid #0f5132;">
                    <p><strong>💡 重要提示：</strong></p>
                    <ul style="margin: 8px 0 8px 20px;">
                        <li><strong>文章内容自动提供</strong>：无论是否包含占位符，AI 都会自动读取到文章标题和内容</li>
                        <li><strong>{title}</strong>：会被替换为文章标题（推荐包含）</li>
                        <li><strong>{content}</strong>：会被替换为文章内容（摘要生成500字，图片生成100字）</li>
                        <li><strong>示例：</strong><code>请用中文总结这篇文章的核心观点：{content}</code></li>
                    </ul>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row">摘要生成提示词</th>
                        <td>
                            <textarea name="ai_cg_summary_prompt" rows="3" class="large-text" placeholder="留空使用默认提示词，例如：请总结这篇文章的核心观点：{content}"><?php echo esc_textarea(get_option('ai_cg_summary_prompt', '')); ?></textarea>
                            <p class="description">自定义文章摘要生成的提示词。文章内容会自动提供给AI，无需重复添加。<br>默认：请为文章生成100-200字的简洁摘要。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">图片生成提示词模板</th>
                        <td>
                            <textarea name="ai_cg_image_prompt" rows="3" class="large-text" placeholder="留空使用默认提示词，例如：生成一张表示 {title} 主题的图片，风格现代简约"><?php echo esc_textarea(get_option('ai_cg_image_prompt', '')); ?></textarea>
                            <p class="description">自定义特色图片生成的提示词模板。文章信息会自动提供给AI。<br>默认：简约、专业、现代风格。</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ai-cg-setting-section">
                <h2>润色提示词</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">标准润色</th>
                        <td>
                            <textarea name="ai_cg_polish_prompt_normal" rows="2" class="large-text code" placeholder="留空使用默认提示词"><?php echo esc_textarea(get_option('ai_cg_polish_prompt_normal', '')); ?></textarea>
                            <p class="description">改善表达流畅度和可读性</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">正式风格</th>
                        <td>
                            <textarea name="ai_cg_polish_prompt_formal" rows="2" class="large-text code" placeholder="留空使用默认提示词"><?php echo esc_textarea(get_option('ai_cg_polish_prompt_formal', '')); ?></textarea>
                            <p class="description">改写为正式、专业的书面语风格</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">轻松风格</th>
                        <td>
                            <textarea name="ai_cg_polish_prompt_casual" rows="2" class="large-text code" placeholder="留空使用默认提示词"><?php echo esc_textarea(get_option('ai_cg_polish_prompt_casual', '')); ?></textarea>
                            <p class="description">改写为轻松、友好的口语风格</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">创意风格</th>
                        <td>
                            <textarea name="ai_cg_polish_prompt_creative" rows="2" class="large-text code" placeholder="留空使用默认提示词"><?php echo esc_textarea(get_option('ai_cg_polish_prompt_creative', '')); ?></textarea>
                            <p class="description">改写为富有创意和吸引力的风格</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ai-cg-setting-section">
                <h2>排版提示词</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">标准排版</th>
                        <td>
                            <textarea name="ai_cg_reformat_prompt_standard" rows="2" class="large-text code" placeholder="留空使用默认提示词"><?php echo esc_textarea(get_option('ai_cg_reformat_prompt_standard', '')); ?></textarea>
                            <p class="description">添加标题、合理分段、使用项目符号</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">博客格式</th>
                        <td>
                            <textarea name="ai_cg_reformat_prompt_blog" rows="2" class="large-text code" placeholder="留空使用默认提示词"><?php echo esc_textarea(get_option('ai_cg_reformat_prompt_blog', '')); ?></textarea>
                            <p class="description">排版为博客文章格式，优化阅读体验</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">技术文档格式</th>
                        <td>
                            <textarea name="ai_cg_reformat_prompt_technical" rows="2" class="large-text code" placeholder="留空使用默认提示词"><?php echo esc_textarea(get_option('ai_cg_reformat_prompt_technical', '')); ?></textarea>
                            <p class="description">排版为技术文档格式，使用代码块和层级结构</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ai-cg-setting-section">
                <h2>排除规则</h2>
                <p class="description">设置哪些文章/页面/分类不参与AI处理。</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">排除分类</th>
                        <td>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                <?php
                                $categories = get_categories(array('hide_empty' => false));
                                $excluded_categories = get_option('ai_cg_excluded_categories', '');
                                $excluded_category_ids = array_filter(array_map('intval', explode(',', $excluded_categories)));

                                if (!empty($categories)) :
                                    foreach ($categories as $category) :
                                        ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="ai_cg_excluded_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked(in_array($category->term_id, $excluded_category_ids)); ?>>
                                            <?php echo esc_html($category->name); ?> (ID: <?php echo $category->term_id; ?>)
                                        </label>
                                    <?php endforeach;
                                else :
                                    ?>
                                    <p style="color: #666;">没有找到分类</p>
                                <?php endif; ?>
                            </div>
                            <p class="description">选中这些分类下的所有文章都不会被AI处理。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">排除页面</th>
                        <td>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                <?php
                                $pages = get_pages();
                                $excluded_pages = get_option('ai_cg_excluded_pages', '');
                                $excluded_page_ids = array_filter(array_map('intval', explode(',', $excluded_pages)));

                                if (!empty($pages)) :
                                    foreach ($pages as $page) :
                                        ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="ai_cg_excluded_pages[]" value="<?php echo esc_attr($page->ID); ?>" <?php checked(in_array($page->ID, $excluded_page_ids)); ?>>
                                            <?php echo esc_html($page->post_title); ?> (ID: <?php echo $page->ID; ?>)
                                        </label>
                                    <?php endforeach;
                                else :
                                    ?>
                                    <p style="color: #666;">没有找到页面</p>
                                <?php endif; ?>
                            </div>
                            <p class="description">选中的页面都不会被AI处理。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">自定义排除文章ID</th>
                        <td>
                            <input type="text" name="ai_cg_excluded_posts" value="<?php echo esc_attr(get_option('ai_cg_excluded_posts', '')); ?>" class="regular-text" placeholder="例如: 1, 5, 10">
                            <p class="description">输入要排除的文章ID，多个ID用逗号分隔。优先级高于分类排除。</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ai-cg-setting-section">
                <h2>数据管理</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">删除插件时清除所有数据</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ai_cg_delete_data_on_uninstall" value="1" <?php checked(get_option('ai_cg_delete_data_on_uninstall'), 1); ?>>
                                删除插件时一并清除所有数据
                            </label>
                            <p class="description" style="color: #d63638;">
                                <strong>警告：</strong>启用此选项后，删除插件时会永久清除以下所有数据：
                            </p>
                            <ul style="color: #d63638; margin: 10px 0 0 20px; font-size: 13px;">
                                <li>API密钥和所有设置选项</li>
                                <li>Token统计记录</li>
                                <li>文章的AI生成记录（摘要、图片描述等）</li>
                                <li>自定义提示词</li>
                                <li>排除规则设置</li>
                                <li>缓存的模型列表</li>
                            </ul>
                            <p class="description" style="color: #666; margin-top: 10px;">
                                如需保留数据以便将来恢复使用，请不要勾选此选项。禁用时只会清除定时任务，不会删除任何数据。
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button('保存设置'); ?>
        </div>
    </form>

    <div class="ai-cg-setting-section ai-cg-test-section">
        <h2>测试API连接</h2>
        <button type="button" class="button" id="ai-cg-test-connection">测试连接</button>
        <span id="ai-cg-test-result" style="margin-left: 10px;"></span>
    </div>

    <div class="ai-cg-setting-section ai-cg-logs-section">
        <h2>诊断与日志</h2>
        <p class="description">
            导出插件日志可以帮助排查问题。日志包含配置信息、最近的错误记录和Token使用统计。
            请先启用WordPress调试模式以收集更多日志信息。
        </p>
        <div style="margin-top: 10px;">
            <button type="button" class="button" id="ai-cg-export-logs">
                <span class="dashicons dashicons-download" style="position: relative; top: 3px; margin-right: 5px;"></span>
                导出日志
            </button>
            <button type="button" class="button button-secondary" id="ai-cg-view-logs">
                <span class="dashicons dashicons-info" style="position: relative; top: 3px; margin-right: 5px;"></span>
                使用说明
            </button>
            <span id="ai-cg-logs-status" style="margin-left: 15px; font-size: 13px;"></span>
        </div>
    </div>

    <div class="ai-cg-info-box">
        <h3>使用说明</h3>
        <ol>
            <li>在"API配置"中输入您的硅基流动API密钥</li>
            <li>在"功能开关"中启用您需要的功能（摘要生成、特色图片生成）</li>
            <li>在"模型配置"中选择合适的AI模型</li>
            <li>在"文章管理"页面中可以批量或单独为文章生成摘要和图片</li>
            <li>在"Token统计"页面中查看API调用统计信息</li>
        </ol>
        <p><strong>注意:</strong> 启用自动检查后，系统每小时会自动处理5篇没有摘要的文章和5篇没有特色图片的文章。</p>
    </div>
</div>
