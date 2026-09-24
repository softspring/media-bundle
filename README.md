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

## Animated image versions

Generated animated versions use `ffprobe` to validate the source and `ffmpeg` to resize and encode every frame. Both binaries must be installed in the application runtime. Processing is synchronous; applications should keep duration and frame limits conservative until background generation is available.

```yaml
sfs_media:
    ffmpeg:
        binary: ffmpeg
        probe_binary: ffprobe
        timeout: 300

    types:
        animation:
            upload_requirements:
                mimeTypes: [image/avif, image/apng, image/gif]
            versions:
                small_avif:
                    type: avif
                    animated: true
                    scale_width: 400
                    avif_quality: 82
                    animation:
                        loop: 0
                        speed: 6
                        max_duration: 3
                        max_frames: 75
                small_webp:
                    type: webp
                    animated: true
                    scale_width: 400
                    webp_quality: 82
                    animation:
                        loop: 0
                        max_duration: 3
                        max_frames: 75
```

Supported generated targets are AVIF, WebP and APNG. `type: keep` also preserves animated GIF input. Source timing and frame rate are preserved unless `animation.fps` is set.

Animated WebP outputs use independent full frames. This is slightly larger than lossy partial-frame animation, but avoids accumulated composition errors that appear as trails or stripes in browsers.

When at least one version uses `animated: true`, configuration compilation checks that the configured `ffmpeg` and `ffprobe` binaries are executable. It fails early with their configured names and installation instructions if either binary is missing. Types without animated versions do not require FFmpeg.

FFmpeg 6.1, currently shipped by Alpine 3.22, can encode animated WebP but cannot reliably demux it as an input. Use AVIF, APNG or GIF uploads with that runtime. Animated WebP can be enabled as an upload source when the deployed FFmpeg build includes its newer animated WebP demuxer.

Animation options:

- `fps`: resample the output frame rate, from 1 to 120.
- `loop`: output loop count; `0` means infinite.
- `crf`: AVIF constant-quality value, from 0 to 63. When omitted, it is derived from `avif_quality`.
- `speed`: AVIF encoding speed (`libaom-av1` `cpu-used`), from 0 to 8.
- `keyframe_interval`: encoder GOP size.
- `max_duration`: reject longer source animations, in seconds.
- `max_frames`: reject sources containing more frames.

The processor only handles generated versions explicitly marked with `animated: true`. Manual versions keep their uploaded file, and regular generated images continue through the GD processor.

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
