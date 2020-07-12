<?php

namespace Stillat\Meerkat\Permissions;

use Stillat\Meerkat\Concerns\EmitsEvents;
use Stillat\Meerkat\Concerns\UsesConfig;
use Stillat\Meerkat\Core\Contracts\Identity\AuthorContract;
use Stillat\Meerkat\Core\Contracts\Permissions\PermissionsMutationPipelineContract;
use Stillat\Meerkat\Core\Permissions\AccessManager;
use Stillat\Meerkat\Core\Permissions\PermissionsSet;
use Stillat\Meerkat\Identity\StatamicAuthorFactory;

/**
 * Class StatamicAccessManager
 *
 * Handles the resolution of permissions for author contexts.
 *
 * @package Stillat\Meerkat\Core\Permissions
 * @since 1.0.0
 */
class StatamicAccessManager extends AccessManager
{
    use UsesConfig, EmitsEvents;


    protected $canViewComments = true;
    protected $canApproveComments = true;
    protected $canUnApproveComments = true;
    protected $canReplyToComments = true;
    protected $canEditComments = true;
    protected $canReportAsSpam = true;
    protected $canReportAsHam = true;
    protected $canRemoveComments = true;

    protected $userRoles = [];
    protected $userRoleInstances = null;
    protected $configuredPermissions = [];
    protected $totalRoleCount = 0;

    private $permissionsConfigured = false;
    private $identity = null;

    /**
     * The mutation pipeline implementation.
     *
     * @var \Stillat\Meerkat\Core\Contracts\Permissions\PermissionsMutationPipelineContract
     */
    private $mutationPipeline = null;

    /**
     * Resets the permission state in-between resolutions.
     */
    private function reset()
    {
        $this->canViewComments = true;
        $this->canApproveComments = true;
        $this->canUnApproveComments = true;
        $this->canReplyToComments = true;
        $this->canEditComments = true;
        $this->canReportAsSpam = true;
        $this->canReportAsHam = true;
        $this->canRemoveComments = true;
        $this->userRoles = [];
        $this->userRoleInstances = [];
        $this->identity = null;
    }

    /**
     * Resolves the permissions set for the provided identity.
     *
     * @param AuthorContract $identity
     * @return PermissionsSet
     */
    public function getPermissions(AuthorContract $identity)
    {
        $this->reset();

        $this->identity = $identity;

        if ($identity->getIsTransient()) {
            return $this->getRestrictivePermissions();
        }

        $isSuperUser = $identity->getDataAttribute(StatamicAuthorFactory::STATAMIC_USER_IS_SUPER, false);

        if ($isSuperUser) {
            return $this->getSuperUserPermissions();
        }

        // Handle non-super user permissions.
        $userRoles = $identity->getDataAttribute(StatamicAuthorFactory::STATAMIC_USER_ROLES, collect());

        if ($userRoles != null && $userRoles->count() > 0) {
            $this->userRoles = $userRoles->keys();
            $this->userRoleInstances = $userRoles;
        }

        $this->setPermissions($this->getConfig('permissions', []));

        $this->resolveUserPermissions();

        return $this->toPermissionSet();
    }

