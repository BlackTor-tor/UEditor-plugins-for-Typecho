<?php
/**
 * 为Typecho启用UEditor编辑器(支持云存储)
 *
 * @package UEditor-plus for Typecho
 * @author BlackTor
 * @version 2.0
 * @link http://www.blacktor.cn
 * DateTime: 2026年1月17日17:22:25
 */
class UEditor_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('admin/write-post.php')->richEditor = array('UEditor_Plugin', 'render');
        Typecho_Plugin::factory('admin/write-page.php')->richEditor = array('UEditor_Plugin', 'render');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('UEditor_Plugin', 'renderFrontend');
        
        Helper::addPanel(0, 'UEditor/ueditor/ueditor.config.js.php','', '', 'contributor');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        Helper::removePanel(0, 'UEditor/ueditor/ueditor.config.js.php');
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form){
        /** 使用UPYUN */
        $c1 = new Typecho_Widget_Helper_Form_Element_Radio('cloud',
            array(
                '0' => '不使用',
                'upyun' => '又拍云(upyun)',
                'qcloud_cos' => '腾讯云COS',
            ),
            '0', '是否使用云服务器存储?', '开启后会把图片和文件上传到云服务器上');
        $form->addInput($c1);

        $c1 = new Typecho_Widget_Helper_Form_Element_Checkbox('cloud_only', array('cloud_only' => '图片上传到云服务器后删除本服务器上对应的文件'), array(), '图片仅上传到云服务器', '如果勾选，则把图片文件上传到云服务器并删除本地服务器上对应的文件');
        $form->addInput($c1);

        $c1 = new Typecho_Widget_Helper_Form_Element_Text('cloud_url', NULL, NULL, 'Bucket空间域名', '大概是这样的:http://bucket.b0.upaiyun.com, 或使用你绑定的域名,这是访问你上传文件的域名<br/>前面要带http或者https,后面不要带斜杆等符号');
        $form->addInput($c1);
        
        $c1 = new Typecho_Widget_Helper_Form_Element_Text('cloud_bucket', NULL, NULL, 'Bucket空间名称', '例如bucket');
        $form->addInput($c1);

        $c1 = new Typecho_Widget_Helper_Form_Element_Text('cloud_user', NULL, NULL, '操作员', '对应的bucket写入权限的账号(操作员/secretId/AccessKeyId)');
        $form->addInput($c1);

        $c1 = new Typecho_Widget_Helper_Form_Element_Password('cloud_password', NULL, NULL, '密码', '对应的正确的密码(操作员密码/secretKey/AccessKeySecret)');
        $form->addInput($c1);

        $c1 = new Typecho_Widget_Helper_Form_Element_Password('cloud_qcloud_appid', NULL, NULL, 'appid', '腾讯云COS的appid');
        $form->addInput($c1);

        $c1 = new Typecho_Widget_Helper_Form_Element_Text('cloud_qcloud_region', NULL, NULL, '腾讯云COS地域简称代码', '腾讯云COS的地域简称代码,其值可以为下列之一:cn-east, cn-sorth, cn-north, cn-south-2, cn-southwest, sg, tj, bj, sh, gz, cd, sgp, ap-guangzhou等');
        $form->addInput($c1);

        $c1 = new Typecho_Widget_Helper_Form_Element_Text('cloud_suffix', NULL, NULL, '缩略图版本', '在文件URL后添加的内容,upyun用户常用功能,例如 !default');
        $form->addInput($c1);

        /** 代码高亮主题 */
        $c1 = new Typecho_Widget_Helper_Form_Element_Radio('code_theme',
            array(
                'light' => '浅色主题',
                'dark' => '深色主题 (VS Code Dark+)',
            ),
            'dark', '代码高亮主题', '选择前台文章代码块的显示主题');
        $form->addInput($c1);

        /** 图片水印功能 */
        $c1 = new Typecho_Widget_Helper_Form_Element_Radio('watermark_enable',
            array(
                '0' => '关闭',
                '1' => '开启',
            ),
            '0', '图片水印', '上传图片时是否自动添加水印');
        $form->addInput($c1);

        $c1 = new Typecho_Widget_Helper_Form_Element_Select('watermark_opacity',
            array(
                '30' => '30%',
                '50' => '50%',
                '70' => '70%',
                '100' => '100%',
            ),
            '50', '水印透明度', '水印的透明度设置');
        $form->addInput($c1);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 插件实现方法
     * 
     * @access public
     * @return void
     */
    public static function render($post)
    {
        $options = Helper::options();
        $configJs = Typecho_Common::url('extending.php?panel=UEditor/ueditor/ueditor.config.js.php', $options->adminUrl);
        $js = Typecho_Common::url('UEditor/ueditor/ueditor.all.js', $options->pluginUrl);
        
        // 获取代码主题配置
        $codeTheme = $options->plugin('UEditor')->code_theme ?: 'dark';

        echo '<script type="text/javascript" src="'. $configJs. '"></script><script type="text/javascript" src="'. $js. '"></script>';
        
        echo '<script type="text/javascript">
        // 设置代码主题
        window.UEDITOR_CODE_THEME = "'. $codeTheme .'";
        
        
        var ue1;
        window.onload = function() {
            // 渲染编辑器
            ue1 = UE.getEditor("text");
        }
    
        // 保存草稿时同步
        document.getElementById("btn-save").onclick = function() {
            ue1.sync("text");
        }

        // 提交时同步
        document.getElementById("btn-submit").onclick = function() {
            ue1.sync("text");
        }
        </script>';
    }

    /**
     * 前台渲染代码高亮
     *
     * @access public
     * @return void
     */
    public static function renderFrontend()
    {
        $options = Helper::options();
        $rootUrl = Typecho_Common::url(__TYPECHO_PLUGIN_DIR__. '/UEditor/ueditor/', $options->siteUrl);
        $codeTheme = $options->plugin('UEditor')->code_theme ?: 'dark';

        // 添加复制按钮样式
        echo '<style>
        .code-copy-btn {
            position: absolute; top: 8px; right: 8px;
            padding: 4px 8px; font-size: 12px;
            color: #858585; background: #2d2d2d;
            border: 1px solid #3c3c3c; border-radius: 4px;
            cursor: pointer; opacity: 0; transition: opacity 0.2s;
            z-index: 10;
        }
        .syntaxhighlighter:hover .code-copy-btn { opacity: 1; }
        .code-copy-btn:hover { background: #3c3c3c; color: #fff; }
        .code-copy-btn.copied { background: #4CAF50; color: #fff; }
        .syntaxhighlighter { position: relative; }
        </style>';

        echo '<script type="text/javascript" src="'. $rootUrl . 'ueditor.parse.js"></script>';
        echo '<script type="text/javascript">
        window.UEDITOR_CODE_THEME = "'. $codeTheme .'";
        
        // 智能代码格式化函数
        function formatCodeBlock(code) {
            // 如果代码已经包含换行符，不处理
            if (code.indexOf("\\n") > -1) return code;
            
            var formatted = code;
            // 在 { 后添加换行
            formatted = formatted.replace(/\{(\s*)/g, "{\\n");
            // 在 } 前添加换行
            formatted = formatted.replace(/(\s*)\}/g, "\\n}");
            // 在 ; 后添加换行（除非在 for 循环中）
            formatted = formatted.replace(/;(\s*)(?!.*\))/g, ";\\n");
            // 清理多余的空格
            formatted = formatted.replace(/\\n\\s+/g, "\\n    ");
            // 清理连续换行
            formatted = formatted.replace(/\\n\\n+/g, "\\n");
            return formatted.trim();
        }
        
        if (typeof window.uParse === "function") {
            // 尝试自动查找文章内容容器
            var container = ".post-content";
            if (document.querySelector(".entry-content")) container = ".entry-content";
            else if (document.querySelector(".article-content")) container = ".article-content";
            else if (document.querySelector(".post-body")) container = ".post-body";
            if (!document.querySelector(container)) container = "body";

            // 处理代码块
            try {
                document.querySelectorAll("pre[class*=brush]").forEach(function(n) {
                    var code = n.textContent || n.innerText;
                    // 检测是否需要格式化（代码在一行且包含 { } 等特征）
                    if (code.indexOf("\\n") === -1 && (code.indexOf("{") > -1 || code.indexOf(";") > -1)) {
                        n.textContent = formatCodeBlock(code);
                    }
                });
            } catch(e) { console.log("Code format error:", e); }

            window.uParse(container, { rootPath: "'. $rootUrl .'" });
            
            // 添加复制按钮功能
            setTimeout(function() {
                document.querySelectorAll(".syntaxhighlighter").forEach(function(block) {
                    if (block.querySelector(".code-copy-btn")) return;
                    var btn = document.createElement("button");
                    btn.className = "code-copy-btn";
                    btn.textContent = "复制";
                    btn.onclick = function() {
                        // 提取纯代码，排除行号
                        var lines = block.querySelectorAll("td.code .line");
                        var code = "";
                        if (lines.length > 0) {
                            lines.forEach(function(line) {
                                code += (line.textContent || line.innerText) + "\\n";
                            });
                        } else {
                            var codeEl = block.querySelector("td.code") || block;
                            code = codeEl.textContent || codeEl.innerText;
                        }
                        code = code.replace(/^\\s+|\\s+$/g, ""); // trim
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(code).then(function() {
                                btn.textContent = "已复制!";
                                btn.classList.add("copied");
                                setTimeout(function() {
                                    btn.textContent = "复制";
                                    btn.classList.remove("copied");
                                }, 2000);
                            });
                        } else {
                            var ta = document.createElement("textarea");
                            ta.value = code;
                            ta.style.cssText = "position:fixed;opacity:0";
                            document.body.appendChild(ta);
                            ta.select();
                            document.execCommand("copy");
                            document.body.removeChild(ta);
                            btn.textContent = "已复制!";
                            btn.classList.add("copied");
                            setTimeout(function() {
                                btn.textContent = "复制";
                                btn.classList.remove("copied");
                            }, 2000);
                        }
                    };
                    block.appendChild(btn);
                });
            }, 500);
        }
        </script>';
    }
}
