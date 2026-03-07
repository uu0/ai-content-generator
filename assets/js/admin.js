(function($) {
    'use strict';

    // 文档加载完成
    $(document).ready(function() {
        initGenerateActions();
        initBulkActions();
        initSelectAll();
        initTestConnection();
        initClearStats();
        initLogActions();
        initRefreshModels();
    });

    // 初始化生成操作
    function initGenerateActions() {
        // 生成摘要
        $(document).on('click', '.ai-cg-generate-summary:not(.ai-cg-bulk-summary)', function(e) {
            e.preventDefault();
            generateSummary($(this));
        });

        // 生成图片
        $(document).on('click', '.ai-cg-generate-image:not(.ai-cg-bulk-image)', function(e) {
            e.preventDefault();
            generateFeaturedImage($(this));
        });
    }

    // 生成摘要
    function generateSummary(button) {
        var postId = button.data('post-id');

        if (!postId) {
            alert('无效的文章ID');
            return;
        }

        showLoading();

        $.ajax({
            url: ai_cg_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_cg_generate_summary',
                post_id: postId,
                nonce: ai_cg_data.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    alert('摘要生成成功！');
                    // 刷新页面
                    location.reload();
                } else {
                    alert('生成失败：' + response.data);
                }
            },
            error: function() {
                hideLoading();
                alert('网络错误，请稍后重试');
            }
        });
    }

    // 生成特色图片
    function generateFeaturedImage(button) {
        var postId = button.data('post-id');

        if (!postId) {
            alert('无效的文章ID');
            return;
        }

        showLoading(true); // 图片生成需要更长时间

        $.ajax({
            url: ai_cg_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_cg_generate_featured_image',
                post_id: postId,
                nonce: ai_cg_data.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    alert('特色图片生成成功！');
                    // 刷新页面
                    location.reload();
                } else {
                    alert('生成失败：' + response.data);
                }
            },
            error: function() {
                hideLoading();
                alert('网络错误，请稍后重试');
            }
        });
    }

    // 初始化批量操作
    function initBulkActions() {
        var bulkSummary = $('.ai-cg-bulk-summary');
        var bulkImage = $('.ai-cg-bulk-image');

        // 更新选中文章ID
        function updateSelectedIds() {
            var selectedIds = $('.ai-cg-post-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            bulkSummary.attr('data-post-ids', selectedIds.join(','));
            bulkImage.attr('data-post-ids', selectedIds.join(','));
        }

        $(document).on('change', '.ai-cg-post-checkbox', function() {
            updateSelectedIds();
        });

        // 批量生成摘要
        bulkSummary.on('click', function() {
            var postIds = $(this).attr('data-post-ids');
            if (!postIds) {
                alert('请先选择要处理的文章');
                return;
            }

            if (!confirm('确定要为选中的 ' + postIds.split(',').length + ' 篇文章生成摘要吗？')) {
                return;
            }

            generateBulkSummary(postIds);
        });

        // 批量生成图片
        bulkImage.on('click', function() {
            var postIds = $(this).attr('data-post-ids');
            if (!postIds) {
                alert('请先选择要处理的文章');
                return;
            }

            if (!confirm('确定要为选中的 ' + postIds.split(',').length + ' 篇文章生成特色图片吗？\n\n注意：图片生成需要较长时间，请耐心等待。')) {
                return;
            }

            generateBulkImage(postIds);
        });

        // 批量生成摘要
        function generateBulkSummary(postIds) {
            var ids = postIds.split(',');
            var currentIndex = 0;

            processNextSummary();

            function processNextSummary() {
                if (currentIndex >= ids.length) {
                    alert('批量生成摘要完成！');
                    location.reload();
                    return;
                }

                var postId = ids[currentIndex];
                $.ajax({
                    url: ai_cg_data.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ai_cg_generate_summary',
                        post_id: postId,
                        nonce: ai_cg_data.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('文章 ' + postId + ' 摘要生成成功');
                        } else {
                            console.error('文章 ' + postId + ' 摘要生成失败：', response.data);
                        }
                    },
                    error: function() {
                        console.error('文章 ' + postId + ' 请求失败');
                    },
                    complete: function() {
                        currentIndex++;
                        setTimeout(processNextSummary, 1000); // 避免请求过快
                    }
                });
            }
        }

        // 批量生成图片
        function generateBulkImage(postIds) {
            var ids = postIds.split(',');
            var currentIndex = 0;

            showLoading(true); // 批量图片生成需要很长时间

            processNextImage();

            function processNextImage() {
                if (currentIndex >= ids.length) {
                    hideLoading();
                    alert('批量生成图片完成！');
                    location.reload();
                    return;
                }

                var postId = ids[currentIndex];
                $.ajax({
                    url: ai_cg_data.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ai_cg_generate_featured_image',
                        post_id: postId,
                        nonce: ai_cg_data.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('文章 ' + postId + ' 图片生成成功');
                        } else {
                            console.error('文章 ' + postId + ' 图片生成失败：', response.data);
                        }
                    },
                    error: function() {
                        console.error('文章 ' + postId + ' 请求失败');
                    },
                    complete: function() {
                        currentIndex++;
                        setTimeout(processNextImage, 2000); // 图片生成需要更多时间
                    }
                });
            }
        }
    }

    // 初始化全选功能
    function initSelectAll() {
        $('#ai-cg-select-all').on('change', function() {
            var checked = $(this).prop('checked');
            $('.ai-cg-post-checkbox').prop('checked', checked);
        });
    }

    // 显示加载提示
    function showLoading(longTime = false) {
        $('#ai-cg-loading').show();
    }

    // 隐藏加载提示
    function hideLoading() {
        $('#ai-cg-loading').hide();
    }

    // 测试连接
    function initTestConnection() {
        $('#ai-cg-test-connection').on('click', function() {
            var button = $(this);
            var resultSpan = $('#ai-cg-test-result');

            button.prop('disabled', true);
            resultSpan.text('测试中...');

            $.ajax({
                url: ai_cg_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_cg_test_connection',
                    nonce: ai_cg_data.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resultSpan.html('<span style="color: green;">✓ 连接成功</span>');
                    } else {
                        resultSpan.html('<span style="color: red;">✗ 连接失败：' + response.data + '</span>');
                    }
                },
                error: function() {
                    resultSpan.html('<span style="color: red;">✗ 网络错误</span>');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        });
    }

    // 清除统计
    function initClearStats() {
        $('.ai-cg-clear-stats').on('click', function() {
            if (!confirm('确定要清除所有统计数据吗？此操作不可恢复！')) {
                return;
            }

            $.ajax({
                url: ai_cg_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_cg_clear_stats',
                    nonce: ai_cg_data.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('统计数据已清除');
                        location.reload();
                    } else {
                        alert('清除失败：' + response.data);
                    }
                },
                error: function() {
                    alert('网络错误，请稍后重试');
                }
            });
        });
    }

    // 日志操作
    function initLogActions() {
        // 导出日志
        $('#ai-cg-export-logs').on('click', function() {
            var button = $(this);
            var statusSpan = $('#ai-cg-logs-status');

            button.prop('disabled', true);
            statusSpan.text('正在导出...').css('color', '#666');

            $.ajax({
                url: ai_cg_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_cg_export_logs',
                    nonce: ai_cg_data.nonce
                },
                success: function(response) {
                    button.prop('disabled', false);
                    if (response.success) {
                        statusSpan.text('导出成功！').css('color', 'green');

                        // 使用XMLHttpRequest来处理文件下载
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', ai_cg_data.ajax_url, true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.responseType = 'blob';

                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                var blob = xhr.response;
                                var url = window.URL.createObjectURL(blob);
                                var a = document.createElement('a');
                                a.href = url;
                                a.download = 'ai-content-generator-logs-' + new Date().toISOString().replace(/[T:.]/g, '-') + '.txt';
                                document.body.appendChild(a);
                                a.click();
                                window.URL.revokeObjectURL(url);
                                document.body.removeChild(a);

                                setTimeout(function() {
                                    statusSpan.text('');
                                }, 3000);
                            } else {
                                statusSpan.text('导出失败，请重试').css('color', 'red');
                            }
                        };

                        xhr.onerror = function() {
                            button.prop('disabled', false);
                            statusSpan.text('网络错误，请稍后重试').css('color', 'red');
                        };

                        xhr.send('action=ai_cg_export_logs&nonce=' + ai_cg_data.nonce);
                    } else {
                        statusSpan.text('导出失败：' + response.data).css('color', 'red');
                    }
                },
                error: function() {
                    button.prop('disabled', false);
                    statusSpan.text('网络错误，请稍后重试').css('color', 'red');
                }
            });
        });

        // 查看日志 - 简化为提供实用建议
        $('#ai-cg-view-logs').on('click', function() {
            alert('查看日志功能提示：\n\n1. 点击"导出日志"按钮下载日志文件\n2. 使用文本编辑器（如记事本、VS Code等）打开\n3. 日志包含：配置信息、错误记录、Token统计等\n\n推荐在调试问题时导出日志进行详细分析。');
        });

        // 关闭日志预览（保留以防万一）
        $('#ai-cg-close-logs').on('click', function() {
            $('#ai-cg-logs-preview').slideUp();
        });
    }

    // 刷新模型列表
    function initRefreshModels() {
        $('#ai-cg-refresh-models').on('click', function() {
            var button = $(this);
            var statusSpan = $('#ai-cg-models-status');

            button.prop('disabled', true);
            button.find('.dashicons').addClass('dashicons-update-alt');
            button.find('.dashicons').css('animation', 'spin 1s linear infinite');
            statusSpan.text('正在刷新模型列表...').css('color', '#666');

            $.ajax({
                url: ai_cg_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_cg_refresh_models',
                    nonce: ai_cg_data.nonce
                },
                success: function(response) {
                    button.prop('disabled', false);
                    button.find('.dashicons').css('animation', '');

                    if (response.success) {
                        statusSpan.text('刷新成功！共 ' + response.data.total + ' 个模型').css('color', 'green');

                        // 更新模型计数
                        $('#ai-cg-chat-models-count').text(Object.keys(response.data.chat_models).length);
                        $('#ai-cg-image-models-count').text(Object.keys(response.data.image_models).length);

                        // 保存当前选中的模型
                        var currentSummaryModel = $('#ai-cg-summary-model').val();
                        var currentImageModel = $('#ai-cg-image-model').val();

                        // 更新摘要生成模型选项
                        var summarySelect = $('#ai-cg-summary-model');
                        summarySelect.empty();
                        var selectedExistsSummary = false;
                        $.each(response.data.chat_models, function(modelKey, modelName) {
                            summarySelect.append('<option value="' + modelKey + '">' + modelName + '</option>');
                            if (modelKey === currentSummaryModel) {
                                selectedExistsSummary = true;
                            }
                        });

                        // 如果之前选择的模型仍然存在，保持选中状态
                        if (selectedExistsSummary) {
                            summarySelect.val(currentSummaryModel);
                        }

                        // 更新图片生成模型选项
                        var imageSelect = $('#ai-cg-image-model');
                        imageSelect.empty();
                        var selectedExistsImage = false;
                        $.each(response.data.image_models, function(modelKey, modelName) {
                            imageSelect.append('<option value="' + modelKey + '">' + modelName + '</option>');
                            if (modelKey === currentImageModel) {
                                selectedExistsImage = true;
                            }
                        });

                        // 如果之前选择的模型仍然存在，保持选中状态
                        if (selectedExistsImage) {
                            imageSelect.val(currentImageModel);
                        }

                        // 3秒后清除状态信息
                        setTimeout(function() {
                            statusSpan.text('最后更新：' + new Date().toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })).css('color', '#999');

                            // 5分钟后清除
                            setTimeout(function() {
                                statusSpan.text('');
                            }, 300000);
                        }, 3000);
                    } else {
                        statusSpan.text('刷新失败：' + response.data.message).css('color', 'red');
                    }
                },
                error: function() {
                    button.prop('disabled', false);
                    button.find('.dashicons').css('animation', '');
                    statusSpan.text('网络错误，请稍后重试').css('color', 'red');
                }
            });
        });
    }

})(jQuery);

// 添加旋转动画
$(function() {
    $("<style>")
        .prop("type", "text/css")
        .html("\
            @keyframes spin {\
                from { transform: rotate(0deg); }\
                to { transform: rotate(360deg); }\
            }\
        ")
        .appendTo("head");
});
