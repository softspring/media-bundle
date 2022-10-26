# Getting started

## Configure storage

First, you need to configure a storage option, see [8. Storage options](8_storage_options.md) section.

This is an example:

```dotenv
# .env
MEDIA_BUCKET_NAME=example-gcloud-media-bucket
```

```yaml
# config/packages/sfs_media.yaml
sfs_media:
  google_cloud_storage:
    bucket: '%env(MEDIA_BUCKET_NAME)%'
```

## Configure basic media type

```yaml
# config/packages/sfs_media.yaml
sfs_media:
  content:
    name: 'Team picture'
    upload_requirements: { minWidth: 100, minHeight: 100, mimeTypes: ['image/jpeg'],  }
```

## Configure admin panel

**Enable admin panel**

```yaml
# config/packages/sfs_media.yaml
sfs_media:
  media:
    admin_controller: true
```

**Add admin routes**

```yaml
# config/routes/admin.yaml
_sfs_media_admin_types_:
  resource: "@SfsMediaBundle/config/routing/admin_media.yaml"
  prefix: "/media"
```

**Add permissions**

```yaml
# config/packages/security.yaml
imports:
  - { resource: '@SfsMediaBundle/config/security/admin_role_hierarchy.yaml' }

security:
    ROLE_ADMIN:
      - ROLE_SFS_MEDIA_ADMIN_MEDIAS_RW
```

## Using medias

After uploading your first media file, you can use it:

```twig
{{ media|sfs_media_render_image }}
```