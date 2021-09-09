# TJ-Digital: Instagram

## Requirements

In order for the Instagram authentication process to work, you must setup a Facebook App with support for basic instagram display permissions. Once generated, add your `INSTAGRAM_CLIENT_ID` & `INSTAGRAM_CLIENT_SECRET` values to your project's `.env` file (or ENV vars depending on your setup) in order to succesfully complete authentication.

Within the Facebook App, you will need to add all site urls (local, uat & live) to the `Valid OAuth redirect URIs` field within the `Client OAuth Settings` section of the Instagram app settings. The format should be: `https://{your-site-url}/auth/instagram/callback`. This is the url that the application will pass to Instagram to both validate and redirect back to which is then picked up in the `/auth/instagram/callback` route and ACF options page.

You can also place this same url value in the `Deauthorize` field although this callback is not currently used in this integration.

Once this has been completed, you must then add all Instagram accounts that this integration will use (multiple are supported i.e. via a multi-site setup) to the `User Token Generator` section. This will take you through to the Facebook app roles page and at the bottom there is a section called `Instagram Testers`, here you can add Instagram account by their Instagram username. Once added, the Instagram account holder will need to logging to the Instagram website (not supported yet in the app), go to their settings, click on `Apps and websites` and then click `Tester invitations` and accept the invitation from `Tilda Feed`.

## Setup

Must be used with ACF and is design to work with an existing custom admin settings/options page to be setup similar, using the [`NanoSoup\Nemesis\ACF\BaseFields`](https://github.com/NanoSoup/Nemesis) package as per the example below.

Once configured, you must also set the `SITE_SETTINGS_SLUG` env variable so that the router can return cms users to the site settings page once authorisation has completed via Facebook/Instagram

Note: Ensure you replace the `acf/render_field/name=` with the matching field value for your instagram field if different than `instagram_url`. The field has no bearing on the instagram account connected to the feed, this is handled during the instagram app authentication process.

```php
// Add in your SiteSettings class (or similar)
use TJDigital\Instagram\Admin\InstagramSettings;

// Configure the instagram field reference
$prefix = 'site_settings';
$settings_fields = [
    $this->addTab($prefix, 'Social links'),
    $this->url($prefix, 'Instagram URL'),
];

acf_add_local_field_group([
    'key' => 'group_site_settings',
    'title' => 'Site Settings',
    'fields' => $settings_fields,
    // etc
]);

// Add in your SiteSettings class (or similar) admin page constructor
add_action('acf/render_field/name=instagram_url', [InstagramSettings::class, 'instagramAuthLink']);

// Add in your theme Kernel class
use TJDigital\Instagram\Admin\InstagramSettings;

new InstagramSettings();
```

## Connecting your instagram account

Once setup from a code prespective, visit your site settings page in your WP CMS and click on the `Authorise Instagram Feed` button. This will kick off the app authorisation flow via the Facebook app and Instagram account you are connecting with.

## Rendering the Instagram feed

To use the connected instagram

```php
use TJDigital\Instagram\InstagramFeed;

$instagram = new InstagramFeed();
$instagram_data = $instagram->getData();

// Pass the data to your timber context, ready to use in your twig templates
$context['instagram'] = $instagram_data;
```

Basic twig templating with Instagram data (please customise as appropriate to your markup and requirements).

```twig
{% if instagram.results is empty %}
    <p>{{ instagram.error }}</p>
{% else %}
    {% for result in instagram.results %}
        <a href="{{ result.link }}" target="_blank">
            <img src="{{ result.thumbnail_url }}" />
        </a>
    {% endfor %}
{% endif %}
```

## Caching

This package supports data caching by passing the returned Instagram data to a cache json file, which it will attempt to located from:

```php
get_template_directory() . '/cache/instagram.json'
```

In order to support this, you will need to ensure that there is a cache folder in the root of your theme, and exclude it from both your theme's version control (i.e. via a `.gitignore` file) and any automated deployment processes to avoid losing the cached data when deploying changes to your site.
