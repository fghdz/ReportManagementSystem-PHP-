<?php
// 读取字数限制配置
$config_file = 'config.txt';
$default_max_words = 2000;
$max_words = $default_max_words;

// 加载配置（优先读取文件配置，无文件则使用默认值并生成配置文件）
if (file_exists($config_file)) {
    $serialized_config = file_get_contents($config_file);
    $config = unserialize($serialized_config) ?: [];
    $max_words = isset($config['max_report_words']) ? (int)$config['max_report_words'] : $default_max_words;
} else {
    // 初始化配置文件
    $init_config = ['max_report_words' => $default_max_words];
    file_put_contents($config_file, serialize($init_config));
}

// 处理举报提交
$submit_msg = '';
$is_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    // 获取表单数据并过滤
    $report_title = trim(htmlspecialchars($_POST['report_title']));
    $report_reason = trim(htmlspecialchars($_POST['report_reason']));
    $user_ip = $_SERVER['REMOTE_ADDR']; // 获取用户IP

    // 处理违规分类（复选框多选，拼接为字符串存储）
    $violation_types = '';
    if (isset($_POST['violation_type']) && is_array($_POST['violation_type'])) {
        $violation_arr = array_map('trim', array_map('htmlspecialchars', $_POST['violation_type']));
        $violation_types = implode('，', $violation_arr);
    }

    // 标题和原因字数校验（共用同一最大字数限制）
    $title_length = mb_strlen($report_title, 'UTF-8');
    $reason_length = mb_strlen($report_reason, 'UTF-8');
    $has_error = false;

    if ($title_length > $max_words) {
        $submit_msg = "举报标题字数超出限制！当前{$title_length}字，最大支持{$max_words}字，请删减后提交。";
        $has_error = true;
    } elseif ($reason_length > $max_words) {
        $submit_msg = "举报原因字数超出限制！当前{$reason_length}字，最大支持{$max_words}字，请删减后提交。";
        $has_error = true;
    } elseif (empty($violation_types) || empty($report_title) || empty($report_reason)) {
        $submit_msg = "所有字段均为必填项，请完整填写！";
        $has_error = true;
    } else {
        // 生成唯一举报编号
        $report_number = 'REP-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
        // 组装举报数据
        $report_data = [
            'report_number' => $report_number,
            'violation_types' => $violation_types,
            'title' => $report_title,
            'reason' => $report_reason,
            'submit_time' => date('Y-m-d H:i:s'),
            'user_ip' => $user_ip,
            'admin_reply' => ''
        ];

        // 存储举报数据到文件
        $reports_file = 'reports_data.txt';
        $all_reports = [];
        if (file_exists($reports_file)) {
            $serialized_reports = file_get_contents($reports_file);
            $all_reports = unserialize($serialized_reports) ?: [];
        }
        // 新增举报插入到数组头部
        array_unshift($all_reports, $report_data);
        file_put_contents($reports_file, serialize($all_reports));

        // 提交成功提示
        $submit_msg = "举报提交成功！您的举报编号：{$report_number}，请妥善保存以便查询结果。";
        $is_success = true;
    }
}

