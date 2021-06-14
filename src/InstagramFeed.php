<?php

namespace TJDigital\Instagram;

use TJDigital\Instagram\InstagramAuth;
use DateTimeImmutable;

/**
 * Class InstagramFeed
 */
class InstagramFeed
{
    public $cacheFile = false;

    public function __construct()
    {
        $this->cacheFile = get_template_directory() . '/cache/instagram.json';
    }

    public function getData()
    {
        if (file_exists($this->cacheFile) && filemtime($this->cacheFile) > (time() - 900)) {
            $json = file_get_contents($this->cacheFile);

            return json_decode($json);
        }

        $instagram_data = get_option('instagram_data');

        if (empty($instagram_data)) {
            return (object) ['results' => [], 'error' => 'No Instagram data configure, please authorise via admin.'];
        }

        $access_token = base64_decode($instagram_data['token']);
        $expiry_timestamp = $instagram_data['expiry_timestamp'];

        if ($this->checkTokenExpiry($expiry_timestamp)) {
            // If token is due to expire in the next 10 days preemptively refresh the access token;
            $token_data = $this->regenerateAccessToken($access_token, $instagram_data['user_id']);

            if ($token_data['status'] === 'error') {
                return (object) [
                    'results' => [],
                    'error' => 'Error regenerating Instagram token, please re-authorise via admin.'
                ];
            }

            $access_token = $token_data['token'];
        }

        $base_url = 'https://graph.instagram.com/me/media';
        $url = "$base_url?fields=id,media_type,media_url,thumbnail_url,permalink&access_token=$access_token";
        $instagram_feed_request = wp_remote_get($url);
        $instagram_feed_response = json_decode(wp_remote_retrieve_body($instagram_feed_request), true);

        // Only update cache if it worked
        if (is_wp_error($instagram_feed_response) || isset($instagram_feed_response['error'])) {
            if (file_exists($this->cacheFile)) {
                return file_get_contents($this->cacheFile);
            }

            return (object) [
                'results' => [],
                'error' => 'Error fetching Instagram data, please re-authorise via admin and check the api.'
            ];
        }

        $feed = [];

        foreach ($instagram_feed_response['data'] as $item) {
            $feed[] = [
                'link' => $item['permalink'],
                'thumbnail_url' => $item['media_type'] === 'VIDEO' ?
                    $item['thumbnail_url'] :
                    $item['media_url']
            ];
        }

        file_put_contents($this->cacheFile, json_encode(['results' => $feed]));

        return json_decode(json_encode(['results' => $feed]));
    }

    private function checkTokenExpiry(string $timestamp)
    {
        $date_now = new DateTimeImmutable();
        $expiry_date = new DateTimeImmutable("@$timestamp");
        $date_interval_days = $date_now->diff($expiry_date)->d;

        return $date_interval_days <= 10;
    }

    private function regenerateAccessToken($access_token, $user_id)
    {
        $token_refresh_response = InstagramAuth::exchangeRefreshToken($access_token);

        if ($token_refresh_response['status'] === 'error') {
            return ['status' => 'error'];
        }

        $access_token = $token_refresh_response['data']->access_token;
        $expiry_seconds = time() + $token_refresh_response['data']->expires_in;
        $option_update = update_option(
            'instagram_data',
            [
                'token' => base64_encode($access_token),
                'user_id' => $user_id,
                'expiry_timestamp' => $expiry_seconds
            ]
        );

        if (empty($option_update)) {
            return ['status' => 'error'];
        }

        return ['status' => 'successful', 'token' => $access_token];
    }
}
