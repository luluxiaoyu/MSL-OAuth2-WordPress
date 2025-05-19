<?php
add_action('admin_menu', function() {
    add_options_page(
        'MSL OAuth设置',
        'MSL OAuth',
        'manage_options',
        'msl-oauth-settings',
        'msl_oauth_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('msl_oauth_settings_group', 'msl_oauth_settings');

    add_settings_section(
        'msl_oauth_main',
        'API设置',
        function() {
            echo '<p>从MSL用户中心-<a href="https://user.mslmc.net/user/oauth" target="_blank">OAuth App管理</a>页面新建应用并填入下方。</p>';
        },
        'msl-oauth-settings'
    );

    add_settings_field(
        'client_id',
        'Client ID',
        function() {
            $options = get_option('msl_oauth_settings');
            echo '<input type="text" name="msl_oauth_settings[client_id]" value="'.esc_attr($options['client_id'] ?? '').'" class="regular-text">';
        },
        'msl-oauth-settings',
        'msl_oauth_main'
    );

    add_settings_field(
        'client_secret',
        'Client Secret',
        function() {
            $options = get_option('msl_oauth_settings');
            echo '<input type="password" name="msl_oauth_settings[client_secret]" value="'.esc_attr($options['client_secret'] ?? '').'" class="regular-text">';
        },
        'msl-oauth-settings',
        'msl_oauth_main'
    );
});

function msl_oauth_settings_page() {
    ?>
    <div class="wrap">
        <h1>MSL OAuth设置</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('msl_oauth_settings_group');
            do_settings_sections('msl-oauth-settings');
            submit_button();
            ?>
        </form>
        
        <h3>回调地址</h3>
        <p>请将以下回调地址配置到MSL OAuth APP：</p>
        <code><?php echo wp_login_url(); ?>?msl_oauth=1</code>
    </div>
    <?php
}