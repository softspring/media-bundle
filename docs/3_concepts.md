# Concepts

To begin using the *media-bundle* you must know some concepts explained next.

First of all the main goal of the bundle.

This bundle is aimed to store and manage project dynamic media files, generating automatically as many versions as required
 and making easy the html generation to be responsive with every device. 

## Model

### Media 

The *Media* entity will store every media instance that the system stores. It stores the name, description, type and versions references.

By default, *Softspring/MediaBundle/Entity/Media* class is used (if rewrite is required see [10. Extending bundle]()).

For referencing a media file, the recommended option is to link with the *Softspring/MediaBundle/Model/MediaInterface*, it will be compatible with rewriting.

This is an example witch references a media as user's photo.

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Softspring\MediaBundle\Model\MediaInterface;

class User 
{
    /**
     * @ORM\ManyToOne(targetEntity="Softspring\MediaBundle\Model\MediaInterface")
     */
    protected ?MediaInterface $photo;

    public function getPhoto(): ?MediaInterface
    {
        return $this->photo;
    }
}
```

### Media Version

A *Media* is linked to a configuration media type, witch can contain as many versions as configured.

Every version configured will be stored in a *Media Version* entity.

This *Media Version* stores version name, mime type, sizes, configuration that generated it, generation date, file size
 and the URL to the public file.

By default, *Softspring/MediaBundle/Entity/MediaVersion* class is used (if rewrite is required see [10. Extending bundle]()).

## Media types

Let's see some examples to learn about media types.

For example, we need to store a background image for our web page, so we need to configure a media type for it:

```yaml
sfs_media:
    types:
        background:
            name: 'Background image'
            upload_requirements: { minWidth: 1280, minHeight: 450, allowLandscape: true, allowPortrait: false, mimeTypes: ['image/png', 'image/jpeg'] }
            versions:
                xs: { type: 'jpeg', scale_width: 360, jpeg_quality: 70, resolution-x: 72, resolution-y: 72 }
                sm: { type: 'jpeg', scale_width: 768, jpeg_quality: 70, resolution-x: 72, resolution-y: 72 }
                md: { type: 'jpeg', scale_width: 1024, jpeg_quality: 70, resolution-x: 72, resolution-y: 72 }
                xl: { type: 'jpeg', scale_width: 1280, jpeg_quality: 70, resolution-x: 72, resolution-y: 72 }
            pictures:
                _default:
                    sources:
                        - { srcset: [{ version: sm, suffix: '1x' }, { version: xs, suffix: '2x' }], attrs: { media: "(min-width: 200w)" } }
                        - { srcset: [{ version: sm }], attrs: { media: "(min-width: 5.1w)", sizes: "100vw" } }
                        - { srcset: [{ version: xs }], attrs: { media: "(min-width: 200w)", sizes: "50vw" } }
                    img:
                        src_version: xl
```

With this example we are able to upload a jpeg or png image, with a minimum 1280x450 resolution and landscape format.

When an image is uploaded, automatically the *MediaBundle* will generate 5 versions of it, scaling to the required sizes to be responsive.

Now we can render the image:

```twig
{{ media|sfs_media_render_image('xs`) }}
```

will render:

```html
<img width="360" height="100" src="https://storage.googleapis.com/<my-bucket-name>/<media-id>/<media-version-id>.xs.jpg" alt="My example name"/>
```

If it's configured we can also generate *picture html tags*:

```twig
{{ media|sfs_media_render_picture }}
```

will render:

```html
<picture >
    <source media="(min-width: 200w)" srcset="https://storage.googleapis.com/<my-bucket-name>/<media-id>/<media-version-id>.sm.jpg 1x, https://storage.googleapis.com/<my-bucket-name>/<media-id>/<media-version-id>.xs.jpg 2x" />
    <source media="(min-width: 5.1w)" sizes="100vw" srcset="https://storage.googleapis.com/<my-bucket-name>/<media-id>/<media-version-id>.sm.jpg" />
    <source media="(min-width: 200w)" sizes="50vw" srcset="https://storage.googleapis.com/<my-bucket-name>/<media-id>/<media-version-id>.xs.jpg" />
    <img width="1280" height="575" src="https://storage.googleapis.com/<my-bucket-name>/<media-id>/<media-version-id>.xl.jpg" alt="My example name" />
</picture>
```
