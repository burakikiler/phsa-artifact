# PHSA Media
Contains a custom logic for the media module.

## Update PHSA oembed URL
We can override the PHSA oembed URL via the settings file. Add next line to the
settings.php file:
`$config['oembed_providers.provider.phsa']['endpoints'][0]['url'] = 'https://<website_domain>/phsa/oembed';`

If changes didn't apply in time, go to the oEmbed Providers configuration page - /admin/config/media/oembed-providers
and press on the '**Clear Provider Cache**' button.
