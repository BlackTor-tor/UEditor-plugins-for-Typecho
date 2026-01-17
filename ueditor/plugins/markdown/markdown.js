/**
 * UEditor Markdown 编辑模式插件
 * 支持 Markdown 语法编辑和实时预览
 * 
 * @author UEditor Plus
 * @version 2.0
 */

UE.plugins['markdown'] = function () {
    var me = this;
    var isMarkdownMode = false;
    var markdownContainer = null;
    var originalIframe = null;

    // 添加 Markdown 命令
    me.commands['markdown'] = {
        execCommand: function () {
            toggleMarkdownMode();
        },
        queryCommandState: function () {
            return isMarkdownMode ? 1 : 0;
        }
    };

    /**
     * 切换 Markdown 模式
     */
    function toggleMarkdownMode() {
        if (isMarkdownMode) {
            switchToRichText();
        } else {
            switchToMarkdown();
        }
    }

    /**
     * 切换到 Markdown 模式
     */
    function switchToMarkdown() {
        // 获取当前富文本内容
        var htmlContent = me.getContent();

        // 获取编辑器容器和 iframe
        var container = me.container;
        originalIframe = container.querySelector('iframe');

        // 创建 Markdown 编辑器（如果不存在）
        if (!markdownContainer) {
            createMarkdownEditor(container);
        }

        // 隐藏富文本 iframe
        if (originalIframe) {
            originalIframe.style.display = 'none';
        }

        // 显示 Markdown 容器
        markdownContainer.style.display = 'flex';

        // 将 HTML 转换为 Markdown
        var mdContent = htmlToMarkdown(htmlContent);
        var textarea = markdownContainer.querySelector('.md-textarea');
        if (textarea) {
            textarea.value = mdContent;
            updatePreview(mdContent);
        }

        isMarkdownMode = true;
    }

    /**
     * 切换回富文本模式
     */
    function switchToRichText() {
        // 获取 Markdown 内容
        var textarea = markdownContainer.querySelector('.md-textarea');
        var mdContent = textarea ? textarea.value : '';

        // 转换为 HTML
        var htmlContent = markdownToHtml(mdContent);

        // 隐藏 Markdown 容器
        if (markdownContainer) {
            markdownContainer.style.display = 'none';
        }

        // 显示富文本 iframe
        if (originalIframe) {
            originalIframe.style.display = '';
        }

        // 设置 HTML 内容到编辑器
        if (htmlContent) {
            me.setContent(htmlContent);
        }

        isMarkdownMode = false;
    }

    /**
     * 创建 Markdown 编辑器界面
     */
    function createMarkdownEditor(container) {
        var iframeHeight = originalIframe ? originalIframe.offsetHeight : 400;

        markdownContainer = document.createElement('div');
        markdownContainer.className = 'md-container';
        markdownContainer.style.cssText = [
            'display: none',
            'width: 100%',
            'height: ' + iframeHeight + 'px',
            'border: 1px solid #ccc',
            'box-sizing: border-box',
            'flex-direction: row'
        ].join(';');

        // 编辑区
        var editPanel = document.createElement('div');
        editPanel.style.cssText = 'flex:1;display:flex;flex-direction:column;border-right:1px solid #ddd;';

        var editHeader = document.createElement('div');
        editHeader.style.cssText = 'padding:6px 10px;background:#f7f7f7;border-bottom:1px solid #ddd;font-size:12px;color:#666;';
        editHeader.innerHTML = '<b>Markdown</b> 编辑';

        var textarea = document.createElement('textarea');
        textarea.className = 'md-textarea';
        textarea.style.cssText = [
            'flex: 1',
            'width: 100%',
            'padding: 10px',
            'border: none',
            'resize: none',
            'font-family: Consolas, Monaco, "Courier New", monospace',
            'font-size: 14px',
            'line-height: 1.6',
            'outline: none',
            'box-sizing: border-box'
        ].join(';');
        textarea.placeholder = '输入 Markdown 内容...\n\n# 标题\n**粗体** *斜体*\n- 列表项';

        editPanel.appendChild(editHeader);
        editPanel.appendChild(textarea);

        // 预览区
        var previewPanel = document.createElement('div');
        previewPanel.style.cssText = 'flex:1;display:flex;flex-direction:column;';

        var previewHeader = document.createElement('div');
        previewHeader.style.cssText = 'padding:6px 10px;background:#f7f7f7;border-bottom:1px solid #ddd;font-size:12px;color:#666;';
        previewHeader.innerHTML = '<b>预览</b>';

        var previewContent = document.createElement('div');
        previewContent.className = 'md-preview';
        previewContent.style.cssText = [
            'flex: 1',
            'padding: 10px',
            'overflow: auto',
            'font-size: 14px',
            'line-height: 1.6'
        ].join(';');

        previewPanel.appendChild(previewHeader);
        previewPanel.appendChild(previewContent);

        markdownContainer.appendChild(editPanel);
        markdownContainer.appendChild(previewPanel);

        // 插入到 iframe 后面
        if (originalIframe && originalIframe.parentNode) {
            originalIframe.parentNode.insertBefore(markdownContainer, originalIframe.nextSibling);
        } else {
            container.appendChild(markdownContainer);
        }

        // 实时预览
        textarea.addEventListener('input', function () {
            updatePreview(this.value);
        });
    }

    /**
     * 更新预览
     */
    function updatePreview(mdContent) {
        var preview = markdownContainer.querySelector('.md-preview');
        if (preview) {
            preview.innerHTML = markdownToHtml(mdContent || '');
        }
    }

    /**
     * Markdown 转 HTML
     */
    function markdownToHtml(md) {
        if (!md) return '';

        var html = md;

        // 代码块（先处理，避免被其他规则影响）
        html = html.replace(/```(\w*)\n([\s\S]*?)```/g, function (m, lang, code) {
            var brush = lang || 'plain';
            return '<pre class="brush:' + brush + ';toolbar:false">' + escapeHtml(code.trim()) + '</pre>';
        });

        // 标题
        html = html.replace(/^######\s+(.+)$/gm, '<h6>$1</h6>');
        html = html.replace(/^#####\s+(.+)$/gm, '<h5>$1</h5>');
        html = html.replace(/^####\s+(.+)$/gm, '<h4>$1</h4>');
        html = html.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>');
        html = html.replace(/^##\s+(.+)$/gm, '<h2>$1</h2>');
        html = html.replace(/^#\s+(.+)$/gm, '<h1>$1</h1>');

        // 粗体和斜体
        html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
        html = html.replace(/___(.+?)___/g, '<strong><em>$1</em></strong>');
        html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');
        html = html.replace(/_(.+?)_/g, '<em>$1</em>');

        // 删除线
        html = html.replace(/~~(.+?)~~/g, '<del>$1</del>');

        // 行内代码
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

        // 引用
        html = html.replace(/^>\s+(.+)$/gm, '<blockquote>$1</blockquote>');

        // 无序列表
        html = html.replace(/^[-*]\s+(.+)$/gm, '<li>$1</li>');
        html = html.replace(/(<li>[\s\S]+?<\/li>)\n(?=<li>)/g, '$1');
        html = html.replace(/(<li>[\s\S]+?<\/li>)+/g, '<ul>$&</ul>');

        // 有序列表
        html = html.replace(/^\d+\.\s+(.+)$/gm, '<oli>$1</oli>');
        html = html.replace(/(<oli>[\s\S]+?<\/oli>)+/g, function (m) {
            return '<ol>' + m.replace(/<\/?oli>/g, function (t) {
                return t.replace('oli', 'li');
            }) + '</ol>';
        });

        // 链接
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');

        // 图片
        html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1">');

        // 水平线
        html = html.replace(/^[-*]{3,}$/gm, '<hr>');

        // 段落
        var lines = html.split('\n');
        var result = [];
        var inParagraph = false;

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            if (!line) {
                if (inParagraph) {
                    result.push('</p>');
                    inParagraph = false;
                }
                continue;
            }

            // 跳过已处理的块级元素
            if (/^<(h[1-6]|ul|ol|li|blockquote|pre|hr|img)/.test(line)) {
                if (inParagraph) {
                    result.push('</p>');
                    inParagraph = false;
                }
                result.push(line);
                continue;
            }

            if (!inParagraph) {
                result.push('<p>');
                inParagraph = true;
            }
            result.push(line);
        }

        if (inParagraph) {
            result.push('</p>');
        }

        return result.join('\n');
    }

    /**
     * HTML 转 Markdown
     */
    function htmlToMarkdown(html) {
        if (!html) return '';

        var md = html;

        // 移除多余空白
        md = md.replace(/\s+/g, ' ');

        // 标题
        md = md.replace(/<h1[^>]*>(.*?)<\/h1>/gi, '\n# $1\n');
        md = md.replace(/<h2[^>]*>(.*?)<\/h2>/gi, '\n## $1\n');
        md = md.replace(/<h3[^>]*>(.*?)<\/h3>/gi, '\n### $1\n');
        md = md.replace(/<h4[^>]*>(.*?)<\/h4>/gi, '\n#### $1\n');
        md = md.replace(/<h5[^>]*>(.*?)<\/h5>/gi, '\n##### $1\n');
        md = md.replace(/<h6[^>]*>(.*?)<\/h6>/gi, '\n###### $1\n');

        // 段落和换行
        md = md.replace(/<p[^>]*>/gi, '\n');
        md = md.replace(/<\/p>/gi, '\n');
        md = md.replace(/<br\s*\/?>/gi, '\n');

        // 粗体斜体
        md = md.replace(/<strong[^>]*>(.*?)<\/strong>/gi, '**$1**');
        md = md.replace(/<b[^>]*>(.*?)<\/b>/gi, '**$1**');
        md = md.replace(/<em[^>]*>(.*?)<\/em>/gi, '*$1*');
        md = md.replace(/<i[^>]*>(.*?)<\/i>/gi, '*$1*');
        md = md.replace(/<del[^>]*>(.*?)<\/del>/gi, '~~$1~~');

        // 链接和图片
        md = md.replace(/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/gi, '[$2]($1)');
        md = md.replace(/<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*\/?>/gi, '![$2]($1)');
        md = md.replace(/<img[^>]*src="([^"]*)"[^>]*\/?>/gi, '![]($1)');

        // 代码块
        md = md.replace(/<pre[^>]*class="brush:(\w+)[^"]*"[^>]*>([\s\S]*?)<\/pre>/gi, '\n```$1\n$2\n```\n');
        md = md.replace(/<pre[^>]*>([\s\S]*?)<\/pre>/gi, '\n```\n$1\n```\n');
        md = md.replace(/<code[^>]*>(.*?)<\/code>/gi, '`$1`');

        // 引用
        md = md.replace(/<blockquote[^>]*>(.*?)<\/blockquote>/gi, '> $1\n');

        // 列表
        md = md.replace(/<li[^>]*>(.*?)<\/li>/gi, '- $1\n');
        md = md.replace(/<\/?[ou]l[^>]*>/gi, '\n');

        // 水平线
        md = md.replace(/<hr[^>]*\/?>/gi, '\n---\n');

        // 移除其他标签
        md = md.replace(/<[^>]+>/g, '');

        // 解码 HTML 实体
        md = md.replace(/&nbsp;/g, ' ');
        md = md.replace(/&amp;/g, '&');
        md = md.replace(/&lt;/g, '<');
        md = md.replace(/&gt;/g, '>');
        md = md.replace(/&quot;/g, '"');

        // 清理空行
        md = md.replace(/\n{3,}/g, '\n\n');

        return md.trim();
    }

    /**
     * 转义 HTML 特殊字符
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }
};

// 注册 Markdown 按钮 UI
UE.registerUI('markdown', function (editor, uiName) {
    var btn = new UE.ui.Button({
        name: uiName,
        title: 'Markdown 模式',
        cssRules: 'background-position: -760px -40px;',
        onclick: function () {
            editor.execCommand('markdown');
        }
    });

    editor.addListener('selectionchange', function () {
        var state = editor.queryCommandState('markdown');
        if (state === -1) {
            btn.setDisabled(true);
            btn.setChecked(false);
        } else {
            btn.setDisabled(false);
            btn.setChecked(state === 1);
        }
    });

    return btn;
});
