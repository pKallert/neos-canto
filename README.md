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

- authentication setup via own backend module
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

tbd.

## Additional configuration options

tbd.

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

This plugin was sponsored by [Paessler](https://www.paessler.com/) and
its initial version was developed by Robert Lemke of
[Flownative](https://www.flownative.com).

See LICENSE for license details.
