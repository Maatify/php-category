<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Service;

use Maatify\Category\Content\Query\Contract\CategoryContentManagementReadQueryInterface;
use Maatify\Category\Content\Query\Contract\CategoryContentQueryReaderInterface;
use Maatify\Category\Content\Query\Contract\CategoryContentReadQueryInterface;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldManagementReadQueryInterface;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldQueryReaderInterface;
use Maatify\Category\ContentField\Query\Contract\CategoryContentFieldReadQueryInterface;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentManagementReadQueryInterface;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentQueryReaderInterface;
use Maatify\Category\ImageAssignment\Query\Contract\CategoryImageAssignmentReadQueryInterface;
use Maatify\Category\ImageRole\Query\Contract\CategoryImageRoleManagementReadQueryInterface;
use Maatify\Category\ImageRole\Query\Contract\CategoryImageRoleQueryReaderInterface;
use Maatify\Category\Query\Contract\CategoryManagementReadQueryInterface;
use Maatify\Category\Query\Contract\CategoryQueryReaderInterface;
use Maatify\Category\Query\Contract\CategoryReadQueryInterface;
use Maatify\Category\Tests\Unit\Service\Support\FixedClock;
use Maatify\Category\Tests\Unit\Service\Support\InMemoryCategoryTransaction;
use PHPUnit\Framework\TestCase;

/** Test-only construction of isolated domain services with explicit read ports. */
abstract class DomainReadServiceTestCase extends TestCase
{
    protected function categoryService(
        ?CategoryReadQueryInterface $visible = null,
        ?CategoryManagementReadQueryInterface $management = null,
    ): \Maatify\Category\Service\CategoryService {
        return new \Maatify\Category\Service\CategoryService(
            $this->createStub(\Maatify\Category\Contract\CategoryCommandRepositoryInterface::class),
            $this->createStub(CategoryQueryReaderInterface::class),
            $visible ?? $this->createStub(CategoryReadQueryInterface::class),
            $management ?? $this->createStub(CategoryManagementReadQueryInterface::class),
            new InMemoryCategoryTransaction(),
            new FixedClock(),
        );
    }

    protected function contentService(
        ?CategoryContentReadQueryInterface $visible = null,
        ?CategoryContentManagementReadQueryInterface $management = null,
    ): \Maatify\Category\Content\Service\ContentService {
        return new \Maatify\Category\Content\Service\ContentService(
            $this->createStub(\Maatify\Category\Content\Contract\CategoryContentCommandRepositoryInterface::class),
            $this->createStub(CategoryQueryReaderInterface::class),
            $this->createStub(CategoryContentQueryReaderInterface::class),
            $visible ?? $this->createStub(CategoryContentReadQueryInterface::class),
            $management ?? $this->createStub(CategoryContentManagementReadQueryInterface::class),
            new InMemoryCategoryTransaction(),
            new FixedClock(),
        );
    }

    protected function contentFieldService(
        ?CategoryContentFieldReadQueryInterface $visible = null,
        ?CategoryContentFieldManagementReadQueryInterface $management = null,
    ): \Maatify\Category\ContentField\Service\ContentFieldService {
        return new \Maatify\Category\ContentField\Service\ContentFieldService(
            $this->createStub(\Maatify\Category\ContentField\Contract\CategoryContentFieldCommandRepositoryInterface::class),
            $this->createStub(CategoryQueryReaderInterface::class),
            $this->createStub(CategoryContentFieldQueryReaderInterface::class),
            $visible ?? $this->createStub(CategoryContentFieldReadQueryInterface::class),
            $management ?? $this->createStub(CategoryContentFieldManagementReadQueryInterface::class),
            new InMemoryCategoryTransaction(),
            new FixedClock(),
        );
    }

    protected function imageRoleService(
        ?CategoryImageRoleManagementReadQueryInterface $management = null,
    ): \Maatify\Category\ImageRole\Service\ImageRoleService {
        return new \Maatify\Category\ImageRole\Service\ImageRoleService(
            $this->createStub(\Maatify\Category\ImageRole\Contract\CategoryImageRoleCommandRepositoryInterface::class),
            $this->createStub(CategoryImageRoleQueryReaderInterface::class),
            $management ?? $this->createStub(CategoryImageRoleManagementReadQueryInterface::class),
            new InMemoryCategoryTransaction(),
            new FixedClock(),
        );
    }

    protected function imageAssignmentService(
        ?CategoryImageAssignmentReadQueryInterface $visible = null,
        ?CategoryImageAssignmentManagementReadQueryInterface $management = null,
    ): \Maatify\Category\ImageAssignment\Service\ImageAssignmentService {
        return new \Maatify\Category\ImageAssignment\Service\ImageAssignmentService(
            $this->createStub(\Maatify\Category\ImageAssignment\Contract\CategoryImageAssignmentCommandRepositoryInterface::class),
            $this->createStub(CategoryQueryReaderInterface::class),
            $this->createStub(CategoryImageRoleQueryReaderInterface::class),
            $this->createStub(CategoryImageAssignmentQueryReaderInterface::class),
            $visible ?? $this->createStub(CategoryImageAssignmentReadQueryInterface::class),
            $management ?? $this->createStub(CategoryImageAssignmentManagementReadQueryInterface::class),
            new InMemoryCategoryTransaction(),
            new FixedClock(),
        );
    }
}