    /**
     * Sets the role-based permissions rules.
     *
     * @param array $permissions The user-configured permission rules.
     */
    private function setPermissions($permissions)
    {
        if ($this->permissionsConfigured === true) {
            return;
        }

        $this->configuredPermissions = $permissions;

        $allPermissionKeys = [AccessManager::PERMISSION_ALL, AccessManager::PERMISSION_CAN_VIEW, AccessManager::PERMISSION_CAN_APPROVE,
            AccessManager::PERMISSION_CAN_UNAPPROVE, AccessManager::PERMISSION_CAN_REPLY, AccessManager::PERMISSION_CAN_EDIT,
            AccessManager::PERMISSION_CAN_REPORT_HAM, AccessManager::PERMISSION_CAN_REPORT_SPAM, AccessManager::PERMISSION_CAN_REMOVE];

        foreach ($allPermissionKeys as $permissionCategory) {
            if (array_key_exists($permissionCategory, $this->configuredPermissions) == false) {
                $this->configuredPermissions[$permissionCategory] = [];
            } else if ($this->configuredPermissions[$permissionCategory] === null) {
                $this->configuredPermissions[$permissionCategory] = [];
            }
        }

        // Get rid of the PERMISSION_ALL entry.
        array_shift($allPermissionKeys);

        if (!is_array($this->configuredPermissions[AccessManager::PERMISSION_ALL])) {
            $this->configuredPermissions[AccessManager::PERMISSION_ALL] = [];
        }

        if (!is_array($this->configuredPermissions[AccessManager::PERMISSION_CAN_VIEW])) {
            $this->configuredPermissions[AccessManager::PERMISSION_CAN_VIEW] = [];
        }

        if (!is_array($this->configuredPermissions[AccessManager::PERMISSION_CAN_APPROVE])) {
            $this->configuredPermissions[AccessManager::PERMISSION_CAN_APPROVE] = [];
        }

        if (!is_array($this->configuredPermissions[AccessManager::PERMISSION_CAN_UNAPPROVE])) {
            $this->configuredPermissions[AccessManager::PERMISSION_CAN_UNAPPROVE] = [];
        }

        if (!is_array($this->configuredPermissions[AccessManager::PERMISSION_CAN_REPLY])) {
            $this->configuredPermissions[AccessManager::PERMISSION_CAN_REPLY] = [];
        }

        if (!is_array($this->configuredPermissions[AccessManager::PERMISSION_CAN_EDIT])) {
            $this->configuredPermissions[AccessManager::PERMISSION_CAN_EDIT] = [];
        }

        if (!is_array($this->configuredPermissions[AccessManager::PERMISSION_CAN_REPORT_SPAM])) {
            $this->configuredPermissions[AccessManager::PERMISSION_CAN_REPORT_SPAM] = [];
        }

        if (!is_array($this->configuredPermissions[AccessManager::PERMISSION_CAN_REPORT_HAM])) {
            $this->configuredPermissions[AccessManager::PERMISSION_CAN_REPORT_HAM] = [];
        }

        if (!is_array($this->configuredPermissions[AccessManager::PERMISSION_CAN_REMOVE])) {
            $this->configuredPermissions[AccessManager::PERMISSION_CAN_REMOVE] = [];
        }

        if (count($this->configuredPermissions[AccessManager::PERMISSION_ALL]) > 0) {
            foreach ($this->configuredPermissions[AccessManager::PERMISSION_ALL] as $userRole) {
                foreach ($allPermissionKeys as $permissionCategory) {
                    if (in_array($userRole, $this->configuredPermissions[$permissionCategory]) == false) {
                        array_push($this->configuredPermissions[$permissionCategory], $userRole);
                    }
                }
            }
        }

        // Calculate a role count.
        foreach ($allPermissionKeys as $permissionCategory) {
            $this->totalRoleCount += count($this->configuredPermissions[$permissionCategory]);
        }

        $this->permissionsConfigured = true;
    }

    /**
     * Resolves the permissions for a non-super user.
     */
    private function resolveUserPermissions()
    {
        if ($this->configuredPermissions == null || $this->totalRoleCount == 0) {
            $this->resolveFromEvents();

            return;
        }

        if ($this->totalRoleCount > 0 && count($this->userRoles) == 0) {
            $this->canViewComments = false;
            $this->canApproveComments = false;
            $this->canUnApproveComments = false;
            $this->canReplyToComments = false;
            $this->canEditComments = false;
            $this->canReportAsSpam = false;
            $this->canReportAsHam = false;
            $this->canRemoveComments = false;

            $this->resolveFromEvents();
            return;
        }

        // At this point, revoke everything and add them back.
        $this->canViewComments = false;
        $this->canApproveComments = false;
        $this->canUnApproveComments = false;
        $this->canReplyToComments = false;
        $this->canEditComments = false;
        $this->canReportAsSpam = false;
        $this->canReportAsHam = false;
        $this->canRemoveComments = false;

        foreach ($this->userRoles as $userRole) {
            if ($this->canViewComments == false && in_array($userRole, $this->configuredPermissions[AccessManager::PERMISSION_CAN_VIEW])) {
                $this->canViewComments = true;
            }

            if ($this->canApproveComments == false && in_array($userRole, $this->configuredPermissions[AccessManager::PERMISSION_CAN_APPROVE])) {
                $this->canApproveComments = true;
            }

            if ($this->canUnApproveComments == false && in_array($userRole, $this->configuredPermissions[AccessManager::PERMISSION_CAN_UNAPPROVE])) {
                $this->canUnApproveComments = true;
            }

            if ($this->canReplyToComments == false && in_array($userRole, $this->configuredPermissions[AccessManager::PERMISSION_CAN_REPLY])) {
                $this->canReplyToComments = true;
            }

            if ($this->canEditComments == false && in_array($userRole, $this->configuredPermissions[AccessManager::PERMISSION_CAN_EDIT])) {
                $this->canEditComments = true;
            }

            if ($this->canReportAsSpam == false && in_array($userRole, $this->configuredPermissions[AccessManager::PERMISSION_CAN_REPORT_SPAM])) {
                $this->canReportAsSpam = true;
            }

            if ($this->canReportAsHam == false && in_array($userRole, $this->configuredPermissions[AccessManager::PERMISSION_CAN_REPORT_HAM])) {
                $this->canReportAsHam = true;
            }

            if ($this->canRemoveComments == false && in_array($userRole, $this->configuredPermissions[AccessManager::PERMISSION_CAN_REMOVE])) {
                $this->canRemoveComments = true;
            }
        }

        $this->resolveFromEvents();
    }

