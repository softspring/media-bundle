<?php

namespace Softspring\MediaBundle\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @deprecated since 5.1, will be removed in 6.0
 */
class DeprecatedPermissionVoter implements VoterInterface
{
    public const DEPRECATIONS = [
        'ROLE_SFS_MEDIA_ADMIN_MEDIAS_LIST' => 'PERMISSION_SFS_MEDIA_ADMIN_MEDIAS_LIST',
        'ROLE_SFS_MEDIA_ADMIN_MEDIAS_DETAILS' => 'PERMISSION_SFS_MEDIA_ADMIN_MEDIAS_DETAILS',
        'ROLE_SFS_MEDIA_ADMIN_MEDIAS_CREATE' => 'PERMISSION_SFS_MEDIA_ADMIN_MEDIAS_CREATE',
        'ROLE_SFS_MEDIA_ADMIN_MEDIAS_DELETE' => 'PERMISSION_SFS_MEDIA_ADMIN_MEDIAS_DELETE',
        'ROLE_SFS_MEDIA_ADMIN_MEDIAS_UPDATE' => 'PERMISSION_SFS_MEDIA_ADMIN_MEDIAS_UPDATE',
    ];

    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        if (isset(self::DEPRECATIONS[$attributes[0] ?? ''])) {
            trigger_deprecation('softspring/media-bundle', '5.1', sprintf('The role "%s" is deprecated, use "%s" instead. Will be removed in 6.0', $attributes[0], self::DEPRECATIONS[$attributes[0]]));
        }

        return self::ACCESS_ABSTAIN;
    }
}
