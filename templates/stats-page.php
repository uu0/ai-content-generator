<div class="wrap">
    <h1>AI内容生成 - Token统计</h1>

    <div class="ai-cg-stats-overview">
        <div class="ai-cg-stat-card">
            <div class="ai-cg-stat-icon">📊</div>
            <div class="ai-cg-stat-info">
                <div class="ai-cg-stat-label">总请求数</div>
                <div class="ai-cg-stat-value"><?php echo number_format($total_stats->total_requests); ?></div>
            </div>
        </div>

        <div class="ai-cg-stat-card">
            <div class="ai-cg-stat-icon">📝</div>
            <div class="ai-cg-stat-info">
                <div class="ai-cg-stat-label">输入Token</div>
                <div class="ai-cg-stat-value"><?php echo number_format($total_stats->total_input_tokens); ?></div>
            </div>
        </div>

        <div class="ai-cg-stat-card">
            <div class="ai-cg-stat-icon">💬</div>
            <div class="ai-cg-stat-info">
                <div class="ai-cg-stat-label">输出Token</div>
                <div class="ai-cg-stat-value"><?php echo number_format($total_stats->total_output_tokens); ?></div>
            </div>
        </div>

        <div class="ai-cg-stat-card">
            <div class="ai-cg-stat-icon">🎯</div>
            <div class="ai-cg-stat-info">
                <div class="ai-cg-stat-label">总Token</div>
                <div class="ai-cg-stat-value"><?php echo number_format($total_stats->total_tokens); ?></div>
            </div>
        </div>
    </div>

    <div class="ai-cg-stats-tables">
        <div class="ai-cg-stats-section">
            <h2>模型使用统计</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>模型</th>
                        <th>类型</th>
                        <th>请求次数</th>
                        <th>输入Token</th>
                        <th>输出Token</th>
                        <th>总Token</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($model_stats) : ?>
                        <?php foreach ($model_stats as $stat) : ?>
                            <tr>
                                <td><?php echo esc_html($stat->model); ?></td>
                                <td>
                                    <span class="ai-cg-badge ai-cg-badge-info">
                                        <?php echo $stat->action === 'generate_summary' ? '摘要生成' : '图片生成'; ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($stat->request_count); ?></td>
                                <td><?php echo number_format($stat->total_input_tokens); ?></td>
                                <td><?php echo number_format($stat->total_output_tokens); ?></td>
                                <td><strong><?php echo number_format($stat->total_tokens); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">暂无数据</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="ai-cg-stats-section">
            <h2>最近30天使用趋势</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>日期</th>
                        <th>请求次数</th>
                        <th>总Token</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($daily_stats) : ?>
                        <?php foreach ($daily_stats as $stat) : ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($stat->date)); ?></td>
                                <td><?php echo number_format($stat->request_count); ?></td>
                                <td><strong><?php echo number_format($stat->total_tokens); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">暂无数据</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="ai-cg-actions">
        <button type="button" class="button ai-cg-clear-stats">清除统计数据</button>
    </div>

    <style>
        .ai-cg-stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .ai-cg-stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .ai-cg-stat-icon {
            font-size: 32px;
        }

        .ai-cg-stat-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .ai-cg-stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
        }

        .ai-cg-stats-tables {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }

        @media (max-width: 768px) {
            .ai-cg-stats-tables {
                grid-template-columns: 1fr;
            }
        }

        .ai-cg-stats-section h2 {
            margin-bottom: 15px;
        }

        .ai-cg-actions {
            margin-top: 30px;
            padding-bottom: 20px;
        }
    </style>
</div>
