<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Api;

use DateTimeImmutable;
use Maatify\Category\Facade\CategoryFacade;
use Maatify\Category\Factory\CategoryFactory;
use Maatify\Category\Facade\Contract\CategoryFacadeInterface;
use Maatify\Category\Api\CategoryApiInterface;
use Maatify\Category\Api\CategoryApi;
use Maatify\Category\Content\Api\ContentApi;
use Maatify\Category\Content\Api\Contract\ContentApiInterface;
use Maatify\Category\Content\Contract\ContentServiceInterface;
use Maatify\Category\ContentField\Api\ContentFieldApi;
use Maatify\Category\ContentField\Api\Contract\ContentFieldApiInterface;
use Maatify\Category\ContentField\Contract\ContentFieldServiceInterface;
use Maatify\Category\Contract\CategoryServiceInterface;
use Maatify\Category\ImageAssignment\Api\Contract\ImageAssignmentApiInterface;
use Maatify\Category\ImageAssignment\Api\ImageAssignmentApi;
use Maatify\Category\ImageAssignment\Contract\ImageAssignmentServiceInterface;
use Maatify\Category\ImageRole\Api\Contract\ImageRoleApiInterface;
use Maatify\Category\ImageRole\Api\ImageRoleApi;
use Maatify\Category\ImageRole\Contract\ImageRoleServiceInterface;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentDescriptionCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentNameCommand;
use Maatify\Category\Query\DTO\CategoryDTO;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldValueCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PHPUnit\Framework\TestCase;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionMethod;
use PDO;

