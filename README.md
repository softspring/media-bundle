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

### Static image processing driver

Static generated images use Imagick by default. Override the default globally,
per media type or per version (version > type > global):

```yaml
sfs_media:
    imagine:
        driver: imagick # imagick | gd
        allow_upscale: false
        resize_tolerance: 0.02
        preserve_original_encoding: true
    types:
        content:
            imagine_driver: gd
            versions:
                small:
                    type: webp # inherits gd
                    scale_width: 400
                large:
                    type: avif
                    imagine_driver: imagick
                    scale_width: 1200
```

Install `ext-imagick` and the ImageMagick codecs used by your sources and outputs.
On Alpine, AVIF support requires `imagemagick-heic` (libheif), in addition to the
PHP Imagick extension. Configuration checks the selected driver and its codecs;
there is no silent fallback to GD. Set `imagine.driver: gd` to keep using GD.

The resolved `imagine_driver` is stored in each static generated version's options,
so adding or changing the effective driver is detected by the media migration
checker, including existing versions created before this option existed. Run the
normal media migration explicitly to regenerate files; changing configuration
does not itself run a migration. Type/global driver changes do not affect versions
with an explicit override. Animated FFmpeg versions and manual uploads do not
inherit an Imagine driver. `resampling-filter` is passed to the resize operation;
Imagick supports configurable filters, while GD keeps its native resampling.

The three resize/encoding options follow the same global → type → version
inheritance (use their names directly on a type or version). Their effective values
are also persisted and checked for regeneration:

- `allow_upscale: false` caps dimensions at the source size.
- `resize_tolerance: 0.02` skips proportional reductions of 2% or less. For example,
  a 1127px source requested at 1120px stays at 1127px. Set `0` for strict resizing.
  Tolerance does not apply when both width and height are explicitly requested.
- `preserve_original_encoding: true` keeps an independent byte-for-byte copy when
  dimensions and format remain unchanged, unless flattening or an explicit output
  resolution is requested. Quality settings do not force recompression in this
  case; set this option to `false` to force encoding. Format conversions and larger
  reductions still encode normally.

Picture width descriptors use the actual generated width and duplicate widths
within a source are removed. These options apply only to static generated images,
not animated FFmpeg versions or manual uploads. Existing stored files are unchanged
until a media migration/regeneration is explicitly run.

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

## Configured media selections

Media fields restrict both their modal choices and submitted values to
`media_types`. For example, `content: { picture: two_columns }` exposes only
`picture#two_columns`, not raw image versions or other picture sets. Image,
video, picture and video-set choices follow the configured order and must exist
on the selected media. The version selector is hidden for zero or one choice.
Existing invalid selections remain visible as invalid; migrate saved module data
before expecting it to validate under a narrower allow-list. Rebuild/install the
bundle's assets when updating.

## Picture display dimensions

By default, a rendered `<picture>` takes its `<img>` width and height from `img.src_version`. When that fallback has different dimensions from the responsive sources, use `img.dimensions_version` to provide the intrinsic display size without changing the fallback URL:

```yaml
pictures:
    card:
        sources:
            - { srcset: [ { version: card, suffix: '1x' }, { version: card_retina, suffix: '2x' } ] }
        img:
            src_version: fallback
            dimensions_version: card
```

Explicit image attributes passed to the renderer still take precedence over the configured dimensions version.

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

The processor only handles generated versions explicitly marked with `animated: true`. Manual versions keep their uploaded file, and regular generated images use the configured Imagine driver.

## Create from an existing original

The media detail screen offers a **Create from this** type dropdown. It creates a
new media from the source's `_original`, copying its name, description and all alt
translations. Original and generated files belong to the new media; deleting or
editing either media does not modify the other's files or translations.

Destinations must be public image types other than the source type, satisfy the
original's stored dimensions, orientation, ratio, pixels, MIME type and size
requirements, and have no independently uploaded versions. The downloaded file is
validated again against the destination's upload requirements before creation.
Generation uses the existing synchronous processing pipeline. The POST action
requires create/read permissions and a source-specific CSRF token.

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
