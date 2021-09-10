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

## Installation

The Canto connector is installed as a regular Flow package via Composer.
For your existing project, simply include `flownative/neos-canto` into
the dependencies of your Flow or Neos distribution:

```bash
$ composer require flownative/neos-canto:0
```

## Enabling Canto API access

1. In Canto go to Settings > Configuration Options > API 
2. Click "Create API Key"
3. Fill in a name that helps you understand what the key is for
4. Fill in the "Redirect URL", using `http://<www.your-site.com>/flownative-canto/authorization/finish`,
   using your own domain(!)
5. Note down "App ID", "App Secret" and "Website" of the new key

## Configure the Canto connection

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

## Cleaning up unused assets

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
