[![MIT license](http://img.shields.io/badge/license-MIT-brightgreen.svg)](http://opensource.org/licenses/MIT)
[![Packagist](https://img.shields.io/packagist/v/flownative/neos-canto.svg)](https://packagist.org/packages/flownative/neos-canto)

# Canto adaptor for Neos

This [Flow](https://flow.neos.io) package allows you to use assets (ie.
pictures and other documents) stored in [Canto](https://www.canto.com/)
in your Neos website as if these assets were native Neos assets.

## About Canto

Canto offers a extensive solutions for digital asset management. The
software makes working with pictures, graphics and video files easier.

## Key Features

- seamless integration into the Neos media browser
- automatic import and clean up of media assets from Canto
- mapping of metadata from Canto to Neos

## Installation

The Canto connector is installed as a regular Flow package via Composer.
For your existing project, simply include `flownative/neos-canto` into
the dependencies of your Flow or Neos distribution:

```bash
$ composer require flownative/neos-canto
```

### Enabling Canto API access

1. In Canto go to Settings > Configuration Options > API > API Keys
2. Click "Create API Key"
3. Fill in a name that helps you understand what the key is for
4. Fill in the "Redirect URL", using `https://<www.your-site.com>/flownative-canto/authorization/finish`,
   using your own domain(!)
5. Note down "App ID", "App Secret" and "Website" of the new key

#### Allow client credentials mode for API key

To be able to use the Canto connection from the command line, client credentials
mode must be enabled.

1. In Canto go to Settings > Configuration Options > API > API Keys
2. Edit the API key you use for the Neos integration
3. Enable "Support Client Credentials Mode" and click "Save"

### Configure the Canto connection

You need to set the "App ID" and "App Secret" from the generated API key as well
as the URLs you are using to access Canto (since they depend on your setup.)

The URLs are a base URI built using the "Website" value appended with `api/v1`
and an URI used for authentication that uses the top-level domain of the "Website"
in `https://oauth.canto.global/oauth/api/oauth2`.

Set those values using the environment variables

- `FLOWNATIVE_CANTO_OAUTH_APP_ID`
- `FLOWNATIVE_CANTO_OAUTH_APP_SECRET`
- `FLOWNATIVE_CANTO_API_BASE_URI`
- `FLOWNATIVE_CANTO_OAUTH_BASE_URI`

or directly in `Settings.yaml` 

```yaml
Neos:
  Media:
    assetSources:
      'flownative-canto':
        assetSourceOptions:
          appId: '1234567890abcdef'
          appSecret: '1234567890abcdef1234567890abcdef'
          apiBaseUri: 'https://acme.canto.global/api/v1'
```

and `Objects.yaml`

```yaml
Flownative\Canto\Service\CantoOAuthClient:
  properties:
    baseUri:
      value: 'https://oauth.canto.global/oauth/api/oauth2'
```

## Using the Canto asset source in Neos

In the Media module you should see two asset sources, one called "Neos" and
one called "Canto". If you switch to the Canto asset source and are not yet
(or no longer) logged in to Canto, you will be redirected to the Canto login
and asked to authorize access for Neos. After a redirect back to Neos you
can now browse/search Canto and use assets from it in Neos as usual.

### Custom Field mapping from Canto to Neos

Canto offers "custom fields" to add arbitrary data to assets. Those can be
mapped to asset collections and tags in Neos, to make them visible for the
users.

The configuration for this looks like that:

```yaml
Flownative:
  Canto:
    mapping:
      # map "Custom Fields" from Canto to Neos
      customFields:
        # Foo Bar Baz                    # the name in Canto, for readbility
        'meta_multichoice_0':            # the custom field id in Canto
          asAssetCollection: false       # map to an asset collection named after the field
          valuesAsTags: false            # map field values to Neos tags; if true
                                         # an asset is assigned a tag corresponding to 
                                         # the value
          include: []                    # only include the listed field values as tags
          exclude: []                    # exclude the listed field values as tags
```

- The key used is the custom field identifier from Canto (not the name!)
- `asAssetCollection` set to `true` exposes the custom field as an asset
  collection named like the custom field.
- If `valuesAsTags` is set to `true`, each distinct value of the custom field
  is exposed as a tag in the asset collection and assets are grouped below the
  tag whose value they are assigned on the Canto side.
- With `include` you can specify which values to consider, if this is used, only
  those will be exposed on the Neos side.
- If only a few values should be excluded, `exclude` can be used. All values are
  exposed in Neos, except those listed here.

**Note:** Right now ths feature only makes sense if both `asAssetCollection` and
`valuesAsTags` are set to `true`.

**Note:** The asset collections and tags must be created manually on the Neos
side (for now.)

### Cleaning up unused assets

Whenever a Canto asset is used in Neos, the media file will be copied
automatically to the internal Neos asset storage. As long as this media
is used somewhere on the website, Neos will flag this asset as being in
use. When an asset is not used anymore, the binary data and the
corresponding metadata can be removed from the internal storage. While
this does not happen automatically, it can be easily automated by a
recurring task, such as a cron-job.

In order to clean up unused assets, simply run the following command as
often as you like:

```bash
./flow media:removeunused --asset-source flownative-canto
```

If you'd rather like to invoke this command through a cron-job, you can
add two additional flags which make this command non-interactive:

```bash
./flow media:removeunused --quiet --assume-yes --asset-source flownative-canto
```

## Credits and license

This plugin was sponsored by [Paessler](https://www.paessler.com/) and its
initial version was developed by Robert Lemke and Karsten Dambekalns of
[Flownative](https://www.flownative.com).

See LICENSE for license details.
