<?php
/**
 * BFS Enterprise Client-Side Hardening & Anti-Inspection Security Engine
 * 
 * 1. Output Buffer Sanitizer: Strips all HTML/CSS/JS developer comments before sending response to browser.
 * 2. Anti-DevTools & Anti-Inspect Shield: Blocks Right-Click, F12, Ctrl+Shift+I/J/C, Ctrl+U, Ctrl+S.
 * 3. Console Silencing: Prevents debug log leakage in browser console.
 */

if (!function_exists('bfs_sanitize_html_output')) {
    function bfs_sanitize_html_output($html) {
        if (empty($html) || !is_string($html)) return $html;

        // 1. Strip all HTML comments (e.g. <!-- Developer notes, section tags -->)
        $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);

        // 2. Strip comments from <style> blocks (/* ... */)
        $html = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function($matches) {
            $tag = $matches[0];
            $css = $matches[1];
            $cleanCss = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
            return str_replace($css, $cleanCss, $tag);
        }, $html);

        // 3. Strip multiline and standalone comments from <script> blocks
        $html = preg_replace_callback('/<script\b[^>]*>(.*?)<\/script>/is', function($matches) {
            $tag = $matches[0];
            $js = $matches[1];
            // Strip multiline /* ... */ comments
            $cleanJs = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
            // Strip standalone comment lines (// ...)
            $cleanJs = preg_replace('/^[ \t]*\/\/(?![\'"]).*$/m', '', $cleanJs);
            return str_replace($js, $cleanJs, $tag);
        }, $html);

        return $html;
    }
}

if (!function_exists('bfs_start_output_sanitizer')) {
    function bfs_start_output_sanitizer() {
        $handlers = ob_list_handlers();
        if (!in_array('bfs_sanitize_html_output', $handlers)) {
            ob_start('bfs_sanitize_html_output');
        }
    }
}

if (!function_exists('bfs_render_anti_inspect_shield')) {
    function bfs_render_anti_inspect_shield() {
        ?>
        <script>
        (function() {
            // 1. Disable Right Click (context menu) except inside editable text fields
            document.addEventListener('contextmenu', function(e) {
                var tag = e.target && e.target.tagName ? e.target.tagName.toLowerCase() : '';
                if (tag !== 'input' && tag !== 'textarea' && !e.target.isContentEditable) {
                    e.preventDefault();
                    return false;
                }
            }, { capture: true });

            // 2. Disable Developer Shortcut Keys (F12, Ctrl+Shift+I/J/C, Ctrl+U, Ctrl+S)
            document.addEventListener('keydown', function(e) {
                // F12 key
                if (e.key === 'F12' || e.keyCode === 123) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
                // Ctrl+Shift+I / J / C (DevTools & Inspector)
                if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
                // Ctrl+U (View Page Source) & Ctrl+S (Save Page)
                if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U' || e.key === 's' || e.key === 'S')) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, { capture: true });

            // 3. Silence Console Log Leakage in Browser
            try {
                var noop = function() {};
                window.console.log = noop;
                window.console.info = noop;
                window.console.warn = noop;
                window.console.debug = noop;
                window.console.dir = noop;
                window.console.table = noop;
                window.console.trace = noop;
            } catch (err) {}
        })();
        </script>
        <?php
    }
}
