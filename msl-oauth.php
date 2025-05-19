<?php
/**
 * Plugin Name: MSL Account OAuth
 * Plugin URI: https://github.com/MSLTeam
 * Description: 为您的WordPress集成MSL统一身份验证(用户中心)登录！
 * Version: 1.0
 * Plugin Name: PDFjs Viewer - Embed PDFs
 * Author: <a href="https://github.com/MSLTeam">MSLTeam</a> <a href="https://github.com/luluxiaoyu">luluxiaoyu</a>
 * Contributors: luluxiaoyu
 * License: GPL-3.0
 */

defined('ABSPATH') || exit;

// 定义常量
define('MSL_OAUTH_PATH', plugin_dir_path(__FILE__));
define('MSL_OAUTH_URL', plugin_dir_url(__FILE__));

// 包含必要文件
require_once MSL_OAUTH_PATH . 'includes/class-msl-oauth.php';
require_once MSL_OAUTH_PATH . 'admin/settings.php';

register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('msl_oauth_clear_expired_transients')) {
        wp_schedule_event(time(), 'daily', 'msl_oauth_clear_expired_transients');
    }
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('msl_oauth_clear_expired_transients');
});

// 初始化OAuth处理器
MSL_OAuth::instance()->init();

// 添加登录按钮
add_action('login_form', function() {
    $url = MSL_OAuth::instance()->get_authorization_url('login');
    echo '<div class="msl-oauth-container" style="margin:1em 0;">';
    echo '<a href="'.$url.'" class="button button-primary" style="background:#0073aa;color:#fff;padding:10px;text-decoration:none;display:block;text-align:center;">使用MSL账户登录</a>';
    echo '</div>';
});

// 用户资料绑定功能
add_action('show_user_profile', function($user) {
    if (isset($_GET['msl_unbind'])) {
    echo '<div class="notice notice-success"><p>已成功解除MSL账户绑定</p></div>';
}
    if (isset($_GET['msl_bind_uid'])) {
    echo '<div class="notice notice-success"><p>已成功绑定MSL账户！</p></div>';
}
    $uid = get_user_meta($user->ID, 'msl_oauth_uid', true);
    ?>
    <h3>MSL账户绑定</h3>
    <table class="form-table">
        <tr>
            <th>当前绑定状态</th>
            <td>
                <?php if ($uid) : ?>
                    <p style="color:green">已绑定 (UID: <?php echo esc_html($uid); ?>)</p>
                    <a class="button" style="margin-top: 6px;" href="<?php echo wp_nonce_url(
    add_query_arg('action', 'msl_unbind', admin_url('profile.php')),
    'msl_unbind_nonce'
) ?>">解除绑定</a>
                <?php else : ?>
                    <p style="color:#666">未绑定</p>
                    <p><a style="margin-top: 6px;" href="<?php echo MSL_OAuth::instance()->get_authorization_url('bind') ?>" class="button">绑定MSL账户</a></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
});

// 处理回调
add_action('login_init', function() {
    if (isset($_GET['msl_oauth'])) {
        MSL_OAuth::instance()->handle_callback();
        exit;
    }
});

add_action('login_message', function($message) {
    if (isset($_GET['msl_oauth_error'])) {
        $error_messages = [
            'unbound' => '该MSL账户未绑定，请先使用密码登录后在个人中心绑定'
        ];
        $error_code = sanitize_text_field($_GET['msl_oauth_error']);
        return '<div id="login_error">'.esc_html($error_messages[$error_code] ?? $_GET['msl_oauth_error']).'</div>';
    }
    return $message;
});

add_action('init', function() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'msl_unbind') return;

    // 验证权限
    if (!is_user_logged_in()) {
        wp_die('请先登录');
    }

    // 验证nonce
    if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'msl_unbind_nonce')) {
        wp_die('安全验证失败');
    }

    // 执行解绑
    delete_user_meta(get_current_user_id(), 'msl_oauth_uid');
    
    // 重定向回资料页
    wp_redirect(admin_url('profile.php?msl_unbind=1'));
    exit;
});

// 添加样式
add_action('login_enqueue_scripts', function() {
    wp_enqueue_style('msl-oauth', MSL_OAUTH_URL . 'assets/style.css');
});