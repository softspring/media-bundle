# Media Bundle

[![Latest Stable](https://img.shields.io/packagist/v/softspring/media-bundle?label=stable&style=flat-square)](https://packagist.org/packages/softspring/media-bundle)
[![Latest Unstable](https://img.shields.io/packagist/v/softspring/media-bundle?label=unstable&style=flat-square&include_prereleases)](https://packagist.org/packages/softspring/media-bundle)
[![License](https://img.shields.io/packagist/l/softspring/media-bundle?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/dependency-v/softspring/media-bundle/php?style=flat-square)](composer.json)
[![Downloads](https://img.shields.io/packagist/dt/softspring/media-bundle?style=flat-square)](https://packagist.org/packages/softspring/media-bundle)
[![CI](https://img.shields.io/github/actions/workflow/status/softspring/media-bundle/ci.yml?branch=6.0&style=flat-square&label=CI)](https://github.com/softspring/media-bundle/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/codecov/c/github/softspring/media-bundle?branch=6.0&style=flat-square&token=QW5SRQ0Q1F)](https://app.codecov.io/gh/softspring/media-bundle/tree/6.0)

Media library, media versioning, media rendering, and admin media management for Symfony applications.

## What It Provides

- configurable media types for images and videos
- generated and manual media versions
- filesystem and Google Cloud Storage drivers
- Twig rendering helpers for images, pictures, videos, and video sets
- admin media library screens
- migration tools when type definitions change

### Delayed Google Cloud Storage deletion

Set `sfs_media.google_cloud_storage.delayed_deletion_days` to a positive integer to keep a removed object available at its existing URL until the configured delay has elapsed:

```yaml
sfs_media:
    google_cloud_storage:
        bucket: media-bucket
        delayed_deletion_days: 90
```

The driver sets the object's `Custom-Time` to the delayed deletion date. Configure the bucket with this Google Cloud Storage lifecycle rule, without a `matchesPrefix` condition:

```json
{
  "rule": [
    {
      "action": {"type": "Delete"},
      "condition": {"daysSinceCustomTime": 0}
    }
  ]
}
```

For example, save it as `lifecycle.json` and apply it with:

```bash
gcloud storage buckets update gs://media-bucket --lifecycle-file=lifecycle.json
```

Without that lifecycle rule, marked objects are not deleted automatically.

This rule only affects objects with `Custom-Time` set. Ensure that no other process using the same bucket sets `Custom-Time`, or its objects will also be eligible for deletion when that time is reached.

## Armonic

This package is part of [Armonic](https://softspring.es/en/armonic).

## Documentation

[Armonic Documentation](https://armonic.softspring.es/latest/bundles/media-bundle)

## Package Files

- [Features](FEATURES.md)
- [Contributing](CONTRIBUTING.md)
- [Security](SECURITY.md)

## Contributing

Use the standard package commands before sending changes:

```bash
composer fix
composer test
composer test-bc
```

[See the contributing guide](CONTRIBUTING.md).

[Report issues](https://github.com/softspring/media-bundle/issues) and [send Pull Requests](https://github.com/softspring/media-bundle/pulls)

## Security

Please report vulnerabilities privately. See [SECURITY.md](SECURITY.md).

## License

This package is free and released under the [AGPL-3.0 license](LICENSE).
