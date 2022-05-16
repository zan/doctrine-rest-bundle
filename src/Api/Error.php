<?php

namespace Zan\DoctrineRestBundle\Api;

class Error
{
    public const GENERIC_ERROR              = 'Zan.Drest.ApiException';

    // No permissions are defined on the entity
    public const NO_ENTITY_PERMISSIONS       = 'Zan.Drest.NoPermissionsOnEntity';

    // The entity version in the database is newer than the entity version sent by the client
    public const CONFLICTING_EDITS          = 'Zan.Drest.ConflictingEdits';
}