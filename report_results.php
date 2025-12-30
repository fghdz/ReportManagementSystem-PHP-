<?php
// 读取举报数据文件
$file_path = 'reports_data.txt';
$all_reports = [];
if (file_exists($file_path)) {
    $serialized_data = file_get_contents($file_path);
    $all_reports = unserialize($serialized_data) ?: [];
}

// 读取违规分类（用于筛选）
$violation_file = 'violation_types.txt';
$default_types = ['色情', '谣言', '人身攻击', '侵权', '广告骚扰', '其他违规'];
$all_violation_types = [];
if (file_exists($violation_file)) {
    $types_data = file_get_contents($violation_file);
    $all_violation_types = unserialize($types_data) ?: $default_types;
} else {
    $all_violation_types = $default_types;
}

// 1. 处理搜索（按举报号码）
$search_keyword = isset($_GET['search']) ? trim(htmlspecialchars($_GET['search'])) : '';
$filtered_reports = $all_reports;
if (!empty($search_keyword)) {
    $filtered_reports = array_filter($filtered_reports, function($report) use ($search_keyword) {
        return strpos($report['report_number'], $search_keyword) !== false;
    });
    $filtered_reports = array_values($filtered_reports);
}

// 2. 处理筛选（按违规分类）
$filter_type = isset($_GET['filter_type']) ? trim(htmlspecialchars($_GET['filter_type'])) : '';
if (!empty($filter_type) && $filter_type !== 'all') {
    $filtered_reports = array_filter($filtered_reports, function($report) use ($filter_type) {
        return strpos($report['violation_types'], $filter_type) !== false;
    });
    $filtered_reports = array_values($filtered_reports);
}

// 3. 处理分页（每页10条）
$page_size = 10;
$total_count = count($filtered_reports);
$total_pages = max(1, ceil($total_count / $page_size));
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, min($current_page, $total_pages)); // 限制页码范围
$offset = ($current_page - 1) * $page_size;
$current_page_reports = array_slice($filtered_reports, $offset, $page_size);

