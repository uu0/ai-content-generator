<div class="wrap">
    <h1>AI内容生成 - 文章管理</h1>

    <div class="ai-cg-toolbar">
        <div class="ai-cg-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="ai-content-generator">

            <div class="ai-cg-bulk-actions">
                <button type="button" class="button ai-cg-bulk-summary" data-post-ids="">
                    批量生成摘要
                </button>
                <button type="button" class="button ai-cg-bulk-image" data-post-ids="">
                    批量生图
                </button>
                <button type="button" class="button ai-cg-bulk-polish" data-post-ids="">
                    批量润色
                </button>
                <button type="button" class="button ai-cg-bulk-reformat" data-post-ids="">
                    批量排版
                </button>
                <button type="button" class="button ai-cg-bulk-exclude" data-post-ids="">
                    批量排除
                </button>
                <button type="button" class="button ai-cg-bulk-unexclude" data-post-ids="">
                    取消排除
                </button>
            </div>
        </div>

        <div class="ai-cg-toolbar" style="margin-top: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <div class="ai-cg-filters">
                <label for="ai-cg-filter"><strong>筛选文章：</strong></label>
                <select name="filter" id="ai-cg-filter" class="ai-cg-filter-select">
                    <option value="all" <?php selected(isset($_GET['filter']) ? $_GET['filter'] : '', 'all'); ?>>全部文章</option>
                    <option value="no_summary" <?php selected(isset($_GET['filter']) ? $_GET['filter'] : '', 'no_summary'); ?>>无摘要</option>
                    <option value="no_image" <?php selected(isset($_GET['filter']) ? $_GET['filter'] : '', 'no_image'); ?>>无特色图片</option>
                    <option value="has_summary" <?php selected(isset($_GET['filter']) ? $_GET['filter'] : '', 'has_summary'); ?>>有摘要</option>
                    <option value="has_image" <?php selected(isset($_GET['filter']) ? $_GET['filter'] : '', 'has_image'); ?>>有特色图片</option>
                    <option value="excluded" <?php selected(isset($_GET['filter']) ? $_GET['filter'] : '', 'excluded'); ?>>已排除</option>
                </select>
            </form>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped posts">
        <thead>
            <tr>
                <th class="manage-column column-cb check-column">
                    <input type="checkbox" id="ai-cg-select-all">
                </th>
                <th>ID</th>
                <th>标题</th>
                <th>状态</th>
                <th>摘要</th>
                <th>特色图片</th>
                <th>生成时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($query->have_posts()) : ?>
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php
                    $has_summary = !empty(get_the_excerpt());
                    $has_image = has_post_thumbnail();
                    $summary_generated = get_post_meta(get_the_ID(), '_ai_cg_summary_generated', true);
                    $image_generated = get_post_meta(get_the_ID(), '_ai_cg_image_generated', true);
                    $api = AI_Content_Generator_API::get_instance();
                    $is_excluded = $api->is_post_excluded(get_the_ID());
                    ?>
                    <tr data-post-id="<?php echo get_the_ID(); ?>">
                        <td>
                            <input type="checkbox" class="ai-cg-post-checkbox" value="<?php echo get_the_ID(); ?>">
                        </td>
                        <td>
                            <strong><?php echo get_the_ID(); ?></strong>
                        </td>
                        <td>
                            <strong>
                                <a href="<?php echo get_edit_post_link(); ?>" target="_blank">
                                    <?php the_title(); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php the_permalink(); ?>" target="_blank">查看</a>
                                </span> |
                                <span class="edit">
                                    <a href="<?php echo get_edit_post_link(); ?>" target="_blank">编辑</a>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="status-<?php echo get_post_status(); ?>">
                                <?php echo get_post_status_object(get_post_status())->label; ?>
                            </span>
                            <?php if ($is_excluded) : ?>
                                <span class="ai-cg-badge ai-cg-badge-danger" style="margin-left: 5px;">已排除</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($has_summary) : ?>
                                <span class="ai-cg-badge ai-cg-badge-success">有摘要</span>
                            <?php else : ?>
                                <span class="ai-cg-badge ai-cg-badge-warning">无摘要</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($has_image) : ?>
                                <span class="ai-cg-badge ai-cg-badge-success">有图片</span>
                            <?php else : ?>
                                <span class="ai-cg-badge ai-cg-badge-warning">无图片</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size: 12px; color: #666;">
                                <?php if ($summary_generated) : ?>
                                    <div>摘要: <?php echo date('Y-m-d H:i', strtotime($summary_generated)); ?></div>
                                <?php endif; ?>
                                <?php if ($image_generated) : ?>
                                    <div>图片: <?php echo date('Y-m-d H:i', strtotime($image_generated)); ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="ai-cg-action-buttons">
                                <button type="button" class="button button-small ai-cg-generate-summary" data-post-id="<?php echo get_the_ID(); ?>">
                                    摘要
                                </button>
                                <button type="button" class="button button-small ai-cg-generate-image" data-post-id="<?php echo get_the_ID(); ?>">
                                    生图
                                </button>
                                <button type="button" class="button button-small ai-cg-polish" data-post-id="<?php echo get_the_ID(); ?>" title="AI润色文章">
                                    润色
                                </button>
                                <button type="button" class="button button-small ai-cg-reformat" data-post-id="<?php echo get_the_ID(); ?>" title="AI排版优化">
                                    排版
                                </button>
                                <?php
                                // 获取操作栈
                                $operation_stack = get_post_meta(get_the_ID(), '_ai_cg_operation_stack', true);
                                $has_polish_undo = false;
                                $has_reformat_undo = false;

                                if (is_array($operation_stack) && !empty($operation_stack)) {
                                    foreach ($operation_stack as $op) {
                                        if ($op['type'] === 'polish') {
                                            $has_polish_undo = true;
                                        }
                                        if ($op['type'] === 'reformat') {
                                            $has_reformat_undo = true;
                                        }
                                    }
                                }

                                // 显示单独的撤回按钮
                                if ($has_polish_undo) :
                                ?>
                                    <button type="button" class="button button-small ai-cg-undo-polish" data-post-id="<?php echo get_the_ID(); ?>" title="撤回润色操作">
                                        撤回润色
                                    </button>
                                <?php endif; ?>

                                <?php if ($has_reformat_undo) : ?>
                                    <button type="button" class="button button-small ai-cg-undo-reformat" data-post-id="<?php echo get_the_ID(); ?>" title="撤回排版操作">
                                        撤回排版
                                    </button>
                                <?php endif; ?>

                                <?php if ($has_polish_undo || $has_reformat_undo) : ?>
                                    <button type="button" class="button button-small button-secondary ai-cg-confirm" data-post-id="<?php echo get_the_ID(); ?>" title="确认修改，清除撤回按钮">
                                        确认
                                    </button>
                                <?php endif; ?>

                                <?php if ($is_excluded) : ?>
                                    <button type="button" class="button button-small ai-cg-unexclude" data-post-id="<?php echo get_the_ID(); ?>" title="从排除列表中移除">
                                        取消排除
                                    </button>
                                <?php else : ?>
                                    <button type="button" class="button button-small ai-cg-exclude" data-post-id="<?php echo get_the_ID(); ?>" title="添加到排除列表">
                                        排除
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        没有找到文章
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php wp_reset_postdata(); ?>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $pagination_html = paginate_links(array(
                    'base' => add_query_arg('page_num', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $current_page
                ));
                echo $pagination_html;
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="ai-cg-loading" style="display: none;">
    <div class="ai-cg-overlay"></div>
    <div class="ai-cg-spinner">
        <div class="ai-cg-bounce1"></div>
        <div class="ai-cg-bounce2"></div>
        <div class="ai-cg-bounce3"></div>
    </div>
</div>

<!-- 排版类型选择模态框 -->
<div id="ai-cg-reformat-modal" style="display: none;">
    <div class="ai-cg-modal-content">
        <h2>选择排版类型</h2>
        <p>请选择您需要的排版风格：</p>
        <select id="ai-cg-reformat-type" class="ai-cg-modal-select">
            <option value="standard">标准排版 - 适合一般文章</option>
            <option value="blog">博客格式 - 适合个人博客</option>
            <option value="technical">技术文档 - 适合技术内容</option>
        </select>
        <div class="ai-cg-modal-buttons">
            <button type="button" class="button button-primary" id="ai-cg-reformat-confirm">确定</button>
            <button type="button" class="button" id="ai-cg-reformat-cancel">取消</button>
        </div>
    </div>
</div>

<!-- 润色风格选择模态框 -->
<div id="ai-cg-polish-modal" style="display: none;">
    <div class="ai-cg-modal-content">
        <h2>选择润色风格</h2>
        <p>请选择您需要的润色风格：</p>
        <select id="ai-cg-polish-style" class="ai-cg-modal-select">
            <option value="normal">标准润色 - 改善流畅度和可读性</option>
            <option value="formal">正式风格 - 专业书面语</option>
            <option value="casual">轻松风格 - 友好的口语</option>
            <option value="creative">创意风格 - 富有创新和吸引力</option>
        </select>
        <div class="ai-cg-modal-buttons">
            <button type="button" class="button button-primary" id="ai-cg-polish-confirm">确定</button>
            <button type="button" class="button" id="ai-cg-polish-cancel">取消</button>
        </div>
    </div>
</div>

<!-- 预览确认模态框 -->
<div id="ai-cg-preview-modal" style="display: none;">
    <div class="ai-cg-modal-content ai-cg-preview-content">
        <h2>预览修改内容</h2>
        <p id="ai-cg-preview-description">请预览修改后的内容：</p>
        <div class="ai-cg-preview-box">
            <div id="ai-cg-preview-content" style="max-height: 400px; overflow-y: auto; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;"></div>
        </div>
        <div class="ai-cg-modal-buttons">
            <button type="button" class="button button-secondary" id="ai-cg-preview-cancel">取消</button>
            <button type="button" class="button button-primary" id="ai-cg-preview-publish">确认发布</button>
        </div>
    </div>
</div>