// 读取违规分类（保留原有逻辑，用于渲染复选框）
$violation_file = 'violation_types.txt';
$default_violations = ['色情', '谣言', '人身攻击', '侵权', '广告骚扰', '其他违规'];
$all_violations = $default_violations;
if (file_exists($violation_file)) {
    $serialized_violations = file_get_contents($violation_file);
    $all_violations = unserialize($serialized_violations) ?: $default_violations;
} else {
    file_put_contents($violation_file, serialize($default_violations));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>举报提交</title>
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
            width: 800px;
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
        input[type="text"], textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            outline: none;
            transition: border-color 0.3s;
        }
        textarea {
            min-height: 180px;
            resize: vertical;
        }
        input[type="text"]:focus, textarea:focus {
            border-color: #409eff;
            box-shadow: 0 0 0 2px rgba(64,158,255,0.2);
        }
        .submit-btn {
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
        .submit-btn:hover {
            background-color: #337ecc;
        }
        .msg-alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        .success-msg {
            background-color: #d4edda;
            color: #155724;
        }
        .error-msg {
            background-color: #f8d7da;
            color: #721c24;
        }
        .word-count {
            text-align: right;
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
        .word-count.warning {
            color: #ff4949;
            font-weight: 500;
        }
        .query-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .query-link a {
            color: #409eff;
            text-decoration: none;
        }
        .query-link a:hover {
            text-decoration: underline;
        }
        /* 违规分类复选框样式（保留原有，不修改） */
        .violation-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: #666;
        }
        input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="title">违规内容举报</h2>

        <!-- 提交提示信息 -->
        <?php if (!empty($submit_msg)): ?>
            <div class="msg-alert <?php echo $is_success ? 'success-msg' : 'error-msg'; ?>">
                <?php echo $submit_msg; ?>
            </div>
        <?php endif; ?>

        <!-- 举报表单（保留复选框多选，标题加字数限制） -->
        <form method="post" action="" id="reportForm">
            <div class="form-group">
                <label class="form-label">违规分类（可多选）</label>
                <!-- 保留复选框多选，不做任何修改 -->
                <div class="violation-checkboxes">
                    <?php foreach ($all_violations as $v): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" name="violation_type[]" id="violation_<?php echo md5($v); ?>" value="<?php echo $v; ?>">
                            <label for="violation_<?php echo md5($v); ?>"><?php echo $v; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="report_title">举报的文章标题</label>
                <input type="text" id="report_title" name="report_title" placeholder="请简要填写举报标题（如：把Ubuntu/Debian系统设置为高性能模式）（最大<?php echo $max_words; ?>字）" required>
                <div class="word-count" id="titleWordCount">当前字数：0 / 最大<?php echo $max_words; ?>字</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="report_reason">举报详细原因</label>
                <textarea id="report_reason" name="report_reason" placeholder="请详细描述违规内容、场景等（如：你这是什么网站啊，害人不浅啊你这网站，我儿子自从看了你这篇文章后，把家里电脑设置成高性能模式了，导致我家交不起电费了啊）（最大<?php echo $max_words; ?>字）" required></textarea>
                <div class="word-count" id="reasonWordCount">当前字数：0 / 最大<?php echo $max_words; ?>字</div>
            </div>

            <button type="submit" name="submit_report" class="submit-btn">提交举报</button>
        </form>

        <div class="query-link">
            <a href="report_results.php">点击此处查询举报结果</a>
        </div>
    </div>

    <script>
        // 获取元素
        const reportTitle = document.getElementById('report_title');
        const reportReason = document.getElementById('report_reason');
        const titleWordCount = document.getElementById('titleWordCount');
        const reasonWordCount = document.getElementById('reasonWordCount');
        const reportForm = document.getElementById('reportForm');
        const maxWords = <?php echo $max_words; ?>; // 从PHP获取全局最大字数限制

        // 通用中文字数统计函数（与PHP mb_strlen保持一致）
        function mbStrLen(str) {
            let len = 0;
            for (let i = 0; i < str.length; i++) {
                const charCode = str.charCodeAt(i);
                // 匹配中文及全角字符
                if (charCode >= 0x4E00 && charCode <= 0x9FFF || 
                    charCode >= 0x3400 && charCode <= 0x4DBF ||
                    charCode >= 0x20000 && charCode <= 0x2A6DF ||
                    charCode >= 0x2A700 && charCode <= 0x2B73F ||
                    charCode >= 0x2B740 && charCode <= 0x2B81F ||
                    charCode >= 0x2B820 && charCode <= 0x2CEAF ||
                    charCode >= 0xF900 && charCode <= 0xFAFF ||
                    charCode >= 0x2F800 && charCode <= 0x2FA1F) {
                    len += 1;
                } else {
                    len += 1; // 英文、数字、符号占1个长度
                }
            }
            return len;
        }

        // 标题实时字数统计
        reportTitle.addEventListener('input', function() {
            const content = this.value;
            const length = mbStrLen(content);
            // 更新显示
            titleWordCount.textContent = `当前字数：${length} / 最大${maxWords}字`;
            // 超出警示
            if (length > maxWords) {
                titleWordCount.classList.add('warning');
            } else {
                titleWordCount.classList.remove('warning');
            }
        });

        // 原因实时字数统计（原有逻辑保留）
        reportReason.addEventListener('input', function() {
            const content = this.value;
            const length = mbStrLen(content);
            // 更新显示
            reasonWordCount.textContent = `当前字数：${length} / 最大${maxWords}字`;
            // 超出警示
            if (length > maxWords) {
                reasonWordCount.classList.add('warning');
            } else {
                reasonWordCount.classList.remove('warning');
            }
        });

        // 表单提交拦截（校验标题和原因字数 + 复选框必填）
        reportForm.addEventListener('submit', function(e) {
            const titleContent = reportTitle.value;
            const reasonContent = reportReason.value;
            const titleLength = mbStrLen(titleContent);
            const reasonLength = mbStrLen(reasonContent);
            const checkedCheckboxes = document.querySelectorAll('input[name="violation_type[]"]:checked');

            // 标题字数校验
            if (titleLength > maxWords) {
                e.preventDefault();
                alert(`举报标题字数超出限制！当前${titleLength}字，最大支持${maxWords}字，请删减后提交。`);
                reportTitle.focus();
                return;
            }

            // 原因字数校验
            if (reasonLength > maxWords) {
                e.preventDefault();
                alert(`举报原因字数超出限制！当前${reasonLength}字，最大支持${maxWords}字，请删减后提交。`);
                reportReason.focus();
                return;
            }

            // 违规分类复选框校验（至少选一个）
            if (checkedCheckboxes.length === 0) {
                e.preventDefault();
                alert('请至少选择一个违规分类！');
                return;
            }
        });
    </script>
</body>
</html>