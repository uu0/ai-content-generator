# AI Content Generator - WordPress插件

使用硅基流动API自动为WordPress文章生成摘要和特色图片。

## 功能特性

- ✅ 自动生成文章摘要
- ✅ 自动生成特色图片
- ✅ 支持多个AI模型（DeepSeek、Qwen、Llama、FLUX、Stable Diffusion等）
- ✅ 功能自由开关
- ✅ 模型自定义配置
- ✅ Token使用统计
- ✅ 批量文章管理
- ✅ 每小时自动检查和生成
- ✅ 筛选和分页查看

## 安装说明

1. 将整个 `ai-content-generator` 文件夹上传到 WordPress 的 `wp-content/plugins/` 目录
2. 登录 WordPress 后台，进入"插件"页面
3. 找到"AI Content Generator"插件并启用

## 配置步骤

1. **获取API密钥**
   - 访问 [硅基流动官网](https://platform.siliconflow.cn/)
   - 注册并登录账号
   - 在控制台获取API密钥

2. **配置插件**
   - 进入WordPress后台 → AI内容生成 → 设置
   - 在"API配置"中填写API密钥
   - 在"功能开关"中启用需要的功能
   - 在"模型配置"中选择合适的模型

## 使用方法

### 方法1：自动生成

1. 在设置中启用"启用自动检查"
2. 系统每小时会自动处理5篇没有摘要的文章和5篇没有特色图片的文章
3. 在"Token统计"页面查看生成日志

### 方法2：手动生成

1. 进入WordPress后台 → AI内容生成 → 文章管理
2. 使用筛选功能找到需要处理的文章
3. 选择单篇或多篇文章
4. 点击"生成摘要"或"生成图片"按钮
5. 等待生成完成

### 方法3：文章列表快速操作

1. 进入"文章"页面
2. 每篇文章标题下方有"生成摘要"和"生成图片"快捷链接
3. 点击对应的链接即可生成

### 方法4：故障排除和日志导出

1. 进入"设置"页面
2. 在"诊断与日志"区域点击"导出日志"按钮
3. 下载包含配置信息、错误记录和Token统计的日志文件
4. 使用文本编辑器分析日志或分享给技术支持

## 故障排除

### 启用调试模式
如果遇到问题，建议启用WordPress调试模式以获取详细的日志信息：

```php
// 在 wp-config.php 中添加
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 常见问题
1. **特色图片生成失败**：检查API密钥是否正确，确认服务器能访问硅基流动API
2. **生成速度慢**：图片生成通常需要30-60秒，请耐心等待
3. **无法自动添加图片**：确保`wp-content/uploads`目录有可写权限

更多详细信息请参考 [调试和故障排除指南](DEBUG_TROUBLESHOOTING.md)。

## 可用模型

### 文本生成模型（摘要）
- DeepSeek Chat
- DeepSeek Coder
- Qwen 2.5 7B / 72B
- Llama 3.1 8B / 70B

### 图片生成模型（特色图片）
- FLUX.1 Dev / Schnell
- Stable Diffusion 3
- Stable Diffusion XL

## Token统计

在"Token统计"页面可以查看：
- 总请求数
- 输入/输出Token数
- 各模型使用情况
- 最近30天使用趋势

## 注意事项

1. **API密钥安全**：API密钥将保存在WordPress数据库中，请确保网站安全
2. **生成速度**：图片生成需要较长时间（可能需要30-60秒），请耐心等待
3. **文章内容**：为保证生成质量，文章内容需要有一定长度（建议至少200字）
4. **配额限制**：根据硅基流动的配额限制，合理安排生成频率
5. **自动检查**：启用自动检查后，每小时只处理5篇文章，避免大量调用API

## 文件结构

```
ai-content-generator/
├── ai-content-generator.php          # 主插件文件
├── includes/                         # 核心类文件
│   ├── class-ai-content-generator.php        # 主类
│   ├── class-ai-content-generator-admin.php  # 管理类
│   ├── class-ai-content-generator-api.php    # API交互类
│   ├── class-ai-content-generator-stats.php  # Token统计类
│   └── class-ai-content-generator-background.php  # 后台任务类
├── templates/                        # 模板文件
│   ├── manage-page.php               # 文章管理页面
│   ├── settings-page.php             # 设置页面
│   └── stats-page.php                # 统计页面
├── assets/                           # 前端资源
│   ├── css/
│   │   └── admin.css                 # 后台样式
│   └── js/
│       └── admin.js                  # 后台脚本
└── README.md                         # 说明文档
```

## 数据库表

插件安装后会创建以下数据表：

- `wp_ai_cg_token_stats`：Token使用统计表

## 卸载说明

1. 停用插件
2. 删除插件文件
3. 如需删除数据，手动删除 `wp_ai_cg_token_stats` 表

## 常见问题

**Q: 为什么生成失败？**
A: 请检查API密钥是否正确，是否有足够配额，网络连接是否正常。

**Q: 如何修改生成的提示词？**
A: 修改 `includes/class-ai-content-generator-api.php` 中的 `generate_summary()` 和 `generate_featured_image()` 方法。

**Q: 支持自定义模型吗？**
A: 支持，在 `includes/class-ai-content-generator-api.php` 的 `get_available_models()` 方法中添加新模型即可。

**Q: 如何增加自动检查的数量？**
A: 在 `includes/class-ai-content-generator-background.php` 的 `hourly_check_posts()` 方法中修改限制数量。

## 技术支持

如有问题，请在GitHub提交Issue。

## 许可证

GPL v2 or later

## 更新日志

### 1.1.0
- 修复特色图片生成成功但不添加到文章的问题
- 优化图片文件扩展名识别逻辑
- 增强API返回数据验证机制
- 支持base64编码的图片返回
- 添加详细的错误日志记录
- 改进临时文件处理和清理
- 优化AJAX错误处理和反馈
- 新增日志导出功能，便于故障排除
- 改进用户界面和错误提示

### 1.0.0
- 初始版本发布
- 支持摘要和特色图片生成
- 支持多个AI模型
- Token统计功能
- 批量管理功能
