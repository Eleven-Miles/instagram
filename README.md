# TJ-Digital: Instagram

## Requirements

In order for the Instagram authentication process to work, you must setup a Facebook App with support for basic instagram display permissions. Once generated, add your `INSTAGRAM_CLIENT_ID` & `INSTAGRAM_CLIENT_SECRET` values to your project's `.env` file (or ENV vars depending on your setup) in order to succesfully complete authentication.

## Setup

Must be used with ACF and is design to work with an existing custom admin settings/options page to be setup similar, using the [`NanoSoup\Nemesis\ACF\BaseFields`](https://github.com/NanoSoup/Nemesis) package as per the example below.

Note: Ensure you replace the `acf/render_field/name=` with the matching field value for your instagram field if different than `instagram_url`. The field has no bearing on the instagram account connected to the feed, this is handled during the instagram app authentication process.

```php
// Add in your SiteSettings class (or similar)
use TJDigital\Instagram\InstagramSettings;

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
```