final class CategoryCompositionTest extends TestCase
{
    public function testFacadeExposesExactlyTheFiveDomainApis(): void
    {
        $categories = $this->createStub(CategoryApiInterface::class);
        $contents = $this->createStub(ContentApiInterface::class);
        $fields = $this->createStub(ContentFieldApiInterface::class);
        $roles = $this->createStub(ImageRoleApiInterface::class);
        $images = $this->createStub(ImageAssignmentApiInterface::class);
        $facade = new CategoryFacade($categories, $contents, $fields, $roles, $images);

        self::assertSame($categories, $facade->categories());
        self::assertSame($contents, $facade->contents());
        self::assertSame($fields, $facade->contentFields());
        self::assertSame($roles, $facade->imageRoles());
        self::assertSame($images, $facade->images());

        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(CategoryFacadeInterface::class))->getMethods(),
        );
        self::assertSame(['categories', 'contents', 'contentFields', 'imageRoles', 'images'], $methods);
    }

    public function testEachDomainApiRoutesItsPublicMutationToItsOwnService(): void
    {
        $categoryService = $this->createMock(CategoryServiceInterface::class);
        $categoryService->expects(self::once())->method('create')->willReturn(11);
        self::assertSame(11, (new CategoryApi($categoryService))->create(new CreateCategoryCommand('category')));

        $contentService = $this->createMock(ContentServiceInterface::class);
        $contentService->expects(self::once())->method('create')->willReturn(12);
        self::assertSame(
            12,
            (new ContentApi($contentService))->create(new CreateCategoryContentCommand(11, 'en-US', 'Name', null)),
        );

        $fieldService = $this->createMock(ContentFieldServiceInterface::class);
        $fieldService->expects(self::once())->method('create')->willReturn(13);
        self::assertSame(
            13,
            (new ContentFieldApi($fieldService))->create(
                new CreateCategoryContentFieldCommand(11, 'badge', null, null, \Maatify\Category\ContentField\CategoryContentFieldFormatEnum::TEXT, 'yes'),
            ),
        );

        $roleService = $this->createMock(ImageRoleServiceInterface::class);
        $roleService->expects(self::once())->method('create')->willReturn(14);
        self::assertSame(14, (new ImageRoleApi($roleService))->create(new CreateCategoryImageRoleCommand('gallery')));

        $imageService = $this->createMock(ImageAssignmentServiceInterface::class);
        $imageService->expects(self::once())->method('assign')->willReturn(15);
        self::assertSame(
            15,
            (new ImageAssignmentApi($imageService))->assign(new CreateCategoryImageAssignmentCommand(11, 99)),
        );

        $imageService->expects(self::once())->method('reorder');
        $imageService->expects(self::once())->method('setDefault');
        $imageService->expects(self::once())->method('clearDefault');
        $imageService->expects(self::once())->method('remove');
        $imageService->expects(self::once())->method('restore');
        $imageApi = new ImageAssignmentApi($imageService);
        $imageApi->reorder(new UpdateCategoryImageAssignmentDisplayOrderCommand(15, 2));
        $imageApi->setDefault(new SetCategoryImageAssignmentDefaultCommand(15));
        $imageApi->clearDefault(new ClearCategoryImageAssignmentDefaultCommand(15));
        $imageApi->remove(new SoftDeleteCategoryImageAssignmentCommand(15));
        $imageApi->restore(new RestoreCategoryImageAssignmentCommand(15));
    }

    public function testCategoryApiRoutesManagementCodeAndPaginationReadsToItsService(): void
    {
        $service = $this->createMock(CategoryServiceInterface::class);
        $criteria = new \Maatify\Category\Query\DTO\CategoryListCriteriaDTO(search: 'category');
        $pageRequest = new PageRequest(page: 1, perPage: 10, sortBy: 'code', sortDirection: 'ASC');
        $page = new PageResult(
            data: [],
            page: 1,
            perPage: 10,
            total: 0,
            filtered: 0,
            totalPages: 0,
            hasNext: false,
            hasPrevious: false,
            sortBy: 'code',
            sortDirection: SortDirectionEnum::ASC,
        );
        $timestamp = new DateTimeImmutable('2026-01-01 00:00:00 Africa/Cairo');
        $category = new CategoryDTO(
            id: 1,
            parentId: null,
            code: 'category',
            status: CategoryStatusEnum::ACTIVE,
            displayOrder: 1,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            deletedAt: null,
        );
        $service->expects(self::once())
            ->method('getByCode')
            ->with('category')
            ->willReturn($category);
        $service->expects(self::once())
            ->method('paginateForManagement')
            ->with($criteria, $pageRequest)
            ->willReturn($page);

        $api = new CategoryApi($service);

        self::assertSame($category, $api->getByCode('category'));
        self::assertSame($page, $api->paginateForManagement($criteria, $pageRequest));
    }

    public function testDomainApisRouteInlineMutationsToTheirOwnServices(): void
    {
        $contentService = $this->createMock(ContentServiceInterface::class);
        $contentService->expects(self::once())
            ->method('updateName')
            ->with(new UpdateCategoryContentNameCommand(11, 'Updated name'));
        $contentService->expects(self::once())
            ->method('updateDescription')
            ->with(new UpdateCategoryContentDescriptionCommand(11, null));
        $contentApi = new ContentApi($contentService);
        $contentApi->updateName(new UpdateCategoryContentNameCommand(11, 'Updated name'));
        $contentApi->updateDescription(new UpdateCategoryContentDescriptionCommand(11, null));

        $fieldService = $this->createMock(ContentFieldServiceInterface::class);
        $fieldService->expects(self::once())
            ->method('updateValue')
            ->with(new UpdateCategoryContentFieldValueCommand(17, 'Updated value'));
        (new ContentFieldApi($fieldService))->updateValue(
            new UpdateCategoryContentFieldValueCommand(17, 'Updated value'),
        );
    }

    public function testEachDomainApiContractExposesExactlyItsDomainServiceOperations(): void
    {
        $contractServices = [
            CategoryApiInterface::class => CategoryServiceInterface::class,
            ContentApiInterface::class => ContentServiceInterface::class,
            ContentFieldApiInterface::class => ContentFieldServiceInterface::class,
            ImageRoleApiInterface::class => ImageRoleServiceInterface::class,
            ImageAssignmentApiInterface::class => ImageAssignmentServiceInterface::class,
        ];

        foreach ($contractServices as $apiContract => $serviceContract) {
            $apiMethods = $this->methodNames($apiContract);
            $serviceMethods = $this->methodNames($serviceContract);
            sort($apiMethods);
            sort($serviceMethods);

            self::assertSame($serviceMethods, $apiMethods, $apiContract . ' must preserve its service operation surface.');
        }
    }

    public function testImageAssignmentApiUsesConsumerWorkflowNames(): void
    {
        $methods = $this->methodNames(ImageAssignmentApiInterface::class);
        sort($methods);

        self::assertSame([
            'assign',
            'clearDefault',
            'getByIdForManagement',
            'listForManagement',
            'listVisibleForCategory',
            'paginateForManagement',
            'remove',
            'reorder',
            'restore',
            'setDefault',
        ], $methods);
        self::assertNotContains('create', $methods);
        self::assertNotContains('softDelete', $methods);
        self::assertNotContains('updateDisplayOrder', $methods);
    }

    public function testFactoryIsFrameworkNeutralAndRequiresHostPrimitives(): void
    {
        $method = new ReflectionMethod(CategoryFactory::class, 'create');
        $parameters = $method->getParameters();

        self::assertSame(['pdo', 'clock'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $parameters,
        ));
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame(PDO::class, $parameters[0]->getType()->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[1]->getType());
        self::assertSame(ClockInterface::class, $parameters[1]->getType()->getName());
        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(CategoryFacadeInterface::class, $returnType->getName());
    }

    public function testDomainServicesDoNotExposeCrossDomainMutationMethodsOrLegacyApiClasses(): void
    {
        self::assertNotContains('createContent', $this->methodNames(CategoryServiceInterface::class));
        self::assertNotContains('createImageAssignment', $this->methodNames(CategoryServiceInterface::class));
        self::assertNotContains('createContentField', $this->methodNames(CategoryServiceInterface::class));
        self::assertNotContains('createImageRole', $this->methodNames(CategoryServiceInterface::class));
        self::assertFalse(class_exists('Maatify\\Category\\Api\\Service\\CategoryCommandService'));
        self::assertFalse(class_exists('Maatify\\Category\\Api\\Service\\CategoryQueryService'));
        self::assertFalse(class_exists('Maatify\\Category\\Api\\Service\\CategoryManagementQueryService'));
    }

    /**
     * @param class-string $interface
     * @return list<string>
     */
    private function methodNames(string $interface): array
    {
        return array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass($interface))->getMethods(),
        );
    }
}
