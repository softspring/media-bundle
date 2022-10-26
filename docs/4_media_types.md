# Media types

As shown in [3. Concepts](3_concepts.md), media types are the definition of files are wanted to store.

Types are defined in a config file, and must have and **id**, **name** and **description**.

```yaml
# config/packages/sfs_media.yaml
sfs_media:
    types:
        type_id:
            name: 'My type id'
```

## Upload requirements

For the file uploading, *Symfony\Component\Form\Extension\Core\Type\FileType* field type is used.

For the uploaded file validation Symfony standard constraints are used.

### Image validation

The *upload_requirements* allowed options are the *Symfony\Component\Validator\Constraints\Image* accepted ones,
 see [Image constraint options in Symfony documentation page](https://symfony.com/doc/current/reference/constraints/Image.html).

- **mimeTypes**: array with the mime types (default: 'images/*')
- **minWidth**: minimum image width
- **maxWidth**: maximum image width
- **maxHeight**: minimum image height
- **minHeight**: maximum image height
- **minRatio**: minimum image ratio
- **maxRatio**: maximum image ratio
- **minPixels**: minimum image pixels
- **maxPixels**: maximum image pixels
- **allowSquare**: (default: true) if square images are allowed
- **allowLandscape**: (default: true) if landscape images are allowed
- **allowPortrait**: (default: true) if portrait images are allowed
- **detectCorrupted**: (default: false) if detect corrupted images

As well, because of inheritance, *Symfony\Component\Validator\Constraints\File* options are allowed,
 see [File constraint options in Symfony documentation page](https://symfony.com/doc/current/reference/constraints/File.html).

- **maxSize**: maximum file size
- **binaryFormat**: if binary format is allowed

**Configuration example**

```yaml
# config/packages/sfs_media.yaml
sfs_media:
    types:
        type_id:
            name: 'My type id'
            upload_requirements: 
                maxSize: 10M
                minWidth: 500
                maxWidth: 2000
                minHeight: 500
                maxHeight: 2000
                allowLandscape: true
                allowSquare: true
                allowPortrait: false
                mimeTypes: ['image/png', 'image/jpeg'] 
```

### Other file validation

*TODO: implement other file validations*

## Versions

Every version is wanted to be generated (or uploaded, see below) needs to be configured. 

Versions are identified by a key name, and it's recommended to be related with its size or purpose.

### Generating versions

[Imagine library](https://imagine.readthedocs.io/en/stable/) is used for this automatic generation, so take a look to its configuration options.

The main configuration field is the *type* option. It will decide the format for the target file:

- **type**: its mandatory to be jpeg, png, gif or webm

**Scaling images**

- *scale_width*: target image width
- *scale_height*: target image height

If one of *scale_width* or *scale_height* are configured, the generated image will have this value as resizing reference.

If both *scale_width* and *scale_height* are configured, the generated image will be forced to scale to this size, and probably it will be deformed.

**Save file options**

- *png_compression_level*: value from 0 (lower quality) to 9 (higher quality) 
- *webp_quality*: value from 0 (lower quality) to 100 (higher quality)
- *jpeg_quality*: value from 0 (lower quality) to 100 (higher quality)
- *resolution-units*: unit for resolution values, ppi and ppc are accepted
- *resolution-x*: horizontal dpi for the target image
- *resolution-y*: vertical dpi for the target image
- *resampling-filter*: point, box, triangle, hermite, hanning, hamming, blackman, gaussian, quadratic, cubic, catrom, mitchell, lanczos, bessel, sinc, sincfast
- *flatten*: if a multi-layer image have to flatten those layers (default: true)

**Example**

```yaml
sfs_media:
    types:
        type_id:
            name: 'My type id'
            versions:
                xs:
                    type: 'jpeg'
                    scale_width: 300
                    jpeg_quality: 70
                    resolution-x: 72
                    resolution-y: 72
                    resampling-filter: 'lanczos'
```

### Uploading versions

Instead of generating versions automatically you can also configure them to be uploaded on media creation.

To use this behaviour just add an *upload_requirements* block for the version:

```yaml
# config/packages/sfs_media.yaml
sfs_media:
    types:
        type_id:
            name: 'My type id'
            versions:
              xs:
                upload_requirements: 
                    maxSize: 1M
                    minWidth: 300
                    maxWidth: 300
                    minHeight: 100
                    maxHeight: 500
                    mimeTypes: ['image/png', 'image/jpeg'] 
```

### Default versions

Every media has a special default version called *_original*. This feature stores the original media version
 in the configured Storage.

This *_original* version allows to use it for the front pages and future image processing.

## Pictures

As shown in [3. Concepts](3_concepts.md) *MediaBundle* provides a feature to work with [HTML picture tags](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/picture).

The configuration has two blocks: **sources** and **img**.

### Sources

The first *sources* block defines each source to be rendered:

**srcset**

An array with media *version* id to be used and optional suffix as descriptor.

The descriptor, can be width descriptor (500w) or a pixel density descriptor (2x).

**attrs**

HTML tag attributes to be included in the source tag (media, sizes, etc).

### Default image

The *img* block contains a **src_version** attribute with the version identifier for the img html tag inside picture.

### Configuration example

```yaml
# config/packages/sfs_media.yaml
sfs_media:
    types:
        type_id:
            name: 'My type id'
            pictures:
                _default:
                    sources:
                        - { srcset: [{ version: sm, suffix: '1x' }, { version: xs, suffix: '2x' }], attrs: { media: "(min-width: 200w)" } }
                        - { srcset: [{ version: sm }], attrs: { media: "(min-width: 500w)", sizes: "100vw" } }
                        - { srcset: [{ version: xs }], attrs: { media: "(min-width: 200w)", sizes: "50vw" } }
                    img:
                        src_version: xl
```


