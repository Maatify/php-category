<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Integration;

use Maatify\Category\Factory\CategoryFactory;
use Maatify\Category\Api\CategoryApiInterface;
use Maatify\Category\ImageAssignment\Api\Contract\ImageAssignmentApiInterface;
use Maatify\Category\ImageRole\Api\Contract\ImageRoleApiInterface;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentListCriteriaDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentRoleFilterDTO;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleListCriteriaDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;
use Maatify\Category\ImageAssignment\Assignment\Exception\CategoryImageAssignmentAlreadyExistsException;
use Maatify\Category\ImageRole\Lifecycle\Exception\CategoryImageRoleAlreadyExistsException;
use Maatify\Category\ImageRole\Exception\CategoryImageRoleNotFoundException;
use Maatify\Category\ImageRole\Lifecycle\Exception\CategoryImageRoleUnavailableException;
use Maatify\Category\Tests\Integration\Support\CategoryMySqlIntegrationTestCase;
use Maatify\Category\Tests\Integration\Support\FixedCategoryClock;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use PDO;

final class CategoryImageRoleIntegrationTest extends CategoryMySqlIntegrationTestCase
{
    public function testRoleLifecycleIdentityAndManagementFilters(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $management = $this->roleService($connection);

        $galleryId = $management->create(new CreateCategoryImageRoleCommand('gallery'));
        $heroId = $management->create(
            new CreateCategoryImageRoleCommand('hero', CategoryImageRoleStatusEnum::INACTIVE),
        );

        self::assertSame('gallery', $management->getByIdForManagement($galleryId)->roleKey);
        self::assertSame($galleryId, $management->getByKeyForManagement('gallery')->id);
        self::assertSame(
            [$galleryId, $heroId],
            $this->roleIds($management->listForManagement(new CategoryImageRoleListCriteriaDTO())),
        );
        self::assertSame(
            [$galleryId],
            $this->roleIds($management->listForManagement(new CategoryImageRoleListCriteriaDTO(
                status: CategoryImageRoleStatusEnum::ACTIVE,
            ))),
        );

        try {
            $management->create(new CreateCategoryImageRoleCommand('gallery'));
            self::fail('Role keys must be unique.');
        } catch (CategoryImageRoleAlreadyExistsException $exception) {
            self::assertStringContainsString('gallery', $exception->getMessage());
        }

        $management->updateStatus(
            new UpdateCategoryImageRoleStatusCommand($galleryId, CategoryImageRoleStatusEnum::INACTIVE),
        );
        self::assertSame(
            CategoryImageRoleStatusEnum::INACTIVE,
            $management->getByIdForManagement($galleryId)->status,
        );

        $management->softDelete(new SoftDeleteCategoryImageRoleCommand($galleryId));
        try {
            $management->getByIdForManagement($galleryId);
            self::fail('Soft-deleted Roles must be hidden from the default management read.');
        } catch (CategoryImageRoleNotFoundException $exception) {
            self::assertStringContainsString((string) $galleryId, $exception->getMessage());
        }
        self::assertSame(
            $galleryId,
            $management->getByKeyForManagement('gallery', CategoryDeletedStateEnum::DELETED_ONLY)->id,
        );
        self::assertSame(
            [$galleryId],
            $this->roleIds($management->listForManagement(new CategoryImageRoleListCriteriaDTO(
                deletedState: CategoryDeletedStateEnum::DELETED_ONLY,
            ))),
        );

        try {
            $management->create(new CreateCategoryImageRoleCommand('gallery'));
            self::fail('Soft-deleted Role keys must remain reserved.');
        } catch (CategoryImageRoleAlreadyExistsException $exception) {
            self::assertStringContainsString('gallery', $exception->getMessage());
        }

        $management->restore(new RestoreCategoryImageRoleCommand($galleryId));
        self::assertSame($galleryId, $management->getByIdForManagement($galleryId)->id);
        self::assertSame(
            CategoryImageRoleStatusEnum::INACTIVE,
            $management->getByIdForManagement($galleryId)->status,
        );
        $management->updateStatus(
            new UpdateCategoryImageRoleStatusCommand($galleryId, CategoryImageRoleStatusEnum::ACTIVE),
        );
        self::assertSame($galleryId, $management->getByKeyForManagement('gallery')->id);
    }

