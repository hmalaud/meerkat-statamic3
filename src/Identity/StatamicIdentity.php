<?php

namespace Stillat\Meerkat\Identity;

use Stillat\Meerkat\Core\Contracts\Identity\AuthorContract;
use Stillat\Meerkat\Core\DataObject;
use Stillat\Meerkat\Core\Permissions\PermissionsSet;

/**
 * Class StatamicIdentity
 *
 * Represents a Statamic User Identity for Meerkat Core.
 *
 * @package Stillat\Meerkat\Identity
 * @since 1.0.0
 */
class StatamicIdentity implements AuthorContract
{
    use DataObject;

    /**
     * A collection of additional data attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The author's system identifier, if available.
     *
     * @var null|string
     */
    protected $userId = null;
    /**
     * The identity's display name, if available.
     *
     * @var string
     */
    protected $displayName = '';
    /**
     * The identity's email address, if available.
     *
     * @var string
     */
    protected $emailAddress = '';
    /**
     * Indicates if the identity is transient.
     *
     * @var bool
     */
    private $isTransient = true;
    /**
     * The identity's permission set.
     *
     * @var PermissionsSet|null
     */
    private $permissionSet = null;

    /**
     * Sets the user's system string identifier.
     *
     * @param string $userId The user's string identifier.
     */
    public function setId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Sets the author context's permission set.
     *
     * @param PermissionsSet $permissionSet
     */
    public function setPermissionsSet($permissionSet)
    {
        $this->permissionSet = $permissionSet;
    }

    /**
     * Gets the host system's user object, if available.
     *
     * @return mixed
     */
    public function getHostUser()
    {
        // TODO: Implement.
        return null;
    }

    /**
     * Converts the author data into an array.
     *
     * @return array
     */
    public function toArray()
    {
        // TODO: Implement.
        $hostUserArray = [];

        return [
            AuthorContract::KEY_USER_AGENT => $this->getDataAttribute(AuthorContract::KEY_USER_AGENT, ''),
            AuthorContract::KEY_USER_ID => $this->getId(),
            AuthorContract::KEY_USER_IP => $this->getDataAttribute(AuthorContract::KEY_USER_IP, ''),
            AuthorContract::KEY_NAME => $this->getDisplayName(),
            AuthorContract::KEY_EMAIL_ADDRESS => $this->getEmailAddress(),
            AuthorContract::KEY_HAS_USER => $this->getIsTransient() === false,
            AuthorContract::KEY_USER => $hostUserArray,
            AuthorContract::KEY_PERMISSIONS => $this->getPermissionSet()->toArray()
        ];
    }

    /**
     * Gets the user's system string identifier.
     *
     * @return string|null
     */
    public function getId()
    {
        return $this->userId;
    }

    /**
     * Gets the identity's display name, if available.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * Sets the identity's display name.
     *
     * @param string $displayName
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;
    }

    /**
     * Gets the identity's email address, if available.
     *
     * @return mixed
     */
    public function getEmailAddress()
    {
        return $this->emailAddress;
    }

    /**
     * Sets the identity's email address.
     *
     * @param string $emailAddress The identity's email address.
     * @return mixed
     */
    public function setEmailAddress($emailAddress)
    {
        $this->emailAddress = $emailAddress;
    }

    /**
     * Returns a value indicating if the identity is transient.
     *
     * @return bool
     */
    public function getIsTransient()
    {
        return $this->isTransient;
    }

    /**
     * Sets whether or not the identity is transient.
     *
     * @param bool $isTransient
     */
    public function setIsTransient($isTransient)
    {
        $this->isTransient = $isTransient;
    }

    /**
     * Gets the author context's permission set.
     *
     * @return PermissionsSet
     */
    public function getPermissionSet()
    {
        return $this->permissionSet;
    }

}
