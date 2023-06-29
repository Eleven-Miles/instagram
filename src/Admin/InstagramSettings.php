<?php

namespace ElevenMiles\Instagram\Admin;

use Routes;
use DateTimeImmutable;
use Timber\Timber;
use ElevenMiles\Instagram\Admin\InstagramAuth;

/**
 * Class InstagramSettings
 */
class InstagramSettings
{
    /**
     * InstagramSettings constructor which sets up admin notices
     *  and custom callback routing
     */
    public function __construct()
    {
        add_action('admin_notices', [__CLASS__, 'instagramNotices']);

        $this->setupRouting();
    }

    /**
     * Renders the Instagram authorisation button link
     *  in the ACF settings page (when defined)
     *
     * @return void
     */
    public static function instagramAuthLink(): void
    {
        $url = InstagramAuth::generateAuthorizeUrl();
        $instagram_data = get_option('instagram_data');

        echo "<br><a class='button button-primary button-large' href='$url'>Authorise Instagram Feed</a>";

        if (!empty($instagram_data) && !empty($instagram_data['expiry_timestamp'])) {
            $expiry_date = new DateTimeImmutable("@{$instagram_data['expiry_timestamp']}");

            echo "<p>Current token will expire on: <strong><em>{$expiry_date->format('d/m/Y')}</em></strong>. " .
                'The system will attempt to regenerate a new token before this date and update the Instagram token. ' .
                'If this fails for any reason, use the button above to reauthorise your account.</p>';
        }
    }

    /**
     * Adds admin notices to be displayed upon callback completion
     *
     * @return void
     */
    public static function instagramNotices(): void
    {
        if (!isset($_GET['response_type'])) {
            return;
        }

        $response_type = $_GET['response_type'];
        $error_msg = !empty($_GET['error_msg']) ? $_GET['error_msg'] : false;

        Timber::render(__DIR__ . "/views/notices/instagram-update-{$response_type}.twig", ['error_msg' => $error_msg]);
    }

    /**
     * Callback routing for Facebook/Instagram app authorisation
     *
     * @return void
     */
    private function setupRouting(): void
    {
        Routes::map('/auth/instagram/callback', static function () {
            $code = !empty($_GET['code']) ? sanitize_text_field($_GET['code']) : false;
            $token_code_response = InstagramAuth::requestShortLifeToken($code);
            $response_type = 'error';
            $error_msg = '';

            if ($token_code_response['status'] === 'success') {
                $instagram_user_id = $token_code_response['data']->user_id;
                $token_exchange_response = InstagramAuth::exchangeLongLifeToken(
                    $token_code_response['data']->access_token
                );

                if ($token_exchange_response['status'] === 'success') {
                    $expiry_seconds = time() + $token_exchange_response['data']->expires_in;
                    $access_token = $token_exchange_response['data']->access_token;
                    $option_update = update_option(
                        'instagram_data',
                        [
                            'token' => base64_encode($access_token),
                            'user_id' => $instagram_user_id,
                            'expiry_timestamp' => $expiry_seconds
                        ]
                    );

                    if ($option_update) {
                        $response_type = 'success';
                    }
                } else {
                    $error_msg = $token_exchange_response['error'];
                }
            } else {
                $error_msg = $token_code_response['error'];
            }

            $settings_slug = SITE_SETTINGS_SLUG;
            $redirect_url = trailingslashit(get_home_url()) .
                "wp-admin/admin.php?page=$settings_slug&code=$code&response_type=$response_type&error_msg=$error_msg";

            wp_redirect($redirect_url, 301);
            exit;
        });
    }
}
