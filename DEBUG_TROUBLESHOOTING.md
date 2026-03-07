# AI内容生成插件 - 调试和故障排除指南

## 特色图片生成成功但不添加到文章的问题

### 问题描述
系统提示"特色图片生成成功"，但图片没有显示在文章的特色图片位置。

### 已修复的问题
1. **图片文件扩展名处理**：修复了从URL中正确提取文件扩展名的逻辑
2. **API返回数据验证**：添加了对API返回数据的详细验证
3. **Base64图片支持**：增加了对base64编码图片的支持
4. **错误日志增强**：添加了详细的错误日志记录机制

### 如何调试

#### 1. 检查错误日志
在WordPress根目录的 `wp-content/debug.log` 文件中查看详细错误信息：

```bash
tail -f wp-content/debug.log
```

查找以 `AI Content Generator:` 开头的日志条目。

#### 2. 启用WordPress调试
在 `wp-config.php` 文件中添加以下代码：

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### 3. 检查WordPress媒体库
1. 进入WordPress后台的"媒体 → 媒体库"
2. 查看最近上传的文件
3. 如果看到图片上传了但文章特色图片没有设置，说明图片上传成功但设置流程有问题
4. 如果没有看到图片，说明图片下载或上传过程失败

#### 4. 检查API返回数据
查看错误日志中的 `图片API响应` 条目，确认API返回的数据格式是否正确。
正常的响应应该包含：
```
Array
(
    [data] => Array
        (
            [0] => Array
                (
                    [url] => https://...
                )
        )
)
```

#### 5. 手动测试API
使用Postman或curl测试硅基流动API：

```bash
curl -X POST https://api.siliconflow.cn/v1/images/generations \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "stable-diffusion-3",
    "prompt": "beautiful sunset over ocean",
    "image_size": "1024x1024",
    "n": 1
  }'
```

### 日志导出功能

插件现在支持一键导出包含所有调试信息的日志文件，大大简化故障排除流程。

#### 使用日志导出功能

1. 进入WordPress后台 → AI内容生成 → 设置
2. 在"诊断与日志"区域点击"导出日志"按钮
3. 系统会生成并下载一个包含以下信息的文本文件：
   - 基本信息（插件版本、WordPress版本、PHP版本等）
   - 配置信息（API设置、功能开关、模型配置）
   - WordPress调试日志中的插件相关条目
   - 最近的Token使用统计
   - 系统状态检查结果

#### 分析导出的日志

日志文件格式示例：
```
=== AI Content Generator 日志导出 ===
导出时间: 2026-03-08 01:01:38
站点URL: https://your-site.com
插件版本: 1.1.0
WordPress版本: 6.4.2
PHP版本: 8.1.2

=== 配置信息 ===
API密钥已配置: 是
摘要生成: 启用
特色图片生成: 启用
自动检查: 启用
摘要模型: deepseek-chat
图片模型: stable-diffusion-3

=== WordPress调试日志 (AI Content Generator相关) ===
[08-Mar-2026 01:00:01 UTC] AI Content Generator: 开始为文章ID: 123生成特色图片
[08-Mar-2026 01:00:05 UTC] AI Content Generator: 图片API响应: {"data":[{"url":"https://..."}]}
[08-Mar-2026 01:00:06 UTC] AI Content Generator: 图片下载成功，已保存到: /tmp/wp_uploads_xxxxx.jpg
[08-Mar-2026 01:00:07 UTC] AI Content Generator: 上传到媒体库成功，Attachment ID: 456

=== 最近Token统计 (最近10条) ===
时间: 2026-03-08 00:00:00 | 类型: summary | 模型: deepseek-chat | 输入: 1543 | 输出: 204 | 文章ID: 122
时间: 2026-03-08 00:01:00 | 类型: image | 模型: stable-diffusion-3 | 输入: 0 | 输出: 0 | 文章ID: 123

=== 系统状态 ===
uploads目录可写: 是
插件目录: /var/www/html/wp-content/plugins/ai-content-generator
插件URL: https://your-site.com/wp-content/plugins/ai-content-generator
```

#### 何时需要导出日志

1. 遇到"特色图片生成成功但不显示"的问题
2. API连接失败
3. 批量操作出现异常
4. Token统计异常
5. 向技术支持寻求帮助时

### 常见问题及解决方案

#### 问题1：API未返回图片URL
**症状**：日志显示"无法从API响应中获取图片URL"
**解决方案**：
- 检查API密钥是否有效
- 确认选择的模型是否支持
- 查看API账户余额是否充足

#### 问题2：图片下载失败
**症状**：日志显示"下载图片失败"
**解决方案**：
- 检查服务器是否允许远程文件下载
- 确认WordPress主题中的 `wp-admin/includes/file.php` 文件可访问
- 检查服务器的上传文件大小限制

#### 问题3：上传到媒体库失败
**症状**：日志显示"上传到媒体库失败"
**解决方案**：
- 检查媒体库目录权限
- 确认存储空间充足
- 检查WordPress的上传文件类型限制

#### 问题4：设置特色图片失败
**症状**：日志显示"设置特色图片失败"
**解决方案**：
- 确认文章有编辑权限
- 检查主题是否支持特色图片功能
- 尝试手动设置特色图片验证功能

### 手动修复步骤

如果自动修复无效，可以尝试：

1. **查看媒体库中的图片ID**：
```php
// 在functions.php中临时添加
add_action('admin_init', function() {
    error_log('最新的媒体文件ID: ' . get_option('latest_media_id'));
});
```

2. **手动设置特色图片**：
```php
// 通过PHP
set_post_thumbnail(post_id, attachment_id);

// 或在WordPress后台手动设置：文章 → 编辑 → 右侧特色图片
```

3. **检查文件权限**：
```bash
chmod 755 wp-content/uploads
chmod 644 wp-content/uploads/*/*
```

### 联系支持

如果以上方法都无法解决问题，请使用新的日志导出功能：

1. 进入插件设置页面，点击"导出日志"按钮
2. 将导出的日志文件发送给技术支持
3. 日志文件包含了所有必要的诊断信息，无需手动收集

日志导出功能确保技术支持团队能够快速准确地理解和解决问题。

### 附加提示

- 定期导出日志备份，用于长期跟踪系统状态
- 在重大配置更改前后导出日志，便于对比分析
- 结合WordPress的调试日志模式和导出功能，获得最完整的问题诊断信息
