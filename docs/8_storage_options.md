# Storage options

## Google Storage

**Requirements**

It's required the Cloud Storage Google's client library to work with this storage driver.

```bash
$ composer require google/cloud-storage 
```

Also, it's required to be authenticated in Google Cloud, see [Authentication](https://github.com/googleapis/google-cloud-php/blob/main/AUTHENTICATION.md) options.

**Bucket permissions**

Bucket must be configured as public, to be accessed from the Internet.

Configured service account must have write permission for the bucket.

**Configuration**

A bucket must be configured to store images.

```yaml
# config/packages/sfs_media.yaml
sfs_media:
  google_cloud_storage:
    bucket: '%env(MEDIA_BUCKET_NAME)%'
```

