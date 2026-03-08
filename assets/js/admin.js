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
        initAutoFilter();
        initModals();
    });

    // 自动筛选功能
    function initAutoFilter() {
        var filterSelect = $('#ai-cg-filter');

        // 只在元素存在时绑定事件
        if (filterSelect.length > 0) {
            filterSelect.on('change', function() {
                // 获取当前URL
                var url = new URL(window.location.href);

                // 更新或添加filter参数
                var filterValue = $(this).val();
                if (filterValue === 'all') {
                    url.searchParams.delete('filter');
                } else {
                    url.searchParams.set('filter', filterValue);
                }

                // 跳转到新URL
                window.location.href = url.toString();
            });
        }
    }

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

        // 生成图片描述
        $(document).on('click', '.ai-cg-generate-image-description', function(e) {
            e.preventDefault();
            generateImageDescription($(this));
        });

        // AI润色
        $(document).on('click', '.ai-cg-polish:not(.ai-cg-bulk-polish)', function(e) {
            e.preventDefault();
            polishContent($(this));
        });

        // AI排版
        $(document).on('click', '.ai-cg-reformat:not(.ai-cg-bulk-reformat)', function(e) {
            e.preventDefault();
            reformatContent($(this));
        });

        // 排除文章
        $(document).on('click', '.ai-cg-exclude', function(e) {
            e.preventDefault();
            excludePost($(this));
        });

        // 取消排除文章
        $(document).on('click', '.ai-cg-unexclude', function(e) {
            e.preventDefault();
            unexcludePost($(this));
        });

        // 撤回操作
        $(document).on('click', '.ai-cg-undo', function(e) {
            e.preventDefault();
            undoOperation($(this));
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

    // 生成图片描述
    function generateImageDescription(button) {
        var postId = button.data('post-id');

        if (!postId) {
            alert('无效的文章ID');
            return;
        }

        if (!confirm('确定要为这篇文章的图片生成描述并重命名吗？\n\n注意：此操作会修改图片文件名，请确保已备份。')) {
            return;
        }

        showLoading(true); // 图片描述需要较长时间

        $.ajax({
            url: ai_cg_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_cg_generate_image_description',
                post_id: postId,
                nonce: ai_cg_data.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    // 如果有部分失败，显示详情
                    if (response.data.renamed < response.data.total && response.data.results) {
                        var errorCount = response.data.total - response.data.renamed;
                        var errorMsg = '图片描述生成完成！\n\n处理了 ' + response.data.total + ' 张图片，成功重命名 ' + response.data.renamed + ' 张，失败 ' + errorCount + ' 张。\n\n失败原因：\n';

                        // 显示前3个失败项
                        var errorItems = response.data.results.filter(function(item) {
                            return item.status === 'error';
                        }).slice(0, 3);

                        errorItems.forEach(function(item) {
                            errorMsg += '- 附件ID ' + item.attachment_id + ': ' + item.message + '\n';
                        });

                        if (errorCount > 3) {
                            errorMsg += '... 还有 ' + (errorCount - 3) + ' 个失败项';
                        }

                        alert(errorMsg);
                    } else {
                        alert('图片描述生成成功！\n\n处理了 ' + response.data.total + ' 张图片，重命名 ' + response.data.renamed + ' 张。');
                    }
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

    // AI润色
    function polishContent(button) {
        var postId = button.data('post-id');

        if (!postId) {
            alert('无效的文章ID');
            return;
        }

        // 显示润色风格选择模态框
        $('#ai-cg-polish-modal').data('post-id', postId).fadeIn();
        $('#ai-cg-polish-style').val('normal');
    }

    // AI排版
    function reformatContent(button) {
        var postId = button.data('post-id');

        if (!postId) {
            alert('无效的文章ID');
            return;
        }

        // 显示排版类型选择模态框
        $('#ai-cg-reformat-modal').data('post-id', postId).fadeIn();
        $('#ai-cg-reformat-type').val('standard');
    }

    // 初始化批量操作
    function initBulkActions() {
        var bulkSummary = $('.ai-cg-bulk-summary');
        var bulkImage = $('.ai-cg-bulk-image');
        var bulkPolish = $('.ai-cg-bulk-polish');
        var bulkReformat = $('.ai-cg-bulk-reformat');
        var bulkDescription = $('.ai-cg-bulk-description');
        var bulkExclude = $('.ai-cg-bulk-exclude');
        var bulkUnexclude = $('.ai-cg-bulk-unexclude');

        // 更新选中文章ID
        function updateSelectedIds() {
            var selectedIds = $('.ai-cg-post-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            bulkSummary.attr('data-post-ids', selectedIds.join(','));
            bulkImage.attr('data-post-ids', selectedIds.join(','));
            bulkPolish.attr('data-post-ids', selectedIds.join(','));
            bulkReformat.attr('data-post-ids', selectedIds.join(','));
            bulkDescription.attr('data-post-ids', selectedIds.join(','));
            bulkExclude.attr('data-post-ids', selectedIds.join(','));
            bulkUnexclude.attr('data-post-ids', selectedIds.join(','));
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

        // 批量润色
        bulkPolish.on('click', function() {
            var postIds = $(this).attr('data-post-ids');
            if (!postIds) {
                alert('请先选择要处理的文章');
                return;
            }

            // 显示润色风格选择模态框
            $('#ai-cg-polish-modal').data('post-ids', postIds).fadeIn();
            $('#ai-cg-polish-style').val('normal');
        });

        // 批量排版
        bulkReformat.on('click', function() {
            var postIds = $(this).attr('data-post-ids');
            if (!postIds) {
                alert('请先选择要处理的文章');
                return;
            }

            // 显示排版类型选择模态框
            $('#ai-cg-reformat-modal').data('post-ids', postIds).fadeIn();
            $('#ai-cg-reformat-type').val('standard');
        });

        // 批量图片描述
        var bulkDescription = $('.ai-cg-bulk-description');
        bulkDescription.on('click', function() {
            var postIds = $(this).attr('data-post-ids');
            if (!postIds) {
                alert('请先选择要处理的文章');
                return;
            }

            if (!confirm('确定要为选中的 ' + postIds.split(',').length + ' 篇文章的图片生成描述并重命名吗？\n\n注意：此操作会修改图片文件名，请确保已备份。')) {
                return;
            }

            generateBulkDescription(postIds);
        });

        // 批量排除
        var bulkExclude = $('.ai-cg-bulk-exclude');
        bulkExclude.on('click', function() {
            var postIds = $(this).attr('data-post-ids');
            if (!postIds) {
                alert('请先选择要排除的文章');
                return;
            }

            if (!confirm('确定要将选中的 ' + postIds.split(',').length + ' 篇文章添加到排除列表吗？')) {
                return;
            }

            bulkExcludePosts(postIds);
        });

        // 批量取消排除
        var bulkUnexclude = $('.ai-cg-bulk-unexclude');
        bulkUnexclude.on('click', function() {
            var postIds = $(this).attr('data-post-ids');
            if (!postIds) {
                alert('请先选择要取消排除的文章');
                return;
            }

            if (!confirm('确定要将选中的 ' + postIds.split(',').length + ' 篇文章从排除列表中移除吗？')) {
                return;
            }

            bulkUnexcludePosts(postIds);
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

        // 批量润色
        function generateBulkPolish(postIds, style) {
            var ids = postIds.split(',');
            var currentIndex = 0;

            showLoading();

            processNextPolish();

            function processNextPolish() {
                if (currentIndex >= ids.length) {
                    hideLoading();
                    alert('批量润色完成！');
                    location.reload();
                    return;
                }

                var postId = ids[currentIndex];
                $.ajax({
                    url: ai_cg_data.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ai_cg_polish_content',
                        post_id: postId,
                        style: style,
                        nonce: ai_cg_data.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('文章 ' + postId + ' 润色成功');
                        } else {
                            console.error('文章 ' + postId + ' 润色失败：', response.data);
                        }
                    },
                    error: function() {
                        console.error('文章 ' + postId + ' 请求失败');
                    },
                    complete: function() {
                        currentIndex++;
                        setTimeout(processNextPolish, 1000); // 避免请求过快
                    }
                });
            }
        }

        // 批量排版
        function generateBulkReformat(postIds, formatType) {
            var ids = postIds.split(',');
            var currentIndex = 0;

            showLoading();

            processNextReformat();

            function processNextReformat() {
                if (currentIndex >= ids.length) {
                    hideLoading();
                    alert('批量排版完成！');
                    location.reload();
                    return;
                }

                var postId = ids[currentIndex];
                $.ajax({
                    url: ai_cg_data.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ai_cg_reformat_content',
                        post_id: postId,
                        format_type: formatType,
                        nonce: ai_cg_data.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('文章 ' + postId + ' 排版成功');
                        } else {
                            console.error('文章 ' + postId + ' 排版失败：', response.data);
                        }
                    },
                    error: function() {
                        console.error('文章 ' + postId + ' 请求失败');
                    },
                    complete: function() {
                        currentIndex++;
                        setTimeout(processNextReformat, 1000); // 避免请求过快
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

    // 批量图片描述
    function generateBulkDescription(postIds) {
        var ids = postIds.split(',');
        var currentIndex = 0;

        showLoading(true); // 批量描述需要较长时间

        function processNextDescription() {
            if (currentIndex >= ids.length) {
                hideLoading();
                alert('批量图片描述完成！');
                location.reload();
                return;
            }

            var postId = ids[currentIndex];
            $.ajax({
                url: ai_cg_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_cg_generate_image_description',
                    post_id: postId,
                    nonce: ai_cg_data.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('文章 ' + postId + ' 图片描述成功，处理了 ' + response.data.total + ' 张图片，重命名 ' + response.data.renamed + ' 张');
                    } else {
                        console.error('文章 ' + postId + ' 图片描述失败：', response.data);
                    }
                },
                error: function() {
                    console.error('文章 ' + postId + ' 请求失败');
                },
                complete: function() {
                    currentIndex++;
                    setTimeout(processNextDescription, 2000); // 避免请求过快，图片处理需要时间
                }
            });
        }

        processNextDescription();
    }

    // 模态框初始化
    function initModals() {
        // 排版模态框确定按钮
        $('#ai-cg-reformat-confirm').on('click', function() {
            var modal = $('#ai-cg-reformat-modal');
            var formatType = $('#ai-cg-reformat-type').val();
            var postId = modal.data('post-id');
            var postIds = modal.data('post-ids');

            modal.fadeOut();

            // 单篇文章排版
            if (postId) {
                // 先预览内容
                showLoading();

                $.ajax({
                    url: ai_cg_data.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ai_cg_preview_operation',
                        post_id: postId,
                        operation_type: 'reformat',
                        format_type: formatType,
                        nonce: ai_cg_data.nonce
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            // 显示预览对话框
                            $('#ai-cg-preview-description').text('以下是排版后的内容预览（只调整格式，不修改文字内容）：');
                            $('#ai-cg-preview-content').html(response.data.new_content);
                            $('#ai-cg-preview-modal')
                                .data('post-id', postId)
                                .data('operation-type', 'reformat')
                                .data('format-type', formatType)
                                .fadeIn();
                        } else {
                            alert('预览失败：' + response.data);
                        }
                    },
                    error: function() {
                        hideLoading();
                        alert('网络错误，请稍后重试');
                    }
                });
            }
            // 批量排版
            else if (postIds) {
                if (!confirm('确定要为选中的 ' + postIds.split(',').length + ' 篇文章进行排版吗？\n\n注意：排版将只调整HTML标签格式，不修改任何文字内容。')) {
                    return;
                }

                generateBulkReformat(postIds, formatType);
            }
        });

        // 排版模态框取消按钮
        $('#ai-cg-reformat-cancel').on('click', function() {
            $('#ai-cg-reformat-modal').fadeOut();
        });

        // 润色模态框确定按钮
        $('#ai-cg-polish-confirm').on('click', function() {
            var modal = $('#ai-cg-polish-modal');
            var style = $('#ai-cg-polish-style').val();
            var postId = modal.data('post-id');
            var postIds = modal.data('post-ids');

            modal.fadeOut();

            // 单篇文章润色
            if (postId) {
                // 先预览内容
                showLoading();

                $.ajax({
                    url: ai_cg_data.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ai_cg_preview_operation',
                        post_id: postId,
                        operation_type: 'polish',
                        style: style,
                        nonce: ai_cg_data.nonce
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            // 显示预览对话框
                            $('#ai-cg-preview-description').text('以下是润色后的内容预览（保留原有HTML结构，仅改善文字表达）：');
                            $('#ai-cg-preview-content').html(response.data.new_content);
                            $('#ai-cg-preview-modal')
                                .data('post-id', postId)
                                .data('operation-type', 'polish')
                                .data('style', style)
                                .fadeIn();
                        } else {
                            alert('预览失败：' + response.data);
                        }
                    },
                    error: function() {
                        hideLoading();
                        alert('网络错误，请稍后重试');
                    }
                });
            }
            // 批量润色
            else if (postIds) {
                if (!confirm('确定要为选中的 ' + postIds.split(',').length + ' 篇文章进行润色吗？\n\n注意：润色将修改文章文字内容，但保留原有的HTML结构。')) {
                    return;
                }

                generateBulkPolish(postIds, style);
            }
        });

        // 润色模态框取消按钮
        $('#ai-cg-polish-cancel').on('click', function() {
            $('#ai-cg-polish-modal').fadeOut();
        });

        // 预览模态框发布按钮
        $('#ai-cg-preview-publish').on('click', function() {
            var modal = $('#ai-cg-preview-modal');
            var postId = modal.data('post-id');
            var operationType = modal.data('operation-type');

            modal.fadeOut();

            // 确认发布
            if (!confirm('确认要发布修改后的内容吗？\n\n原始内容将被保存以便撤回。')) {
                return;
            }

            showLoading();

            // 根据操作类型调用对应的发布接口
            var action = operationType === 'polish' ? 'ai_cg_polish_content' : 'ai_cg_reformat_content';
            var params = {
                action: action,
                post_id: postId,
                nonce: ai_cg_data.nonce
            };

            if (operationType === 'polish') {
                params.style = modal.data('style');
            } else {
                params.format_type = modal.data('format-type');
            }

            $.ajax({
                url: ai_cg_data.ajax_url,
                type: 'POST',
                data: params,
                success: function(response) {
                    hideLoading();
                    if (response.success) {
                        alert((operationType === 'polish' ? '润色' : '排版') + '成功！内容已保存。');
                        location.reload();
                    } else {
                        alert('发布失败：' + response.data);
                    }
                },
                error: function() {
                    hideLoading();
                    alert('网络错误，请稍后重试');
                }
            });
        });

        // 预览模态框取消按钮
        $('#ai-cg-preview-cancel').on('click', function() {
            $('#ai-cg-preview-modal').fadeOut();
        });

        // 点击模态框外部关闭
        $(window).on('click', function(e) {
            if ($(e.target).is('#ai-cg-reformat-modal, #ai-cg-polish-modal, #ai-cg-preview-modal')) {
                $(e.target).fadeOut();
            }
        });
    }

    // 批量排除
    function bulkExcludePosts(postIds) {
        showLoading();

        $.ajax({
            url: ai_cg_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_cg_batch_exclude',
                post_ids: postIds,
                nonce: ai_cg_data.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    alert('批量排除成功！已添加 ' + response.data.added + ' 篇文章到排除列表，共 ' + response.data.total + ' 篇。');
                    location.reload();
                } else {
                    alert('排除失败：' + response.data);
                }
            },
            error: function() {
                hideLoading();
                alert('网络错误，请稍后重试');
            }
        });
    }

    // 批量取消排除
    function bulkUnexcludePosts(postIds) {
        showLoading();

        $.ajax({
            url: ai_cg_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_cg_remove_from_excluded',
                post_ids: postIds,
                nonce: ai_cg_data.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    alert('取消排除成功！已从排除列表移除 ' + response.data.removed + ' 篇文章。');
                    location.reload();
                } else {
                    alert('取消排除失败：' + response.data);
                }
            },
            error: function() {
                hideLoading();
                alert('网络错误，请稍后重试');
            }
        });
    }

    // 排除单篇文章
    function excludePost(button) {
        var postId = button.data('post-id');

        if (!postId) {
            alert('无效的文章ID');
            return;
        }

        if (!confirm('确定要将这篇文章添加到排除列表吗？')) {
            return;
        }

        showLoading();

        $.ajax({
            url: ai_cg_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_cg_batch_exclude',
                post_ids: postId,
                nonce: ai_cg_data.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    alert('已添加到排除列表！');
                    location.reload();
                } else {
                    alert('排除失败：' + response.data);
                }
            },
            error: function() {
                hideLoading();
                alert('网络错误，请稍后重试');
            }
        });
    }

    // 取消排除单篇文章
    function unexcludePost(button) {
        var postId = button.data('post-id');

        if (!postId) {
            alert('无效的文章ID');
            return;
        }

        if (!confirm('确定要移除这篇文章的排除状态吗？')) {
            return;
        }

        showLoading();

        $.ajax({
            url: ai_cg_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_cg_remove_from_excluded',
                post_ids: postId,
                nonce: ai_cg_data.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    alert('已从排除列表移除！');
                    location.reload();
                } else {
                    alert('取消排除失败：' + response.data);
                }
            },
            error: function() {
                hideLoading();
                alert('网络错误，请稍后重试');
            }
        });
    }

    // 撤回操作
    function undoOperation(button) {
        var postId = button.data('post-id');
        var operationType = button.data('operation-type');

        if (!postId) {
            alert('无效的文章ID');
            return;
        }

        var opText = operationType === 'polish' ? '润色' : '排版';
        if (!confirm('确定要撤回' + opText + '操作吗？\n\n此操作将恢复到修改前的内容。')) {
            return;
        }

        showLoading();

        $.ajax({
            url: ai_cg_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ai_cg_undo_operation',
                post_id: postId,
                nonce: ai_cg_data.nonce
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    alert('已撤回' + opText + '操作！');
                    location.reload();
                } else {
                    alert('撤回失败：' + response.data);
                }
            },
            error: function() {
                hideLoading();
                alert('网络错误，请稍后重试');
            }
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
