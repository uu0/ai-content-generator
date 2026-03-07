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
                                请输入您的硅基流动API密钥。访问 <a href="https://platform.siliconflow.cn/" target="_blank">https://platform.siliconflow.cn/</a> 获取API密钥。
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

                <table class="form-table">
                    <tr>
                        <th scope="row">摘要生成模型</th>
                        <td>
                            <select name="ai_cg_summary_model" id="ai-cg-summary-model" class="regular-text">
                                <?php
                                $api = AI_Content_Generator_API::get_instance();
                                $models = $api->get_available_models();
                                $selected_model = get_option('ai_cg_summary_model', 'deepseek-chat');

                                foreach ($models['chat'] as $model_key => $model_name) :
                                    ?>
                                    <option value="<?php echo esc_attr($model_key); ?>" <?php selected($selected_model, $model_key); ?>>
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">选择用于生成文章摘要的AI模型。共 <strong id="ai-cg-chat-models-count"><?php echo count($models['chat']); ?></strong> 个可用模型。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">图片生成模型</th>
                        <td>
                            <select name="ai_cg_image_model" id="ai-cg-image-model" class="regular-text">
                                <?php
                                $selected_model = get_option('ai_cg_image_model', 'stable-diffusion-3');

                                foreach ($models['image'] as $model_key => $model_name) :
                                    ?>
                                    <option value="<?php echo esc_attr($model_key); ?>" <?php selected($selected_model, $model_key); ?>>
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">选择用于生成特色图片的AI模型。共 <strong id="ai-cg-image-models-count"><?php echo count($models['image']); ?></strong> 个可用模型。</p>
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
