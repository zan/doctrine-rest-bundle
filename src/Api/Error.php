<?php

namespace Zan\DoctrineRestBundle\Api;

class Error
{
    public const GENERIC_ERROR              = 'Zan.Drest.ApiException';

    // Entity-specific errors
    public const NO_ENTITY                   = 'Zan.Drest.NoEntity';
    public const NO_ENTITY_PERMISSIONS       = 'Zan.Drest.NoPermissionsOnEntity';
    public const NO_ENTITY_PROPERTY_SETTER   = 'Zan.Drest.NoEntityPropertySetter';

    // The entity version in the database is newer than the entity version sent by the client
    public const CONFLICTING_EDITS          = 'Zan.Drest.ConflictingEdits';

    // Insufficient permissions to perform the given action
    public const INSUFFICIENT_PERMISSIONS    = 'Zan.Drest.InsufficientPermissions';
}