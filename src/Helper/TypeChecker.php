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
                if (empty($typeConfig['versions'][$version->getVersion()][$option]) || $typeConfig['versions'][$version->getVersion()][$option] !== $value) {
                    $changedOptions[$option] = [
                        'config' => $typeConfig['versions'][$version->getVersion()][$option],
                        'db' => $value,
                        'string' => "$option: $value => {$typeConfig['versions'][$version->getVersion()][$option]}",
                    ];
                }
            }
            if (!empty($changedOptions)) {
                $checkResult['changed'][$version->getVersion()] = $changedOptions;
                continue;
            }

            $checkResult['ok'][] = $version->getVersion();
        }

        $dbVersions = $media->getVersions()->map(fn (MediaVersionInterface $version) => $version->getVersion())->toArray();
        $configuredVersions = array_keys($typeConfig['versions']);
        $newVersions = array_diff($configuredVersions, $dbVersions);
        foreach ($newVersions as $version) {
            $checkResult['new'][] = $version;
        }

        return $checkResult;
    }
}
