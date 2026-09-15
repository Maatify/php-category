<?php

declare(strict_types=1);

namespace Maatify\Category\Facade;

use Maatify\Category\Api\CategoryApiInterface;
use Maatify\Category\Content\Api\Contract\ContentApiInterface;
use Maatify\Category\ContentField\Api\Contract\ContentFieldApiInterface;
use Maatify\Category\Facade\Contract\CategoryFacadeInterface;
use Maatify\Category\ImageAssignment\Api\Contract\ImageAssignmentApiInterface;
use Maatify\Category\ImageRole\Api\Contract\ImageRoleApiInterface;

/** Package-level access point containing only Domain API references. */
final readonly class CategoryFacade implements CategoryFacadeInterface
{
    public function __construct(
        private CategoryApiInterface $categories,
        private ContentApiInterface $contents,
        private ContentFieldApiInterface $contentFields,
        private ImageRoleApiInterface $imageRoles,
        private ImageAssignmentApiInterface $images,
    ) {}

    public function categories(): CategoryApiInterface
    {
        return $this->categories;
    }

    public function contents(): ContentApiInterface
    {
        return $this->contents;
    }

    public function contentFields(): ContentFieldApiInterface
    {
        return $this->contentFields;
    }

    public function imageRoles(): ImageRoleApiInterface
    {
        return $this->imageRoles;
    }

    public function images(): ImageAssignmentApiInterface
    {
        return $this->images;
    }
}
