<?php
session_start();

// 管理员密码（可自行修改）
$admin_password = '123456';
$is_login = isset($_SESSION['admin_login']) && $_SESSION['admin_login'] === true;

// 处理管理员登录
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    $input_pwd = trim($_POST['login_password']);
    if ($input_pwd === $admin_password) {
        $_SESSION['admin_login'] = true;
        header("Location: admin_panel.php");
        exit;
    } else {
        $login_error = "密码错误，请输入正确的管理员密码！";
    }
}

// 处理管理员退出
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_login']);
    header("Location: admin_panel.php");
    exit;
}

// 登录验证：未登录则显示登录界面
if (!$is_login) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理员后台 - 登录</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: "Microsoft Yahei", Arial, sans-serif;
            }
            body {
                background-color: #f5f7fa;
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-box {
                width: 400px;
                background-color: #fff;
                padding: 30px 40px;
                border-radius: 12px;
                box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            }
            .login-title {
                text-align: center;
                color: #333;
                font-size: 22px;
                margin-bottom: 30px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }
            .form-group {
                margin-bottom: 25px;
            }
            .form-label {
                display: block;
                color: #555;
                font-size: 15px;
                margin-bottom: 10px;
                font-weight: 500;
            }
            input[type="password"] {
                width: 100%;
                padding: 12px 15px;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 14px;
                color: #333;
                outline: none;
                transition: border-color 0.3s;
            }
            input[type="password"]:focus {
                border-color: #409eff;
                box-shadow: 0 0 0 2px rgba(64,158,255,0.2);
            }
            .login-btn {
                width: 100%;
                padding: 14px;
                background-color: #409eff;
                color: #fff;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                transition: background-color 0.3s;
            }
            .login-btn:hover {
                background-color: #337ecc;
            }
            .error-alert {
                background-color: #f8d7da;
                color: #721c24;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2 class="login-title">管理员后台登录</h2>
            <?php if (isset($login_error)): ?>
                <div class="error-alert"><?php echo $login_error; ?></div>
            <?php endif; ?>
            <form method="post" action="">
                <div class="form-group">
                    <label class="form-label" for="login_password">管理员密码</label>
                    <input type="password" id="login_password" name="login_password" placeholder="请输入管理员密码" required>
                </div>
                <button type="submit" class="login-btn">登录</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -------------------------- 已登录后的管理员功能 --------------------------

// 定义文件路径
$reports_file = 'reports_data.txt';
$violation_file = 'violation_types.txt';
$config_file = 'config.txt';
$default_violations = ['色情', '谣言', '人身攻击', '侵权', '广告骚扰', '其他违规'];
$default_max_words = 2000;

// 读取举报数据
$all_reports = [];
if (file_exists($reports_file)) {
    $serialized_reports = file_get_contents($reports_file);
    $all_reports = unserialize($serialized_reports) ?: [];
}

// 读取违规分类数据
$all_violations = [];
if (file_exists($violation_file)) {
    $serialized_violations = file_get_contents($violation_file);
    $all_violations = unserialize($serialized_violations) ?: $default_violations;
} else {
    file_put_contents($violation_file, serialize($default_violations));
    $all_violations = $default_violations;
}

// 读取字数配置数据
$config = [];
if (file_exists($config_file)) {
    $serialized_config = file_get_contents($config_file);
    $config = unserialize($serialized_config) ?: [];
} else {
    $config = ['max_report_words' => $default_max_words];
    file_put_contents($config_file, serialize($config));
}
$current_max_words = isset($config['max_report_words']) ? (int)$config['max_report_words'] : $default_max_words;

// 长文本截断函数
function truncate_text($text, $length = 20) {
    $text = strip_tags($text);
    if (mb_strlen($text, 'UTF-8') > $length) {
        return mb_substr($text, 0, $length, 'UTF-8') . '...';
    }
    return $text;
}

// 处理POST操作（回复/删除/新增/编辑/分类管理/字数配置）
$operate_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. 回复举报
    if (isset($_POST['action']) && $_POST['action'] === 'reply_report') {
        $report_num = trim($_POST['report_number']);
        $admin_reply = trim(htmlspecialchars($_POST['admin_reply']));
        foreach ($all_reports as &$report) {
            if ($report['report_number'] === $report_num) {
                $report['admin_reply'] = $admin_reply;
                break;
            }
        }
        unset($report);
        file_put_contents($reports_file, serialize($all_reports));
        $operate_msg = "回复成功！";
    }

    // 2. 删除举报
    if (isset($_POST['action']) && $_POST['action'] === 'delete_report') {
        $report_num = trim($_POST['report_number']);
        $new_reports = array_filter($all_reports, function($report) use ($report_num) {
            return $report['report_number'] !== $report_num;
        });
        $all_reports = array_values($new_reports);
        file_put_contents($reports_file, serialize($all_reports));
        $operate_msg = "删除举报成功！";
    }

    // 3. 新增举报
    if (isset($_POST['action']) && $_POST['action'] === 'add_report') {
        $new_report_num = 'REP-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
        $new_violation = trim($_POST['add_violation']);
        $new_title = trim(htmlspecialchars($_POST['add_title']));
        $new_reason = trim(htmlspecialchars($_POST['add_reason']));
        $new_ip = trim($_POST['add_ip']);
        $new_report = [
            'report_number' => $new_report_num,
            'violation_types' => $new_violation,
            'title' => $new_title,
            'reason' => $new_reason,
            'submit_time' => date('Y-m-d H:i:s'),
            'user_ip' => $new_ip,
            'admin_reply' => ''
        ];
        array_unshift($all_reports, $new_report);
        file_put_contents($reports_file, serialize($all_reports));
        $operate_msg = "新增举报成功！";
    }

    // 4. 编辑举报
    if (isset($_POST['action']) && $_POST['action'] === 'edit_report') {
        $report_num = trim($_POST['report_number']);
        $edit_violation = trim($_POST['edit_violation']);
        $edit_title = trim(htmlspecialchars($_POST['edit_title']));
        $edit_reason = trim(htmlspecialchars($_POST['edit_reason']));
        $edit_ip = trim($_POST['edit_ip']);
        foreach ($all_reports as &$report) {
            if ($report['report_number'] === $report_num) {
                $report['violation_types'] = $edit_violation;
                $report['title'] = $edit_title;
                $report['reason'] = $edit_reason;
                $report['user_ip'] = $edit_ip;
                break;
            }
        }
        unset($report);
        file_put_contents($reports_file, serialize($all_reports));
        $operate_msg = "编辑举报成功！";
    }

    // 5. 新增违规分类
    if (isset($_POST['action']) && $_POST['action'] === 'add_violation') {
        $new_violation = trim($_POST['new_violation_type']);
        if (!empty($new_violation) && !in_array($new_violation, $all_violations)) {
            $all_violations[] = $new_violation;
            file_put_contents($violation_file, serialize($all_violations));
            $operate_msg = "新增违规分类成功！";
        } else {
            $operate_msg = "分类已存在或不能为空！";
        }
    }

    // 6. 删除违规分类
    if (isset($_POST['action']) && $_POST['action'] === 'delete_violation') {
        $del_violation = trim($_POST['del_violation_type']);
        $new_violations = array_filter($all_violations, function($v) use ($del_violation) {
            return $v !== $del_violation;
        });
        $all_violations = array_values($new_violations);
        file_put_contents($violation_file, serialize($all_violations));
        $operate_msg = "删除违规分类成功！";
    }

    // 7. 修改字数限制
    if (isset($_POST['action']) && $_POST['action'] === 'update_max_words') {
        $new_max_words = trim($_POST['new_max_words']);
        if (is_numeric($new_max_words) && (int)$new_max_words > 0) {
            $config['max_report_words'] = (int)$new_max_words;
            file_put_contents($config_file, serialize($config));
            $current_max_words = (int)$new_max_words;
            $operate_msg = "字数限制修改成功！当前最大字数：" . $current_max_words;
        } else {
            $operate_msg = "字数限制输入无效！请输入正整数（如：1000、2000）。";
        }
    }
}

// 处理举报搜索
$admin_search = isset($_GET['admin_search']) ? trim(htmlspecialchars($_GET['admin_search'])) : '';
$filtered_admin_reports = $all_reports;
if (!empty($admin_search)) {
    $filtered_admin_reports = array_filter($filtered_admin_reports, function($report) use ($admin_search) {
        return strpos($report['report_number'], $admin_search) !== false;
    });
    $filtered_admin_reports = array_values($filtered_admin_reports);
}

// 分页处理
$page_size = 10;
$total_count = count($filtered_admin_reports);
$total_pages = max(1, ceil($total_count / $page_size));
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $page_size;
$current_reports = array_slice($filtered_admin_reports, $offset, $page_size);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员后台 - 举报管理</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft Yahei", Arial, sans-serif;
        }
        body {
            background-color: #f5f7fa;
            padding: 20px 0;
        }
        .container {
            width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .title {
            text-align: center;
            color: #333;
            font-size: 24px;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .logout-btn {
            text-align: right;
            margin-bottom: 20px;
        }
        .logout-btn a {
            color: #ff4949;
            text-decoration: none;
            padding: 8px 15px;
            border: 1px solid #ff4949;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .logout-btn a:hover {
            background-color: #ff4949;
            color: #fff;
        }
        .success-msg {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            width: 1200px;
            margin: 0 auto 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            color: #555;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input[type="text"], select, textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            outline: none;
            transition: border-color 0.3s;
        }
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        input[type="text"]:focus, select:focus, textarea:focus {
            border-color: #409eff;
            box-shadow: 0 0 0 2px rgba(64,158,255,0.2);
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: #409eff;
            color: #fff;
        }
        .btn-primary:hover {
            background-color: #337ecc;
        }
        .btn-danger {
            background-color: #ff4949;
            color: #fff;
        }
        .btn-danger:hover {
            background-color: #e53935;
        }
        .btn-default {
            background-color: #909399;
            color: #fff;
        }
        .btn-default:hover {
            background-color: #73767a;
        }
        .report-table, .violation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .report-table th, .report-table td,
        .violation-table th, .violation-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            word-break: break-all;
        }
        .report-table th, .violation-table th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        .report-table td, .violation-table td {
            color: #666;
            font-size: 13px;
            vertical-align: top;
        }
        .report-table tr:hover, .violation-table tr:hover {
            background-color: #fafbfc;
        }
        .action-btns {
            display: flex;
            gap: 8px;
        }
        .search-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            align-items: flex-end;
        }
        .search-input {
            flex: 1;
            max-width: 300px;
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
        .module-box {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
        }
        .module-title {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 500;
        }
        .text-truncate {
            cursor: pointer;
            color: #409eff;
        }
        .text-truncate:hover {
            text-decoration: underline;
        }

        /* 浮窗样式修复：确保层级和显示正常 */
        .modal-mask {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
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
            overflow-y: auto;
            color: #666;
            line-height: 1.8;
            font-size: 14px;
            white-space: pre-wrap;
        }
        .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid #eee;
            text-align: right;
        }

        /* 回复/编辑浮窗样式修复 */
        .form-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }
        .form-modal-content {
            width: 500px;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- 操作成功提示 -->
    <?php if (!empty($operate_msg)): ?>
        <div class="success-msg"><?php echo $operate_msg; ?></div>
    <?php endif; ?>

    <!-- 举报数据管理容器 -->
    <div class="container">
        <div class="logout-btn">
            <a href="admin_panel.php?action=logout">退出管理员登录</a>
        </div>
        <h2 class="title">举报数据管理</h2>

        <!-- 搜索栏 -->
        <div class="search-bar">
            <div class="search-input">
                <label class="form-label">按举报号码搜索</label>
                <input type="text" name="admin_search" id="searchInput" placeholder="输入举报号码" value="<?php echo $admin_search; ?>">
            </div>
            <button type="button" class="btn btn-primary" onclick="submitSearch()">搜索</button>
            <a href="admin_panel.php" class="btn btn-default">重置</a>
        </div>

        <!-- 新增举报模块 -->
        <div class="module-box">
            <h3 class="module-title">新增举报（手动添加）</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="add_report">
                <div class="form-group">
                    <label class="form-label">违规分类</label>
                    <select name="add_violation" required>
                        <?php foreach ($all_violations as $v): ?>
                            <option value="<?php echo $v; ?>"><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">举报标题</label>
                    <input type="text" name="add_title" placeholder="输入举报标题" required>
                </div>
                <div class="form-group">
                    <label class="form-label">举报原因</label>
                    <textarea name="add_reason" placeholder="输入举报详细原因" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">举报者IP</label>
                    <input type="text" name="add_ip" placeholder="输入IP地址" required>
                </div>
                <button type="submit" class="btn btn-primary">新增举报</button>
            </form>
        </div>

        <!-- 举报列表 -->
        <?php if ($total_count > 0): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>举报号码</th>
                        <th>违规分类</th>
                        <th>举报标题</th>
                        <th>举报者IP</th>
                        <th>提交时间</th>
                        <th>举报详情</th>
                        <th>当前回复</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($current_reports as $report): ?>
                        <tr>
                            <td>
                                <div class="text-truncate" onclick="showCommonModal('举报号码', '<?php echo addslashes($report['report_number']); ?>')">
                                    <?php echo truncate_text($report['report_number'], 15); ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-truncate" onclick="showCommonModal('违规分类', '<?php echo addslashes($report['violation_types']); ?>')">
                                    <?php echo truncate_text($report['violation_types'], 15); ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-truncate" onclick="showCommonModal('举报标题', '<?php echo addslashes($report['title']); ?>')">
                                    <?php echo truncate_text($report['title'], 15); ?>
                                </div>
                            </td>
                            <td><?php echo $report['user_ip']; ?></td>
                            <td><?php echo $report['submit_time']; ?></td>
                            <td>
                                <div class="text-truncate" onclick="showCommonModal('举报详情', '<?php echo addslashes(nl2br($report['reason'])); ?>')">
                                    <?php echo truncate_text($report['reason'], 50); ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($report['admin_reply'])): ?>
                                    <div style="color: #155724; background: #f0f8fb; padding: 5px; border-radius: 4px;">
                                        <div class="text-truncate" onclick="showCommonModal('站长回复', '<?php echo addslashes(nl2br($report['admin_reply'])); ?>')">
                                            <?php echo truncate_text($report['admin_reply'], 30); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    暂无回复
                                <?php endif; ?>
                            </td>
                            <td class="action-btns">
                                <button type="button" class="btn btn-primary" onclick="showReplyModal('<?php echo $report['report_number']; ?>')">回复</button>
                                <button type="button" class="btn btn-default" onclick="showEditModal(
                                    '<?php echo $report['report_number']; ?>',
                                    '<?php echo $report['violation_types']; ?>',
                                    '<?php echo addslashes($report['title']); ?>',
                                    '<?php echo addslashes($report['reason']); ?>',
                                    '<?php echo $report['user_ip']; ?>'
                                )">编辑</button>
                                <form method="post" action="" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_report">
                                    <input type="hidden" name="report_number" value="<?php echo $report['report_number']; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('确定要删除该举报吗？删除后不可恢复！')">删除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- 分页 -->
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?admin_search=<?php echo $admin_search; ?>&page=<?php echo $current_page - 1; ?>">上一页</a>
                <?php else: ?>
                    <span class="disabled">上一页</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i === $current_page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?admin_search=<?php echo $admin_search; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?admin_search=<?php echo $admin_search; ?>&page=<?php echo $current_page + 1; ?>">下一页</a>
                <?php else: ?>
                    <span class="disabled">下一页</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; color: #999;">
                暂无举报数据（当前搜索条件下无结果）
            </div>
        <?php endif; ?>
    </div>

    <!-- 违规分类管理容器 -->
    <div class="container">
        <h2 class="title">违规分类管理</h2>
        <div class="module-box">
            <h3 class="module-title">新增违规分类</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="add_violation">
                <div class="form-group">
                    <label class="form-label">新分类名称</label>
                    <input type="text" name="new_violation_type" placeholder="输入违规分类名称（如：政治敏感）" required>
                </div>
                <button type="submit" class="btn btn-primary">新增分类</button>
            </form>
        </div>

        <?php if (count($all_violations) > 0): ?>
            <table class="violation-table">
                <thead>
                    <tr>
                        <th>分类序号</th>
                        <th>分类名称</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_violations as $key => $v): ?>
                        <tr>
                            <td><?php echo $key + 1; ?></td>
                            <td><?php echo $v; ?></td>
                            <td>
                                <form method="post" action="" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_violation">
                                    <input type="hidden" name="del_violation_type" value="<?php echo $v; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('确定要删除该分类吗？')">删除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; color: #999;">
                暂无违规分类数据
            </div>
        <?php endif; ?>
    </div>

    <!-- 字数限制配置容器 -->
    <div class="container">
        <h2 class="title">举报字数限制配置</h2>
        <div class="module-box">
            <h3 class="module-title">修改最大举报字数</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="update_max_words">
                <div class="form-group">
                    <label class="form-label" for="new_max_words">当前最大字数：<?php echo $current_max_words; ?></label>
                    <input type="text" id="new_max_words" name="new_max_words" 
                           value="<?php echo $current_max_words; ?>" 
                           placeholder="请输入正整数（如：1000、2000、5000）" required>
                    <p style="font-size: 13px; color: #666; margin-top: 8px;">
                        说明：该配置控制 report_submit.php 中「举报标题」和「举报原因」的最大输入字数，修改后实时生效。
                    </p>
                </div>
                <button type="submit" class="btn btn-primary">保存修改</button>
            </form>
        </div>
    </div>

    <!-- 通用查看浮窗 -->
    <div class="modal-mask" id="commonModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title" id="commonModalTitle">标题</h3>
                <button class="modal-close" onclick="hideCommonModal()">×</button>
            </div>
            <div class="modal-body" id="commonModalContent">
                <!-- 内容填充 -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" onclick="hideCommonModal()">关闭</button>
            </div>
        </div>
    </div>

    <!-- 回复浮窗 -->
    <div class="form-modal" id="replyModal">
        <div class="form-modal-content">
            <h3 style="text-align: center; color: #333; margin-bottom: 20px;">站长回复</h3>
            <form method="post" action="" id="replyForm">
                <input type="hidden" name="action" value="reply_report">
                <input type="hidden" name="report_number" id="replyReportNum" value="">
                <div class="form-group">
                    <label class="form-label">回复内容</label>
                    <textarea name="admin_reply" id="replyContent" placeholder="输入回复内容" required style="min-height: 100px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="submit" class="btn btn-primary">提交回复</button>
                    <button type="button" class="btn btn-default" onclick="hideReplyModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 编辑浮窗 -->
    <div class="form-modal" id="editModal">
        <div class="form-modal-content">
            <h3 style="text-align: center; color: #333; margin-bottom: 20px;">编辑举报</h3>
            <form method="post" action="" id="editForm">
                <input type="hidden" name="action" value="edit_report">
                <input type="hidden" name="report_number" id="editReportNum" value="">
                <div class="form-group">
                    <label class="form-label">违规分类</label>
                    <select name="edit_violation" id="editViolation" required>
                        <?php foreach ($all_violations as $v): ?>
                            <option value="<?php echo $v; ?>"><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">举报标题</label>
                    <input type="text" name="edit_title" id="editTitle" placeholder="输入举报标题" required>
                </div>
                <div class="form-group">
                    <label class="form-label">举报原因</label>
                    <textarea name="edit_reason" id="editReason" placeholder="输入举报详细原因" required style="min-height: 100px;"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">举报者IP</label>
                    <input type="text" name="edit_ip" id="editIp" placeholder="输入IP地址" required>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="submit" class="btn btn-primary">提交修改</button>
                    <button type="button" class="btn btn-default" onclick="hideEditModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 搜索提交函数
        function submitSearch() {
            const searchVal = document.getElementById('searchInput').value.trim();
            window.location.href = 'admin_panel.php?admin_search=' + encodeURIComponent(searchVal);
        }

        // 通用浮窗 - 显示
        function showCommonModal(title, content) {
            document.getElementById('commonModalTitle').innerText = title;
            document.getElementById('commonModalContent').innerHTML = content;
            document.getElementById('commonModal').style.display = 'flex';
        }

        // 通用浮窗 - 隐藏
        function hideCommonModal() {
            document.getElementById('commonModal').style.display = 'none';
            document.getElementById('commonModalContent').innerHTML = '';
        }

        // 回复浮窗 - 显示
        function showReplyModal(reportNum) {
            document.getElementById('replyReportNum').value = reportNum;
            document.getElementById('replyContent').value = '';
            document.getElementById('replyModal').style.display = 'flex';
        }

        // 回复浮窗 - 隐藏
        function hideReplyModal() {
            document.getElementById('replyModal').style.display = 'none';
        }

        // 编辑浮窗 - 显示
        function showEditModal(reportNum, violation, title, reason, ip) {
            document.getElementById('editReportNum').value = reportNum;
            document.getElementById('editViolation').value = violation;
            document.getElementById('editTitle').value = title;
            document.getElementById('editReason').value = reason;
            document.getElementById('editIp').value = ip;
            document.getElementById('editModal').style.display = 'flex';
        }

        // 编辑浮窗 - 隐藏
        function hideEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // 点击浮窗外层关闭
        window.onclick = function(event) {
            const commonModal = document.getElementById('commonModal');
            const replyModal = document.getElementById('replyModal');
            const editModal = document.getElementById('editModal');

            if (event.target === commonModal) {
                hideCommonModal();
            }
            if (event.target === replyModal) {
                hideReplyModal();
            }
            if (event.target === editModal) {
                hideEditModal();
            }
        };

        // ESC键关闭所有浮窗
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideCommonModal();
                hideReplyModal();
                hideEditModal();
            }
        });
    </script>
</body>
</html>