// 长文本截断函数（统一复用）
function truncate_text($text, $length = 20) {
    $text = strip_tags($text);
    if (mb_strlen($text, 'UTF-8') > $length) {
        return mb_substr($text, 0, $length, 'UTF-8') . '...';
    }
    return $text;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>举报结果查询</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft Yahei", Arial, sans-serif;
        }
        body {
            background-color: #f5f7fa;
            padding: 30px 0;
        }
        .container {
            width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        .title {
            text-align: center;
            color: #333;
            font-size: 24px;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .search-filter {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-group, .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .search-label, .filter-label {
            display: block;
            color: #555;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input[type="text"], select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            outline: none;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus, select:focus {
            border-color: #409eff;
            box-shadow: 0 0 0 2px rgba(64,158,255,0.2);
        }
        .search-btn {
            padding: 10px 20px;
            background-color: #409eff;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
            height: 40px;
            margin-top: 24px;
        }
        .search-btn:hover {
            background-color: #337ecc;
        }
        .reset-btn {
            padding: 10px 20px;
            background-color: #909399;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
            height: 40px;
            margin-top: 24px;
        }
        .reset-btn:hover {
            background-color: #73767a;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .report-table th, .report-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            word-break: break-all;
        }
        .report-table th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        .report-table td {
            color: #666;
            font-size: 13px;
            vertical-align: top;
        }
        .report-table tr:hover {
            background-color: #fafbfc;
        }
        .reply-content {
            color: #155724;
            background-color: #f0f8fb;
            padding: 8px;
            border-radius: 4px;
            margin-top: 5px;
        }
        .text-truncate {
            cursor: pointer;
            color: #409eff;
        }
        .text-truncate:hover {
            text-decoration: underline;
        }
        .no-data {
            text-align: center;
            padding: 50px;
            color: #999;
            font-size: 16px;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            color: #666;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .pagination a:hover {
            background-color: #409eff;
            color: #fff;
            border-color: #409eff;
        }
        .pagination .current {
            background-color: #409eff;
            color: #fff;
            border-color: #409eff;
        }
        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
            border-color: #eee;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .back-link a {
            color: #409eff;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }

        /* 弹窗样式（统一美观） */
        .modal-mask {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }
        .modal-container {
            width: 80%;
            max-width: 700px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: modal-fade 0.3s ease-in-out;
        }
        @keyframes modal-fade {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .modal-header {
            padding: 16px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title {
            font-size: 18px;
            color: #333;
            font-weight: 500;
        }
        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #ff4949;
            color: #fff;
            border: none;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
        }
        .modal-close:hover {
            background-color: #e53935;
        }
        .modal-body {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto; /* 滑动展示完整内容 */
            color: #666;
            line-height: 1.8;
            font-size: 14px;
            white-space: pre-wrap; /* 保留换行和空格 */
        }
        .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid #eee;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="title">举报结果总览</h2>

        <!-- 搜索和筛选区域 -->
        <form method="get" action="" class="search-filter">
            <div class="search-group">
                <label class="search-label">按举报号码搜索</label>
                <input type="text" name="search" placeholder="输入举报号码（如：REP-20251229...）" value="<?php echo $search_keyword; ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label">按违规分类筛选</label>
                <select name="filter_type">
                    <option value="all" <?php echo $filter_type === 'all' || empty($filter_type) ? 'selected' : ''; ?>>全部分类</option>
                    <?php foreach ($all_violation_types as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                            <?php echo $type; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="search-btn">查询</button>
            <a href="report_results.php" class="reset-btn">重置</a>
        </form>

        <!-- 举报列表表格 -->
        <?php if ($total_count > 0): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>举报号码</th>
                        <th>违规分类</th>
                        <th>举报标题</th>
                        <th>提交时间</th>
                        <th>举报详情</th>
                        <th>站长回复</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($current_page_reports as $report): ?>
                        <tr>
                            <!-- 举报号码 - 截断+弹窗 -->
                            <td>
                                <div class="text-truncate" onclick="showModal('举报号码', '<?php echo addslashes($report['report_number']); ?>')">
                                    <?php echo truncate_text($report['report_number'], 15); ?>
                                </div>
                            </td>
                            <!-- 违规分类 - 截断+弹窗 -->
                            <td>
                                <div class="text-truncate" onclick="showModal('违规分类', '<?php echo addslashes($report['violation_types']); ?>')">
                                    <?php echo truncate_text($report['violation_types'], 15); ?>
                                </div>
                            </td>
                            <!-- 举报标题 - 截断+弹窗 -->
                            <td>
                                <div class="text-truncate" onclick="showModal('举报标题', '<?php echo addslashes($report['title']); ?>')">
                                    <?php echo truncate_text($report['title'], 15); ?>
                                </div>
                            </td>
                            <td><?php echo $report['submit_time']; ?></td>
                            <!-- 举报详情 - 截断+弹窗 -->
                            <td>
                                <div class="text-truncate" onclick="showModal('举报详情', '<?php echo addslashes(nl2br($report['reason'])); ?>')">
                                    <?php echo truncate_text($report['reason'], 50); ?>
                                </div>
                            </td>
                            <!-- 站长回复 - 截断+弹窗 -->
                            <td>
                                <?php if (!empty($report['admin_reply'])): ?>
                                    <div class="reply-content">
                                        <div class="text-truncate" onclick="showModal('站长回复', '<?php echo addslashes(nl2br($report['admin_reply'])); ?>')">
                                            <?php echo truncate_text($report['admin_reply'], 30); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    暂无回复
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- 分页组件 -->
            <div class="pagination">
                <!-- 上一页 -->
                <?php if ($current_page > 1): ?>
                    <a href="?search=<?php echo $search_keyword; ?>&filter_type=<?php echo $filter_type; ?>&page=<?php echo $current_page - 1; ?>">上一页</a>
                <?php else: ?>
                    <span class="disabled">上一页</span>
                <?php endif; ?>

                <!-- 页码 -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i === $current_page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?search=<?php echo $search_keyword; ?>&filter_type=<?php echo $filter_type; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <!-- 下一页 -->
                <?php if ($current_page < $total_pages): ?>
                    <a href="?search=<?php echo $search_keyword; ?>&filter_type=<?php echo $filter_type; ?>&page=<?php echo $current_page + 1; ?>">下一页</a>
                <?php else: ?>
                    <span class="disabled">下一页</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- 无数据提示 -->
            <div class="no-data">
                暂无举报数据 <?php if (!empty($search_keyword) || !empty($filter_type)): ?>（当前搜索/筛选条件下无结果）<?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 返回举报页面 -->
        <div class="back-link">
            <a href="report_submit.php">返回举报提交页面</a>
        </div>
    </div>

    <!-- 通用弹窗（统一复用，支持所有字段） -->
    <div class="modal-mask" id="commonModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">标题</h3>
                <button class="modal-close" onclick="hideModal()">×</button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- 弹窗内容 -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" onclick="hideModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #ddd; background: #f8f9fa; color: #666; cursor: pointer;">
                    关闭
                </button>
            </div>
        </div>
    </div>

    <script>
        // 显示弹窗
        function showModal(title, content) {
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalContent').innerHTML = content;
            document.getElementById('commonModal').style.display = 'flex';
        }

        // 隐藏弹窗
        function hideModal() {
            document.getElementById('commonModal').style.display = 'none';
            document.getElementById('modalContent').innerHTML = '';
        }

        // 点击弹窗外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('commonModal');
            if (event.target === modal) {
                hideModal();
            }
        }

        // 按ESC键关闭弹窗
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideModal();
            }
        });
    </script>
</body>
</html>