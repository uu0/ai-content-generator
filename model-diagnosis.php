<?php
/**
 * 硅基流动API模型诊断工具
 *
 * 使用方法：
 * 1. 确保 WordPress 已安装 AI Content Generator 插件
 * 2. 在 WordPress 后台配置好 API 密钥
 * 3. 将此文件放到 WordPress 根目录
 * 4. 访问 http://你的域名/model-diagnosis.php
 * 5. 查看诊断报告
 */

// WordPress路径
$wordpress_path = __DIR__;
require_once($wordpress_path . '/wp-load.php');

// 检查权限
if (!current_user_can('manage_options')) {
    die('需要管理员权限才能访问此页面');
}

// 引入插件类
if (!class_exists('AI_Content_Generator_API')) {
    $plugin_file = $wordpress_path . '/wp-content/plugins/ai-content-generator/ai-content-generator.php';
    if (file_exists($plugin_file)) {
        require_once($plugin_file);
    } else {
        die('无法找到 AI Content Generator 插件文件');
    }
}

// 获取API实例
$api = AI_Content_Generator_API::get_instance();

// 页面标题
$page_title = '硅基流动API模型诊断工具';

// HTML头部
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #23527b;
            border-bottom: 2px solid #23527b;
            padding-bottom: 10px;
        }
        h2 {
            color: #23527b;
            margin-top: 30px;
        }
        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        .info {
            color: #17a2b8;
        }
        .btn {
            padding: 10px 20px;
            margin-right: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .loading {
            color: #007bff;
            font-style: italic;
        }
        .model-id {
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }
        .test-result {
            font-size: 12px;
            margin-top: 5px;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin: 15px 0;
        }
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            flex: 1;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <h1><?php echo $page_title; ?></h1>

    <div class="section">
        <h2>诊断操作</h2>
        <div style="margin-bottom: 15px;">
            <button class="btn btn-primary" onclick="location.href='?action=fetch_models'">获取模型列表</button>
            <button class="btn btn-success" onclick="location.href='?action=clear_cache'">清除缓存</button>
            <button class="btn btn-danger" onclick="if(confirm('确定要测试所有模型吗？这可能需要几分钟。')) location.href='?action=test_all'">测试所有模型</button>
        </div>
        <p class="info">提示：测试所有模型会消耗API配额，请谨慎使用。</p>
    </div>

    <?php
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'fetch_models') {
        // 获取模型列表
        echo '<div class="section">';
        echo '<h2>获取模型列表</h2>';

        $result = $api->get_available_models(true);

        if (is_wp_error($result)) {
            echo '<p class="error">错误：' . esc_html($result->get_error_message()) . '</p>';
        } else {
            echo '<p class="success">✓ 模型列表获取成功！</p>';

            // 统计信息
            echo '<div class="stats">';
            echo '<div class="stat-box">';
            echo '<div class="stat-number">' . count($result['chat']) . '</div>';
            echo '<div class="stat-label">聊天模型</div>';
            echo '</div>';
            echo '<div class="stat-box">';
            echo '<div class="stat-number">' . count($result['image']) . '</div>';
            echo '<div class="stat-label">图片模型</div>';
            echo '</div>';
            if (isset($result['total'])) {
                echo '<div class="stat-box">';
                echo '<div class="stat-number">' . $result['total'] . '</div>';
                echo '<div class="stat-label">总计</div>';
                echo '</div>';
            }
            echo '</div>';

            // 缓存时间
            if (isset($result['last_updated'])) {
                $last_update = $result['last_updated'];
                $time_diff = time() - $last_update;
                if ($time_diff < 60) {
                    $time_text = $time_diff . ' 秒前';
                } elseif ($time_diff < 3600) {
                    $time_text = floor($time_diff / 60) . ' 分钟前';
                } elseif ($time_diff < 86400) {
                    $time_text = floor($time_diff / 3600) . ' 小时前';
                } else {
                    $time_text = floor($time_diff / 86400) . ' 天前';
                }
                echo '<p class="info">缓存更新时间：' . esc_html($time_text) . '</p>';
            }

            // 聊天模型列表
            echo '<h3>聊天模型（' . count($result['chat']) . '）</h3>';
            echo '<table>';
            echo '<thead><tr><th>模型名称</th><th>模型ID</th></tr></thead>';
            echo '<tbody>';
            foreach ($result['chat'] as $model_id => $model_name) {
                echo '<tr>';
                echo '<td>' . esc_html($model_name) . '</td>';
                echo '<td><code class="model-id">' . esc_html($model_id) . '</code></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';

            // 图片模型列表
            echo '<h3>图片模型（' . count($result['image']) . '）</h3>';
            echo '<table>';
            echo '<thead><tr><th>模型名称</th><th>模型ID</th></tr></thead>';
            echo '<tbody>';
            foreach ($result['image'] as $model_id => $model_name) {
                echo '<tr>';
                echo '<td>' . esc_html($model_name) . '</td>';
                echo '<td><code class="model-id">' . esc_html($model_id) . '</code></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }

        echo '</div>';
    } elseif ($action === 'clear_cache') {
        // 清除缓存
        echo '<div class="section">';
        echo '<h2>清除缓存</h2>';

        delete_option('ai_cg_available_models');
        delete_option('ai_cg_models_last_update');

        echo '<p class="success">✓ 缓存已清除！</p>';
        echo '<p><a href="?action=fetch_models" class="btn btn-primary">重新获取模型列表</a></p>';

        echo '</div>';
    } elseif ($action === 'test_all') {
        // 测试所有模型
        echo '<div class="section">';
        echo '<h2>测试所有模型</h2>';

        set_time_limit(300); // 设置5分钟超时

        $models = $api->get_available_models();

        if (is_wp_error($models)) {
            echo '<p class="error">错误：' . esc_html($models->get_error_message()) . '</p>';
        } else {
            $success_count = 0;
            $error_count = 0;

            echo '<h3>聊天模型测试</h3>';
            echo '<table>';
            echo '<thead><tr><th>模型名称</th><th>模型ID</th><th>状态</th><th>结果</th></tr></thead>';
            echo '<tbody>';

            foreach ($models['chat'] as $model_id => $model_name) {
                echo '<tr>';
                echo '<td>' . esc_html($model_name) . '</td>';
                echo '<td><code class="model-id">' . esc_html($model_id) . '</code></td>';

                // 测试聊天模型
                $test_result = test_chat_model($api, $model_id);

                if ($test_result['success']) {
                    echo '<td class="success">✓ 通过</td>';
                    echo '<td class="info">响应时间: ' . $test_result['time'] . 's</td>';
                    $success_count++;
                } else {
                    echo '<td class="error">✗ 失败</td>';
                    echo '<td class="error">' . esc_html($test_result['error']) . '</td>';
                    $error_count++;
                }

                echo '</tr>';
                flush();
            }

            echo '</tbody>';
            echo '</table>';

            echo '<h3>图片模型测试</h3>';
            echo '<table>';
            echo '<thead><tr><th>模型名称</th><th>模型ID</th><th>状态</th><th>结果</th></tr></thead>';
            echo '<tbody>';

            foreach ($models['image'] as $model_id => $model_name) {
                echo '<tr>';
                echo '<td>' . esc_html($model_name) . '</td>';
                echo '<td><code class="model-id">' . esc_html($model_id) . '</code></td>';

                // 测试图片模型
                $test_result = test_image_model($api, $model_id);

                if ($test_result['success']) {
                    echo '<td class="success">✓ 通过</td>';
                    echo '<td class="info">响应时间: ' . $test_result['time'] . 's</td>';
                    $success_count++;
                } else {
                    echo '<td class="error">✗ 失败</td>';
                    echo '<td class="error">' . esc_html($test_result['error']) . '</td>';
                    $error_count++;
                }

                echo '</tr>';
                flush();
            }

            echo '</tbody>';
            echo '</table>';

            // 测试总结
            echo '<div class="stats">';
            echo '<div class="stat-box">';
            echo '<div class="stat-number">' . $success_count . '</div>';
            echo '<div class="stat-label">通过测试</div>';
            echo '</div>';
            echo '<div class="stat-box">';
            echo '<div class="stat-number" style="color: #dc3545;">' . $error_count . '</div>';
            echo '<div class="stat-label">测试失败</div>';
            echo '</div>';
            echo '</div>';

            if ($error_count > 0) {
                echo '<p class="warning">⚠️ 有 ' . $error_count . ' 个模型测试失败，请检查模型ID或API权限。</p>';
            } else {
                echo '<p class="success">✓ 所有模型测试通过！</p>';
            }
        }

        echo '</div>';
    }

    /**
     * 测试聊天模型
     */
    function test_chat_model($api, $model_id) {
        $start_time = microtime(true);

        try {
            // 使用私有方法反射
            $reflection = new ReflectionClass($api);
            $method = $reflection->getMethod('call_chat_api');

            $result = $method->invoke($api, $model_id, 'Hi');

            $time = round(microtime(true) - $start_time, 2);

            if (is_wp_error($result)) {
                return array('success' => false, 'error' => $result->get_error_message(), 'time' => $time);
            }

            return array('success' => true, 'time' => $time);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage(), 'time' => 0);
        }
    }

    /**
     * 测试图片模型
     */
    function test_image_model($api, $model_id) {
        $start_time = microtime(true);

        try {
            // 使用私有方法反射
            $reflection = new ReflectionClass($api);
            $method = $reflection->getMethod('call_image_api');

            $result = $method->invoke($api, $model_id, 'Test image');

            $time = round(microtime(true) - $start_time, 2);

            if (is_wp_error($result)) {
                return array('success' => false, 'error' => $result->get_error_message(), 'time' => $time);
            }

            return array('success' => true, 'time' => $time);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage(), 'time' => 0);
        }
    }
    ?>

    <div class="section">
        <h2>使用说明</h2>
        <ol>
            <li><strong>获取模型列表</strong>：从硅基流动API获取最新的模型列表并更新缓存</li>
            <li><strong>清除缓存</strong>：清除插件缓存的模型列表，下次访问时会重新获取</li>
            <li><strong>测试所有模型</strong>：逐个测试所有模型的可用性（会消耗API配额）</li>
        </ol>
        <h3>常见问题</h3>
        <ul>
            <li><strong>获取模型列表失败</strong>：检查API密钥是否正确，网络连接是否正常</li>
            <li><strong>模型测试失败</strong>：可能是模型ID不存在、模型已下线或API权限不足</li>
            <li><strong>部分模型不可用</strong>：某些模型可能只在特定条件下可用</li>
        </ul>
    </div>

    <div class="section">
        <h2>已知的模型黑名单</h2>
        <p>以下模型类型会被自动排除：</p>
        <ul>
            <li><strong>Embedding模型</strong>：用于文本向量化，不支持聊天（如 BAAI/bge-*）</li>
            <li><strong>Rerank模型</strong>：用于搜索结果重排序（如 BAAI/bge-reranker-*）</li>
            <li><strong>Audio模型</strong>：用于语音处理（如 openai/whisper-*）</li>
            <li><strong>其他不支持模型</strong>：包含 thumbnail, upscale, edit 等关键词的模型</li>
        </ul>
    </div>

</body>
</html>
