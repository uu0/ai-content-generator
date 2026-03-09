<?php
/**
 * 插件卸载脚本
 * 当用户删除插件文件时执行
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 检查是否启用了"删除插件时清除所有数据"选项
$delete_data = get_option('ai_cg_delete_data_on_uninstall', false);

if (!$delete_data) {
    // 用户未选择清除数据，则只清除定时任务
    wp_clear_scheduled_hook('ai_cg_hourly_check');
    return;
}

// 如果用户选择了清除数据，则执行以下清理操作

// 1. 删除 Token 统计表
global $wpdb;
$table_name = $wpdb->prefix . 'ai_cg_token_stats';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// 2. 删除插件相关的 WordPress 选项
$options_to_delete = array(
    'ai_cg_api_key',
    'ai_cg_summary_enabled',
    'ai_cg_featured_image_enabled',
    'ai_cg_summary_model',
    'ai_cg_image_model',
    'ai_cg_polish_model',
    'ai_cg_reformat_model',
    'ai_cg_auto_check_enabled',
    'ai_cg_summary_prompt',
    'ai_cg_image_prompt',
    'ai_cg_polish_prompt_normal',
    'ai_cg_polish_prompt_formal',
    'ai_cg_polish_prompt_casual',
    'ai_cg_polish_prompt_creative',
    'ai_cg_reformat_prompt_standard',
    'ai_cg_reformat_prompt_blog',
    'ai_cg_reformat_prompt_technical',
    'ai_cg_excluded_categories',
    'ai_cg_excluded_pages',
    'ai_cg_excluded_posts',
    'ai_cg_delete_data_on_uninstall',  // 同时删除这个选项本身
    'ai_cg_available_models',  // 缓存的模型列表
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// 3. 清除定时任务
wp_clear_scheduled_hook('ai_cg_hourly_check');

// 4. 删除所有文章的插件相关元数据（可选）
// 获取所有具有插件元数据的文章
$meta_keys_to_delete = array(
    '_ai_cg_summary_generated',
    '_ai_cg_image_generated',
    '_ai_cg_original_content',
    '_ai_cg_operation_type',
    '_ai_cg_operation_timestamp',
    '_ai_cg_operation_stack',
    '_ai_cg_polished_at',
    '_ai_cg_polish_style',
    '_ai_cg_reformatted_at',
    '_ai_cg_reformat_type',
);

foreach ($meta_keys_to_delete as $meta_key) {
    // 删除所有具有该元数据的记录
    delete_post_meta_by_key($meta_key);
}
