<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service\Support;

use Maatify\Category\Content\Query\DTO\CategoryContentDTO;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentDTO;
use Maatify\Category\ImageRole\CategoryImageRoleDTO;
use Maatify\Category\Query\Contract\CategoryQueryReaderInterface;
use Maatify\Category\Content\Query\Contract\CategoryContentQueryReaderInterface;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldQueryReaderInterface;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentQueryReaderInterface;
use Maatify\Category\ImageRole\Query\Contract\CategoryImageRoleQueryReaderInterface;
use Maatify\Category\Query\DTO\CategoryDTO;

/** @internal Test-only in-memory query port. */
final class InMemoryCategoryQueryReader implements
    CategoryQueryReaderInterface,
    CategoryContentQueryReaderInterface,
    CategoryContentFieldQueryReaderInterface,
    CategoryImageAssignmentQueryReaderInterface,
    CategoryImageRoleQueryReaderInterface
{
    /** @var list<int> */
    public array $lockedIds = [];

    /** @var list<int> */
    public array $lockedContentIds = [];

    /** @var list<int> */
    public array $lockedAssignmentIds = [];

    /** @var list<int> */
    public array $lockedContentFieldIds = [];

    /** @var list<int> */
    public array $lockedRoleIds = [];

    /** @var list<CategoryDTO> */
    private array $categories;

    /** @var list<CategoryContentDTO> */
    private array $contents;

    /** @var list<CategoryImageAssignmentDTO> */
    private array $assignments;

    /** @var list<CategoryContentFieldDTO> */
    private array $fields;

    /** @var list<CategoryImageRoleDTO> */
    private array $roles;

    /**
     * @param list<CategoryDTO>                $categories
     * @param list<CategoryContentDTO>         $contents
     * @param list<CategoryImageAssignmentDTO> $assignments
     * @param list<CategoryContentFieldDTO>    $fields
     * @param list<CategoryImageRoleDTO>       $roles
     */
    public function __construct(
        array $categories,
        array $contents = [],
        array $assignments = [],
        array $fields = [],
        array $roles = [],
    ) {
        $this->categories = $categories;
        $this->contents = $contents;
        $this->assignments = $assignments;
        $this->fields = $fields;
        $this->roles = $roles;
    }

    public function findById(int $categoryId): ?CategoryDTO
    {
        foreach ($this->categories as $category) {
            if ($category->id === $categoryId) {
                return $category;
            }
        }

        return null;
    }

    public function findByCode(string $code): ?CategoryDTO
    {
        foreach ($this->categories as $category) {
            if ($category->code === $code) {
                return $category;
            }
        }

        return null;
    }

    public function findActiveById(int $categoryId): ?CategoryDTO
    {
        foreach ($this->categories as $category) {
            if ($category->id === $categoryId && $category->deletedAt === null) {
                return $category;
            }
        }

        return null;
    }

    public function findActiveByIdForUpdate(int $categoryId): ?CategoryDTO
    {
        $this->lockedIds[] = $categoryId;

        return $this->findActiveById($categoryId);
    }

    public function findByIdForUpdate(int $categoryId): ?CategoryDTO
    {
        $this->lockedIds[] = $categoryId;

        return $this->findById($categoryId);
    }

    public function hasNonDeletedChildrenForUpdate(int $categoryId): bool
    {
        foreach ($this->categories as $category) {
            if ($category->parentId === $categoryId && $category->deletedAt === null) {
                return true;
            }
        }

        return false;
    }

    public function findContentById(int $contentId): ?CategoryContentDTO
    {
        foreach ($this->contents as $content) {
            if ($content->id === $contentId) {
                return $content;
            }
        }

        return null;
    }

    public function findContentByIdForUpdate(int $contentId): ?CategoryContentDTO
    {
        $this->lockedContentIds[] = $contentId;

        return $this->findContentById($contentId);
    }

    public function findImageAssignmentById(int $assignmentId): ?CategoryImageAssignmentDTO
    {
        foreach ($this->assignments as $assignment) {
            if ($assignment->id === $assignmentId) {
                return $assignment;
            }
        }

        return null;
    }

    public function findImageAssignmentByIdForUpdate(int $assignmentId): ?CategoryImageAssignmentDTO
    {
        $this->lockedAssignmentIds[] = $assignmentId;

        return $this->findImageAssignmentById($assignmentId);
    }

    public function findImageRoleByIdForUpdate(int $roleId): ?CategoryImageRoleDTO
    {
        $this->lockedRoleIds[] = $roleId;

        foreach ($this->roles as $role) {
            if ($role->id === $roleId) {
                return $role;
            }
        }

        return null;
    }

    public function findContentFieldById(int $fieldId): ?CategoryContentFieldDTO
    {
        foreach ($this->fields as $field) {
            if ($field->id === $fieldId) {
                return $field;
            }
        }

        return null;
    }

    public function findContentFieldByIdForUpdate(int $fieldId): ?CategoryContentFieldDTO
    {
        $this->lockedContentFieldIds[] = $fieldId;

        return $this->findContentFieldById($fieldId);
    }
}
