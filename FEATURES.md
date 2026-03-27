# Media Bundle Features

Functional definition for `softspring/media-bundle`.

## Purpose

- Manage uploaded media as Doctrine entities instead of raw files.
- Define media behavior through configuration-driven media types.
- Provide reusable upload, versioning, rendering, and admin media flows for Symfony applications.

## Main Features

- Registers media and media version model contracts, default entities, Doctrine mappings, and target entity resolution.
- Supports media types for images and videos, including upload requirements, generated versions, uploaded versions, pictures, and video sets.
- Provides storage drivers for local filesystem and Google Cloud Storage.
- Ships processors for file copying, image resizing and conversion, file storage, and upload metadata extraction.
- Exposes Twig helpers to render images, pictures, videos, video sets, and media URLs.
- Provides forms and admin screens to upload, browse, migrate, and select media entries.
- Includes migration support when media type definitions change.

## Integration And Extension

- Can be extended with custom media entities, media version entities, media type providers, name generators, storage drivers, processors, forms, listeners, and templates.
- Integrates with `softspring/crudl-controller` for admin UI and with `softspring/permissions-bundle` for admin permissions.
- Can work with application-specific media usage through `MediaTypeUploadType`, `MediaChoiceType`, modal selectors, and Twig rendering helpers.

## Expected Capabilities

- Must work with the supported dependency matrix of this line, including Symfony `6.4`, `7.x`, and `8.x`.
- Must keep both regular and lowest dependency validation workflows working (`composer test` and `composer test-bc`).
- Must keep media type configuration, version generation, rendering helpers, storage handling, and admin media permissions stable across minor releases in the same line.
