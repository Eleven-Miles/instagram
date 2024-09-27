<?php

namespace ElevenMiles\Instagram\Admin;

/**
 * Class InstagramAuth
 */
class InstagramAuth
{
    /**
     * Redirect url used in other static methods
     *
     * @var string
     */
    private static $redirectUrl = '';

    /**
     * Builds the initial instagram auth url requiring user signon and authorisation
     *
     * @param string $client_id The Instagram client id.
     *
     * @return string
     */
    public static function generateAuthorizeUrl(string $client_id): string
    {
        $redirect_url = self::getRedirectUrl();
        $base_url = 'https://www.instagram.com/oauth/authorize/';
        $scopes = 'user_media,user_profile';

        return "$base_url?client_id=$client_id&redirect_uri=$redirect_url&response_type=code&scope=$scopes";
    }

    /**
     * Takes the initial auth code from Instagram FB App and generates a short live
     *
     * @param string $client_id     The Instagram client id.
     * @param string $client_secret The Instagram client secret.
     * @param string $code          Value returned form initial auth with Instagram app which can be converted into a
     *                              short life token.
     *
     * @return mixed
     */
    public static function requestShortLifeToken(
        string $client_id,
        string $client_secret,
        string $code
    ) {

        $response = wp_remote_post(
            'https://api.instagram.com/oauth/access_token',
            [
                'method' => 'POST',
                'body' => [
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => self::getRedirectUrl(),
                ]
            ]
        );
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (is_wp_error($response) || isset($data['code']) && $data['code'] === 400) {
            return [
                'status' => 'error',
                'error' => 'There was a problem exchanging tokens with Instagram, please try again later.'
            ];

            wp_die();
        }

        return ['status' => 'success', 'data' => (object) $data];

        wp_die();
    }

    /**
     * Exchanges a short life token for a long life token
     *
     * @param string $client_secret    The Instagram client secret.
     * @param string $short_life_token A short life token returned by the initial Instagram app auth flow.
     *
     * @return mixed
     */
    public static function exchangeLongLifeToken(string $client_secret, string $short_life_token)
    {
        $base_url = 'https://graph.instagram.com/access_token';
        $url = "$base_url?grant_type=ig_exchange_token&client_secret=$client_secret&access_token=$short_life_token";
        $response = wp_remote_get($url);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (is_wp_error($response) || isset($data['error'])) {
            return [
                'status' => 'error',
                'error' => 'There was a problem exchanging tokens with Instagram, please try again later.'
            ];

            wp_die();
        }

        return ['status' => 'success', 'data' => (object) $data];

        wp_die();
    }

    /**
     * Generates a new access token by refreshing an existing access token
     *
     * @param string $access_token An existing Instagram access token.
     *
     * @return mixed
     */
    public static function exchangeRefreshToken(string $access_token)
    {
        $base_url = 'https://graph.instagram.com/refresh_access_token';
        $url = "$base_url?grant_type=ig_refresh_token&access_token=$access_token";
        $response = wp_remote_get($url);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (is_wp_error($response) || isset($data['error'])) {
            return [
                'status' => 'error',
                'error' => 'There was a problem refreshing tokens with Instagram, please try again later.'
            ];

            wp_die();
        }

        return ['status' => 'success', 'data' => (object) $data];

        wp_die();
    }

    /**
     * Returns the redirect url from variable if already set, else sets and returns the correct url
     *
     * @return string
     */
    private static function getRedirectUrl(): string
    {
        if (!empty(self::$redirectUrl)) {
            return self::$redirectUrl;
        }

        self::$redirectUrl = trailingslashit(get_home_url()) . 'auth/instagram/callback';

        return self::$redirectUrl;
    }
}
