<?php

declare(strict_types=1);

namespace Maatify\Category\Facade\Contract;

use Maatify\Category\Api\CategoryApiInterface;
use Maatify\Category\Content\Api\Contract\ContentApiInterface;
use Maatify\Category\ContentField\Api\Contract\ContentFieldApiInterface;
use Maatify\Category\ImageAssignment\Api\Contract\ImageAssignmentApiInterface;
use Maatify\Category\ImageRole\Api\Contract\ImageRoleApiInterface;

interface CategoryFacadeInterface
{
    public function categories(): CategoryApiInterface;

    public function contents(): ContentApiInterface;

    public function contentFields(): ContentFieldApiInterface;

    public function imageRoles(): ImageRoleApiInterface;

    /** Returns Category Image Assignments, not Media upload/storage APIs. */
    public function images(): ImageAssignmentApiInterface;
}
