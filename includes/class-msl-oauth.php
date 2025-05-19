<?php
class MSL_OAuth {
    private static $instance;
    private $client_id;
    private $client_secret;
    
    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        $options = get_option('msl_oauth_settings');
        $this->client_id = $options['client_id'] ?? '';
        $this->client_secret = $options['client_secret'] ?? '';
    }

public function get_authorization_url($action) {
    // 确保回调地址包含专用参数
    $redirect_uri = add_query_arg(['msl_oauth' => '1'], wp_login_url());
    
    $state = wp_create_nonce('msl_oauth_' . $action);
    set_transient('msl_oauth_state_' . $state, $action, 15 * MINUTE_IN_SECONDS);
    
    return add_query_arg([
        'response_type' => 'code',
        'client_id'     => $this->client_id,
        'redirect_uri'  => rawurlencode($redirect_uri),
        'state'         => $state,
        'scope'         => 'user_info',
    ], 'https://user.mslmc.net/oauth');
}

    public function handle_callback() {
        try {
            if (!isset($_GET['code']) || !isset($_GET['state'])) {
                wp_redirect(add_query_arg('msl_oauth_error', 'no_code', wp_login_url()));
                exit;
            }

            $state = $_GET['state'];
            $action = get_transient('msl_oauth_state_' . $state);
            delete_transient('msl_oauth_state_' . $state);

            if (!$action || !wp_verify_nonce($state, 'msl_oauth_' . $action)) {
                wp_redirect(add_query_arg('msl_oauth_error', 'expired', wp_login_url()));
                exit;
            }

            $token = $this->exchange_code($_GET['code']);
            $user_info = $this->get_user_info($token);

            switch ($action) {
                case 'login':
                    $this->handle_login($user_info);
                    break;
                case 'bind':
                    $this->handle_binding($user_info);
                    break;
                default:
                    throw new Exception('未知操作类型');
            }
        } catch (Exception $e) {
            wp_die('OAuth错误：' . $e->getMessage());
        }
    }

    private function exchange_code($code) {
        $response = wp_remote_post('https://user.mslmc.net/api/oauth/exchangeAccessToken', [
            'body' => [
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => wp_login_url()
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('令牌交换失败');
        }

        $body = json_decode($response['body'], true);
    if ($body['code'] !== 200) {
        $error_msg = $body['msg'] ?? '未知错误';
        throw new Exception("API返回错误: [{$body['code']}] {$error_msg}");
    }
        return $body['access_token'] ?? null;
    }

    private function get_user_info($token) {
        $response = wp_remote_get('https://user.mslmc.net/api/oauth/user', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('无法获取用户信息');
        }

        $user_data = json_decode($response['body'], true);
        if (empty($user_data['uid'])) {
            throw new Exception("无效的用户信息");
        }

        return [
            'uid' => $user_data['uid'],
            'email' => $user_data['email'] ?? ''
        ];
    }

    private function handle_login($user_info) {
        $user_id = $this->get_user_by_uid($user_info['uid']);
        
        if (!$user_id) {
            wp_redirect(add_query_arg('msl_oauth_error', 'unbound', wp_login_url()));
            exit;
        }

        wp_set_auth_cookie($user_id, true);
        wp_redirect(admin_url());
        exit;
    }

    private function handle_binding($user_info) {
        if (!is_user_logged_in()) {
            throw new Exception('需要先登录才能绑定');
        }

        $existing_user = $this->get_user_by_uid($user_info['uid']);
        if ($existing_user) {
            throw new Exception('该账户已被其他用户绑定');
        }

        update_user_meta(get_current_user_id(), 'msl_oauth_uid', $user_info['uid']);
        wp_redirect(admin_url("profile.php?msl_bind_uid=" . $user_info['uid']));
        exit;
    }



    private function get_user_by_uid($uid) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'msl_oauth_uid' AND meta_value = %s",
            $uid
        ));
    }
}