    private function resolveFromEvents()
    {
        if ($this->mutationPipeline === null) {
            $this->mutationPipeline = new PermissionMutationPipeline();
        }

        $permissionSet = $this->toPermissionSet();

        $this->mutationPipeline->resolving($this->identity, $permissionSet, function ($resolved) {
            if ($resolved !== null && $resolved instanceof PermissionsSet) {
                $this->canViewComments = $resolved->canViewComments;
                $this->canApproveComments = $resolved->canApproveComments;
                $this->canUnApproveComments = $resolved->canUnApproveComments;
                $this->canReplyToComments = $resolved->canReplyToComments;
                $this->canEditComments = $resolved->canEditComments;
                $this->canReportAsSpam = $resolved->canReportAsSpam;
                $this->canReportAsHam = $resolved->canReportAsHam;
                $this->canRemoveComments = $resolved->canRemoveComments;
            }
        });
    }

    /**
     * Converts the access manager permissions to a PermissionsSet object.
     *
     * @return PermissionsSet
     */
    public function toPermissionSet()
    {
        $permissionSet = new PermissionsSet();
        $permissionSet->canApproveComments = $this->canApproveComments();
        $permissionSet->canViewComments = $this->canViewComments();
        $permissionSet->canUnApproveComments = $this->canUnApproveComments();
        $permissionSet->canReplyToComments = $this->canReplyToComments();
        $permissionSet->canEditComments = $this->canEditComments();
        $permissionSet->canReportAsSpam = $this->canReportAsSpam();
        $permissionSet->canReportAsHam = $this->canReportAsHam();
        $permissionSet->canRemoveComments = $this->canRemoveComments();

        return $permissionSet;
    }

    public function canViewComments()
    {
        return $this->canViewComments;
    }

    public function canApproveComments()
    {
        return $this->canApproveComments;
    }

    public function canUnApproveComments()
    {
        return $this->canUnApproveComments;
    }

    public function canReplyToComments()
    {
        return $this->canReplyToComments;
    }

    public function canEditComments()
    {
        return $this->canEditComments;
    }

    public function canReportAsSpam()
    {
        return $this->canReportAsSpam;
    }

    public function canReportAsHam()
    {
        return $this->canReportAsHam;
    }

    public function canRemoveComments()
    {
        return $this->canRemoveComments;
    }

    /**
     * Creates the least-restrictive permissions for a Statamic super user.
     *
     * @return PermissionsSet
     */
    private function getSuperUserPermissions()
    {
        $permissionSet = new PermissionsSet();

        $permissionSet->canViewComments = true;
        $permissionSet->canApproveComments = true;
        $permissionSet->canUnApproveComments = true;
        $permissionSet->canReplyToComments = true;
        $permissionSet->canEditComments = true;
        $permissionSet->canReportAsHam = true;
        $permissionSet->canReportAsSpam = true;
        $permissionSet->canRemoveComments = true;

        return $permissionSet;
    }

}