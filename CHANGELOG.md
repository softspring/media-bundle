# CHANGELOG

## [v5.1.0](https://github.com/softspring/media-bundle/releases/tag/v5.1.0)

### Upgrading

There are too many changes in this version, so you must check your code to adapt it to the new version.

As we noticed in README, this bundle is still under development, so it has changed a lot since the last version.

We are not going to provide a full changelog.

#### Assets installing

Upgrade your package.json to use the appropriated assets:

```json
{
    "dependencies": {
        "@softspring/media": "file:vendor/softspring/media-bundle/assets"
    }
}
```

Now you can use directly in you JS:

```js
import '@softspring/media/scripts/media-type';
```

#### Doctrine migrations

After 5.1 versions, CMS contains migrations to update the database schema.

If your version is older than 5.1, probably your database is already created, so ignore the origin migration with:

    bin/console doctrine:migrations:version "Softspring\MediaBundle\Migrations\Version20230301000000" --add --no-interaction

and run a diff to check if there are any changes:

    bin/console doctrine:migrations:diff --namespace=DoctrineMigrations

Take care of the namespace parameter, it must be the same as the one configured in your `doctrine_migrations.yaml` file.

## [v5.0.5](https://github.com/softspring/media-bundle/releases/tag/v5.0.5)

### Upgrading

*Nothing to do on upgrading*

### Commits

- [a8034c6](https://github.com/softspring/media-bundle/commit/a8034c6c0d925b228bbb4179e7b18d64184708bd): Update changelog

### Changes

```
 CHANGELOG.md | 21 +++++++++++++++++++++
 1 file changed, 21 insertions(+)
```

## [v5.0.4](https://github.com/softspring/media-bundle/releases/tag/v5.0.4)

*Nothing has changed since last v5.0.3 version*

## [v5.0.3](https://github.com/softspring/media-bundle/releases/tag/v5.0.3)

*Nothing has changed since last v5.0.2 version*

## [v5.0.2](https://github.com/softspring/media-bundle/releases/tag/v5.0.2)

*Nothing has changed since last v5.0.1 version*

## [v5.0.1](https://github.com/softspring/media-bundle/releases/tag/v5.0.1)

*Nothing has changed since last v5.0.0 version*

## [v5.0.0](https://github.com/softspring/media-bundle/releases/tag/v5.0.0)

*Previous versions are not in changelog*
