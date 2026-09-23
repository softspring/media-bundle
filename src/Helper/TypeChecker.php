<?php

namespace Softspring\MediaBundle\Helper;

use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;

class TypeChecker
{
    public static function checkMedia(MediaInterface $media, array $typeConfig): array
    {
        $checkResult = [
            'new' => [],
            'ok' => [],
            'changed' => [],
            'delete' => [],
            'manual' => [],
        ];

        /** @var MediaVersionInterface $version */
        foreach ($media->getVersions() as $version) {
            if ('_original' === $version->getVersion()) {
                $checkResult['ok'][] = '_original';
                continue;
            }

            if (!isset($typeConfig['versions'][$version->getVersion()])) {
                $checkResult['delete'][] = $version->getVersion();
                continue;
            }

            $changedOptions = [];
            foreach ($version->getOptions() as $option => $value) {
                $configuredVersion = $typeConfig['versions'][$version->getVersion()];
                $configuredOption = $configuredVersion[$option] ?? null;
                if (!array_key_exists($option, $configuredVersion) || !self::optionsAreEqual($configuredOption, $value)) {
                    $changedOptions[$option] = [
                        'config' => $configuredOption,
                        'db' => $value,
                        'string' => sprintf('%s: %s => %s', $option, self::optionToString($value), self::optionToString($configuredOption)),
                    ];
                }
            }
            // TODO search for old configuration values
            if ([] !== $changedOptions) {
                $checkResult['changed'][$version->getVersion()] = $changedOptions;
                continue;
            }

            $checkResult['ok'][] = $version->getVersion();
        }

        $dbVersions = $media->getVersions()->map(fn (MediaVersionInterface $version): ?string => $version->getVersion())->toArray();
        $configuredVersions = array_keys($typeConfig['versions']);
        $newVersions = array_diff($configuredVersions, $dbVersions);
        foreach ($newVersions as $version) {
            if (!empty($typeConfig['versions'][$version]['upload_requirements'])) {
                $checkResult['manual'][] = $version;
            } else {
                $checkResult['new'][] = $version;
            }
        }

        return $checkResult;
    }

    private static function optionsAreEqual(mixed $configuredOption, mixed $databaseOption): bool
    {
        return self::normalizeOption($configuredOption) === self::normalizeOption($databaseOption);
    }

    private static function normalizeOption(mixed $option): mixed
    {
        if (!is_array($option)) {
            return $option;
        }

        if (!array_is_list($option)) {
            ksort($option);
        }

        return array_map(self::normalizeOption(...), $option);
    }

    private static function optionToString(mixed $option): string
    {
        if (is_string($option)) {
            return $option;
        }

        if (null === $option) {
            return 'null';
        }

        if (is_bool($option)) {
            return $option ? 'true' : 'false';
        }

        if (is_int($option) || is_float($option)) {
            return (string) $option;
        }

        $json = json_encode(self::normalizeOption($option), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return false === $json ? get_debug_type($option) : $json;
    }
}
