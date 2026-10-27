# Media Bundle

[![Latest Stable](https://img.shields.io/packagist/v/softspring/media-bundle?label=stable&style=flat-square)](https://packagist.org/packages/softspring/media-bundle)
[![Latest Unstable](https://img.shields.io/packagist/v/softspring/media-bundle?label=unstable&style=flat-square&include_prereleases)](https://packagist.org/packages/softspring/media-bundle)
[![License](https://img.shields.io/packagist/l/softspring/media-bundle?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/dependency-v/softspring/media-bundle/php?style=flat-square)](composer.json)
[![Downloads](https://img.shields.io/packagist/dt/softspring/media-bundle?style=flat-square)](https://packagist.org/packages/softspring/media-bundle)
[![CI](https://img.shields.io/github/actions/workflow/status/softspring/media-bundle/ci.yml?branch=6.0&style=flat-square&label=CI)](https://github.com/softspring/media-bundle/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/codecov/c/github/softspring/media-bundle?branch=6.0&style=flat-square&token=QW5SRQ0Q1F)](https://app.codecov.io/gh/softspring/media-bundle/tree/6.0)

Media library, media versioning, media rendering, and admin media management for Symfony applications.

## Generation defaults

Generated image versions inherit processing options from `sfs_media.defaults`
and then from their media type's `defaults` block. Explicit version options
have the highest priority. Nested animation settings are merged by key.
Only options relevant to the output format are passed to its processor.
Manual uploads and video types do not inherit image processing defaults.

```yaml
sfs_media:
    defaults:
        avif_quality: 85
        animation: { max_frames: 500, speed: 6 }
    types:
        content:
            defaults:
                avif_quality: 88
                animation: { speed: 5 }
            versions:
                normal: { type: avif } # Quality 88; max_frames 500; speed 5.
                detailed: { type: avif, avif_quality: 90 }
```


### Animated images in pictures

AVIF and WebP versions preserve animation automatically when the original is
animated. The same picture configuration works for static images and animations;
sources are rendered in their configured order without animation filtering:

```yaml
versions:
    avif: { type: avif }
    webp: { type: webp }
pictures:
    _default:
        sources:
            - srcset: [{ version: avif }]
              attrs: { type: image/avif }
            - srcset: [{ version: webp }]
              attrs: { type: image/webp }
        img:
            src_version: avif
```

The default `animated: keep` retains animation in supported image formats;
`animated: no` explicitly generates a still image. JPEG is always static.
Animated images do not need a poster. Upload and regeneration store detection
in `media.metadata.animated`; picture rendering never downloads originals or
runs FFprobe. Animated WebP inputs use Imagick and a lossless RGBA APNG bridge
when passed to FFmpeg, preserving frame timing and transparency.

`webx` is not a supported output format. WebM belongs in video sets, while
picture sources and `<img>` use image formats.

Configured storage public base URLs apply consistently to image URLs, picture
sources, image fallbacks, video sources, and posters. Stored `gs://` references
remain unchanged when using a CDN or local proxy hostname.

## Parallel media migrations

The migration command defaults to a single process. Start with two workers and
increase only after measuring CPU and memory usage, especially with animated
media and AVIF (the encoders may also use multiple threads).

```bash
php bin/console sfs:media:types-migration --workers=2 --batch-size=100
php bin/console sfs:media:types-migration --workers=2 --type=background --dry-run
php bin/console sfs:media:types-migration --workers=4 --type=background --force --dry-run
php bin/console sfs:media:types-migration --workers=4 --type=background --force --no-debug
```

Workers partition media IDs deterministically. A media and all its versions are
processed by one worker, in version order. IDs are paginated and Doctrine's
identity map is cleared after each media. Each worker reports timings and counts;
the parent returns a nonzero exit code if any worker fails. Dry runs report
version changes without modifying media or storage. Existing matching versions
are not regenerated; rerunning checks the remaining differences.

Use `--force` to rebuild generated versions even when their configuration is
unchanged, for example after updating image codecs. It preserves `_original` and
configured manual/uploaded versions. Normal migration cleanup of obsolete
versions still applies. Combine it with `--type` to limit its scope and with
`--dry-run` to inspect the plan without writing anything. Without `--dry-run`,
forced regeneration replaces generated versions and uploads their new files;
it can be expensive. Force is opt-in and is forwarded to every worker.

Distributed runners can use `--worker-index=0 --worker-count=4` (indexes 0–3),
instead of `--workers`. Every task must use the same configuration, filters and
worker count, and a unique index. Do not run overlapping migration jobs against
the same media concurrently: partitioning is not a cross-job lock. Do not change
media type configuration during a run. No Messenger worker or PHP threading
extension is required; local workers use Symfony Process and the project's
`bin/console`. The worker count must fit the resources allocated to the container.

## Regenerate versions from the admin

The media detail page includes **Regenerate versions** when the user has the
media migration permission and the media has an original file and generated
versions. This opens a confirmation page listing the generated version keys.
Only a confirmed, CSRF-protected POST starts the synchronous regeneration.
Originals and configured manual uploads are preserved; generated versions are
rebuilt even when their options already match. Normal cleanup of obsolete
versions still applies. On completion the user returns to the media detail page
with a success/error notification. Large animations may take time: wait for the
result before retrying. The standard and Sylius admin templates are supported.

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

Image types accept still and animated uploads without separate animation types.
The detector inspects PNG/APNG, GIF, WebP and AVIF files independently of their
extension. Animated inputs use FFmpeg; still images use the configured Imagine
driver. Image animations render in pictures without a manual poster. Video
versions and their video sets keep their existing processing path.

```yaml
sfs_media:
    defaults:
        avif_quality: 85
        animation: { max_frames: 500 }
    ffmpeg:
        binary: ffmpeg
        probe_binary: ffprobe
        timeout: 300
    types:
        content:
            defaults:
                animation: { speed: 6 }
            upload_requirements:
                animation: accepted
                mimeTypes: [image/png, image/apng, image/avif, image/gif]
            versions:
                small_avif: { type: avif, scale_width: 400 }
                small_webp: { type: webp, scale_width: 400, webp_quality: 82 }
                still: { type: avif, scale_width: 400, animated: no }
```

`upload_requirements.animation` accepts `accepted` (the default), `no` or
`required`. Compatible-type choices use known animation metadata and creation
validates the downloaded original again. Generated versions use `animated: keep`
by default for animation-capable formats. Set `animated: no` to extract a still
frame; JPEG always produces a still image. Legacy boolean values on versions
are normalized to `keep`/`no`; migrate configurations to the explicit values.

Supported animated targets are AVIF, WebP, PNG/APNG and GIF; `type: keep` retains
the input format. Timing and frame rate are preserved unless `animation.fps` is
set. PNG input explicitly selects the APNG demuxer, including files with a
separate default image outside the animation. Animated WebP output uses
independent full frames to avoid composition trails in browsers.

FFmpeg and FFprobe must be installed when processing animated uploads. A profile
requiring animation checks both binaries during configuration compilation.
Accepted animation does not make static-only processing depend on FFmpeg.
The runtime must also support the uploaded format: FFmpeg 6.1 cannot reliably
demux animated WebP input; use APNG, AVIF or GIF with that runtime.

Animation processing options (in global/type defaults or per version):

- `fps`: output frame rate, 1–120; omitted preserves source timing.
- `loop`: loop count; `0` means infinite.
- `crf`: AVIF quality, 0–63; derived from `avif_quality` when omitted.
- `speed`: AVIF encoding speed, 0–8.
- `keyframe_interval`: encoder GOP size.
- `max_duration`: maximum source duration in seconds.
- `max_frames`: maximum source frame count.

Limits reject excessive animations instead of silently truncating them.
Processing is synchronous; regenerate large collections outside HTTP requests.
Manual uploads do not inherit generation defaults. The former `Tools\Apng`
utility is replaced by the unified `Tools\AnimationDetector` service.

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