    public function testRoleAssignmentUsesExactNullableScopeAndRoleVisibility(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $clock = new FixedCategoryClock();
        $consumer = $this->imageService($connection);
        $management = $this->imageService($connection);
        $roles = $this->roleService($connection);
        $categoryId = $service->create(new CreateCategoryCommand('role-assignment-category'));
        $galleryId = $roles->create(new CreateCategoryImageRoleCommand('gallery'));
        $heroId = $roles->create(new CreateCategoryImageRoleCommand('hero'));

        $genericId = $consumer->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                1000,
                new CategoryImageAssignmentScopeDTO('ar', 'ios'),
            ),
        );
        $galleryFirstId = $consumer->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                1000,
                new CategoryImageAssignmentScopeDTO('ar', 'ios', $galleryId),
            ),
        );
        $gallerySecondId = $consumer->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                1001,
                new CategoryImageAssignmentScopeDTO('ar', 'ios', $galleryId),
            ),
        );
        $heroIdAssignment = $consumer->assign(
            new CreateCategoryImageAssignmentCommand(
                $categoryId,
                1000,
                new CategoryImageAssignmentScopeDTO('ar', 'ios', $heroId),
            ),
        );

        self::assertSame(
            [$genericId],
            $this->assignmentIds($consumer->listVisibleForCategory(
                $categoryId,
                new CategoryImageAssignmentScopeDTO('ar', 'ios'),
            )),
        );
        self::assertSame(
            [$galleryFirstId, $gallerySecondId],
            $this->assignmentIds($consumer->listVisibleForCategory(
                $categoryId,
                new CategoryImageAssignmentScopeDTO('ar', 'ios', $galleryId),
            )),
        );
        self::assertSame(
            [$heroIdAssignment],
            $this->assignmentIds($consumer->listVisibleForCategory(
                $categoryId,
                new CategoryImageAssignmentScopeDTO('ar', 'ios', $heroId),
            )),
        );
        self::assertSame(
            [$genericId, $galleryFirstId, $gallerySecondId, $heroIdAssignment],
            $this->assignmentIds($management->listForManagement(
                new CategoryImageAssignmentListCriteriaDTO(categoryId: $categoryId),
            )),
        );
        self::assertSame(
            [$genericId],
            $this->assignmentIds($management->listForManagement(
                new CategoryImageAssignmentListCriteriaDTO(
                    categoryId: $categoryId,
                    scope: new CategoryImageAssignmentScopeDTO('ar', 'ios', null),
                ),
            )),
        );
        self::assertSame(
            [$genericId, $galleryFirstId, $gallerySecondId, $heroIdAssignment],
            $this->assignmentIds($management->listForManagement(
                new CategoryImageAssignmentListCriteriaDTO(
                    categoryId: $categoryId,
                    scope: new CategoryImageAssignmentScopeDTO('ar', 'ios'),
                    roleFilter: CategoryImageAssignmentRoleFilterDTO::omitted(),
                ),
            )),
        );
        self::assertSame(
            [$genericId],
            $this->assignmentIds($management->listForManagement(
                new CategoryImageAssignmentListCriteriaDTO(
                    categoryId: $categoryId,
                    scope: new CategoryImageAssignmentScopeDTO('ar', 'ios'),
                    roleFilter: CategoryImageAssignmentRoleFilterDTO::exactNull(),
                ),
            )),
        );
        self::assertSame(
            [$galleryFirstId, $gallerySecondId],
            $this->assignmentIds($management->listForManagement(
                new CategoryImageAssignmentListCriteriaDTO(
                    categoryId: $categoryId,
                    scope: new CategoryImageAssignmentScopeDTO('ar', 'ios'),
                    roleFilter: CategoryImageAssignmentRoleFilterDTO::forRole($galleryId),
                ),
            )),
        );

        try {
            $consumer->assign(
                new CreateCategoryImageAssignmentCommand(
                    $categoryId,
                    1000,
                    new CategoryImageAssignmentScopeDTO('ar', 'ios', $galleryId),
                ),
            );
            self::fail('The exact Category/Image/Role/scope identity must be unique.');
        } catch (CategoryImageAssignmentAlreadyExistsException $exception) {
            self::assertStringContainsString((string) $galleryId, $exception->getMessage());
        }

        $roles->updateStatus(
            new UpdateCategoryImageRoleStatusCommand($galleryId, CategoryImageRoleStatusEnum::INACTIVE),
        );
        self::assertTrue($consumer->listVisibleForCategory(
            $categoryId,
            new CategoryImageAssignmentScopeDTO('ar', 'ios', $galleryId),
        )->isEmpty());
        self::assertSame(
            [$galleryFirstId, $gallerySecondId],
            $this->assignmentIds($management->listForManagement(new CategoryImageAssignmentListCriteriaDTO(
                categoryId: $categoryId,
                scope: new CategoryImageAssignmentScopeDTO('ar', 'ios', $galleryId),
            ))),
        );

        $roles->updateStatus(
            new UpdateCategoryImageRoleStatusCommand($galleryId, CategoryImageRoleStatusEnum::ACTIVE),
        );
        self::assertCount(2, $consumer->listVisibleForCategory(
            $categoryId,
            new CategoryImageAssignmentScopeDTO('ar', 'ios', $galleryId),
        ));
        $roles->softDelete(new SoftDeleteCategoryImageRoleCommand($galleryId));
        self::assertTrue($consumer->listVisibleForCategory(
            $categoryId,
            new CategoryImageAssignmentScopeDTO('ar', 'ios', $galleryId),
        )->isEmpty());

        $roles->restore(new RestoreCategoryImageRoleCommand($galleryId));
        self::assertCount(2, $consumer->listVisibleForCategory(
            $categoryId,
            new CategoryImageAssignmentScopeDTO('ar', 'ios', $galleryId),
        ));
    }

    public function testManagementPaginationPreservesBinaryRoleKeyOrderingByDefault(): void
    {
        $management = $this->roleService($this->connection());
        $lowercaseId = $management->create(new CreateCategoryImageRoleCommand('a'));
        $uppercaseId = $management->create(new CreateCategoryImageRoleCommand('A1'));

        $page = $management->paginateForManagement(
            new CategoryImageRoleListCriteriaDTO(),
            new PageRequest(perPage: 2),
        );
        $ids = [];
        foreach ($page->data as $role) {
            $ids[] = $role->id;
        }

        self::assertSame([$uppercaseId, $lowercaseId], $ids);
    }

    public function testUnavailableAndMissingRolesAreRejectedForNewAssignments(): void
    {
        $connection = $this->connection();
        $service = $this->commandService($connection);
        $categoryId = $service->create(new CreateCategoryCommand('role-availability-category'));
        $roleService = $this->roleService($connection);
        $imageService = $this->imageService($connection);
        $inactiveRoleId = $roleService->create(new CreateCategoryImageRoleCommand(
            'inactive',
            CategoryImageRoleStatusEnum::INACTIVE,
        ));

        try {
            $imageService->assign(
                new CreateCategoryImageAssignmentCommand(
                    $categoryId,
                    2000,
                    new CategoryImageAssignmentScopeDTO(null, null, $inactiveRoleId),
                ),
            );
            self::fail('Inactive Roles must reject new assignments.');
        } catch (CategoryImageRoleUnavailableException $exception) {
            self::assertStringContainsString('cannot accept new assignments', $exception->getMessage());
        }

        try {
            $imageService->assign(
                new CreateCategoryImageAssignmentCommand(
                    $categoryId,
                    2001,
                    new CategoryImageAssignmentScopeDTO(null, null, 999999),
                ),
            );
            self::fail('Missing Roles must reject new assignments.');
        } catch (CategoryImageRoleNotFoundException $exception) {
            self::assertStringContainsString('999999', $exception->getMessage());
        }

        $roleService->updateStatus(
            new UpdateCategoryImageRoleStatusCommand($inactiveRoleId, CategoryImageRoleStatusEnum::ACTIVE),
        );
        $roleService->softDelete(new SoftDeleteCategoryImageRoleCommand($inactiveRoleId));
        try {
            $imageService->assign(
                new CreateCategoryImageAssignmentCommand(
                    $categoryId,
                    2002,
                    new CategoryImageAssignmentScopeDTO(null, null, $inactiveRoleId),
                ),
            );
            self::fail('Soft-deleted Roles must reject new assignments.');
        } catch (CategoryImageRoleUnavailableException $exception) {
            self::assertStringContainsString('cannot accept new assignments', $exception->getMessage());
        }
    }

    /** @return list<int> */
    private function roleIds(\Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleCollectionDTO $roles): array
    {
        $ids = [];
        foreach ($roles as $role) {
            $ids[] = $role->id;
        }

        return $ids;
    }

    /** @return list<int> */
    private function assignmentIds(\Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentCollectionDTO $assignments): array
    {
        $ids = [];
        foreach ($assignments as $assignment) {
            $ids[] = $assignment->id;
        }

        return $ids;
    }

    private function commandService(PDO $connection): CategoryApiInterface
    {
        return $this->application($connection)->categories();
    }

    private function imageService(PDO $connection): ImageAssignmentApiInterface
    {
        return $this->application($connection)->images();
    }

    private function roleService(PDO $connection): ImageRoleApiInterface
    {
        return $this->application($connection)->imageRoles();
    }

    private function application(PDO $connection): \Maatify\Category\Facade\Contract\CategoryFacadeInterface
    {
        return CategoryFactory::create($connection, new FixedCategoryClock());
    }
}
