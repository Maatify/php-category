<?php

declare(strict_types=1);

namespace Maatify\Category\Tests\Unit\Command;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentDescriptionCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentNameCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldValueCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\Contract\CategoryServiceInterface;
use Maatify\Category\Common\DTO\CategoryIdDTO;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;
use Maatify\Category\Common\Exception\CategoryInvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;

final class CategoryCommandTest extends TestCase
{
    public function testCanonicalStringIdentityIsNormalizedToAnInteger(): void
    {
        $identity = new CategoryIdDTO('42', 'categoryId');

        self::assertSame(42, $identity->value);
    }

    #[DataProvider('invalidIdentityProvider')]
    public function testEveryCommandRejectsZeroNegativeAndNonCanonicalIdentities(int|string $value): void
    {
        $factories = [
            'CreateCategory.parentId' => static fn (int|string $id): object => new CreateCategoryCommand('code', $id),
            'CreateCategoryContent.categoryId' => static fn (int|string $id): object => new CreateCategoryContentCommand($id, 'en-US', 'Name', null),
            'CreateCategoryImageAssignment.categoryId' => static fn (int|string $id): object => new CreateCategoryImageAssignmentCommand($id, 100),
            'CreateCategoryImageAssignment.mediaAssetId' => static fn (int|string $id): object => new CreateCategoryImageAssignmentCommand(1, $id),
            'ClearCategoryImageAssignmentDefault.assignmentId' => static fn (int|string $id): object => new ClearCategoryImageAssignmentDefaultCommand($id),
            'MoveCategory.categoryId' => static fn (int|string $id): object => new MoveCategoryCommand($id, null),
            'MoveCategory.parentId' => static fn (int|string $id): object => new MoveCategoryCommand(42, $id),
            'RestoreCategory.categoryId' => static fn (int|string $id): object => new RestoreCategoryCommand($id),
            'RestoreCategoryContent.contentId' => static fn (int|string $id): object => new RestoreCategoryContentCommand($id),
            'RestoreCategoryImageAssignment.assignmentId' => static fn (int|string $id): object => new RestoreCategoryImageAssignmentCommand($id),
            'SetCategoryImageAssignmentDefault.assignmentId' => static fn (int|string $id): object => new SetCategoryImageAssignmentDefaultCommand($id),
            'SoftDeleteCategory.categoryId' => static fn (int|string $id): object => new SoftDeleteCategoryCommand($id),
            'SoftDeleteCategoryContent.contentId' => static fn (int|string $id): object => new SoftDeleteCategoryContentCommand($id),
            'SoftDeleteCategoryImageAssignment.assignmentId' => static fn (int|string $id): object => new SoftDeleteCategoryImageAssignmentCommand($id),
            'UpdateCategoryDisplayOrder.categoryId' => static fn (int|string $id): object => new UpdateCategoryDisplayOrderCommand($id, 1),
            'UpdateCategoryStatus.categoryId' => static fn (int|string $id): object => new UpdateCategoryStatusCommand($id, CategoryStatusEnum::ACTIVE),
            'UpdateCategoryContent.contentId' => static fn (int|string $id): object => new UpdateCategoryContentCommand($id, 'Name', null),
            'UpdateCategoryContentName.contentId' => static fn (int|string $id): object => new UpdateCategoryContentNameCommand($id, 'Name'),
            'UpdateCategoryContentDescription.contentId' => static fn (int|string $id): object => new UpdateCategoryContentDescriptionCommand($id, null),
            'UpdateCategoryContentFieldValue.fieldId' => static fn (int|string $id): object => new UpdateCategoryContentFieldValueCommand($id, 'value'),
            'UpdateCategoryImageAssignment.assignmentId' => static fn (int|string $id): object => new UpdateCategoryImageAssignmentDisplayOrderCommand($id, 1),
        ];

        foreach ($factories as $name => $factory) {
            $this->assertInvalidArgument(
                static fn (): object => $factory($value),
                sprintf('%s must reject identity %s.', $name, var_export($value, true)),
            );
        }
    }

    /** @return iterable<string, array{int|string}> */
    public static function invalidIdentityProvider(): iterable
    {
        yield 'zero integer' => [0];
        yield 'negative integer' => [-1];
        yield 'zero' => ['0'];
        yield 'leading zero' => ['07'];
        yield 'positive sign' => ['+7'];
        yield 'negative sign' => ['-7'];
        yield 'whitespace' => ['7 '];
        yield 'decimal' => ['7.0'];
        yield 'scientific notation' => ['7e1'];
        yield 'overflow' => [str_repeat('9', strlen((string) PHP_INT_MAX) + 1)];
    }

    public function testEveryCommandNormalizesCanonicalPositiveStringIdentities(): void
    {
        self::assertSame(42, (new CreateCategoryCommand('code', '42'))->parentId);
        self::assertSame(42, (new CreateCategoryContentCommand('42', 'en-US', 'Name', null))->categoryId);
        self::assertSame(42, (new CreateCategoryImageAssignmentCommand('42', '43'))->categoryId);
        self::assertSame(43, (new CreateCategoryImageAssignmentCommand('42', '43'))->mediaAssetId);
        self::assertSame(42, (new ClearCategoryImageAssignmentDefaultCommand('42'))->assignmentId);
        self::assertSame(42, (new MoveCategoryCommand('42', '43'))->categoryId);
        self::assertSame(43, (new MoveCategoryCommand('42', '43'))->parentId);
        self::assertSame(42, (new RestoreCategoryCommand('42'))->categoryId);
        self::assertSame(42, (new RestoreCategoryContentCommand('42'))->contentId);
        self::assertSame(42, (new RestoreCategoryImageAssignmentCommand('42'))->assignmentId);
        self::assertSame(42, (new SetCategoryImageAssignmentDefaultCommand('42'))->assignmentId);
        self::assertSame(42, (new SoftDeleteCategoryCommand('42'))->categoryId);
        self::assertSame(42, (new SoftDeleteCategoryContentCommand('42'))->contentId);
        self::assertSame(42, (new SoftDeleteCategoryImageAssignmentCommand('42'))->assignmentId);
        self::assertSame(42, (new UpdateCategoryDisplayOrderCommand('42', 1))->categoryId);
        self::assertSame(42, (new UpdateCategoryStatusCommand('42', CategoryStatusEnum::ACTIVE))->categoryId);
        self::assertSame(42, (new UpdateCategoryContentCommand('42', 'Name', null))->contentId);
        self::assertSame(42, (new UpdateCategoryContentNameCommand('42', 'Name'))->contentId);
        self::assertSame(42, (new UpdateCategoryContentDescriptionCommand('42', null))->contentId);
        self::assertSame(42, (new UpdateCategoryContentFieldValueCommand('42', 'value'))->fieldId);
        self::assertSame(42, (new UpdateCategoryImageAssignmentDisplayOrderCommand('42', 1))->assignmentId);
    }

    public function testCreateContentAllowsNullForUnlocalizedContent(): void
    {
        $command = new CreateCategoryContentCommand(42, null, 'Name', null);

        self::assertNull($command->languageCode);
        self::assertSame(
            ['categoryId' => 42, 'languageCode' => null, 'name' => 'Name', 'description' => null],
            $command->jsonSerialize(),
        );
    }

    public function testRequiredStringsRejectEmptyAndWhitespaceOnlyValues(): void
    {
        foreach (['empty' => '', 'spaces' => '   ', 'tabs' => "\t\n"] as $label => $value) {
            $this->assertInvalidArgument(
                static fn (): object => new CreateCategoryCommand($value),
                sprintf('CreateCategory.code must reject %s input.', $label),
            );
            $this->assertInvalidArgument(
                static fn (): object => new CreateCategoryContentCommand(1, $value, 'Name', null),
                sprintf('CreateCategoryContent.languageCode must reject %s input.', $label),
            );
            $this->assertInvalidArgument(
                static fn (): object => new CreateCategoryImageAssignmentCommand(
                    1,
                    100,
                    new CategoryImageAssignmentScopeDTO($value),
                ),
                sprintf('CreateCategoryImageAssignment.languageCode must reject %s input.', $label),
            );
            $this->assertInvalidArgument(
                static fn (): object => new CreateCategoryImageAssignmentCommand(
                    1,
                    100,
                    new CategoryImageAssignmentScopeDTO(null, $value),
                ),
                sprintf('CreateCategoryImageAssignment.platform must reject %s input.', $label),
            );
            $this->assertInvalidArgument(
                static fn (): object => new CreateCategoryContentCommand(1, 'en-US', $value, null),
                sprintf('CreateCategoryContent.name must reject %s input.', $label),
            );
            $this->assertInvalidArgument(
                static fn (): object => new UpdateCategoryContentCommand(1, $value, null),
                sprintf('UpdateCategoryContent.name must reject %s input.', $label),
            );
            $this->assertInvalidArgument(
                static fn (): object => new UpdateCategoryContentNameCommand(1, $value),
                sprintf('UpdateCategoryContentName.name must reject %s input.', $label),
            );
        }
    }

    public function testStorageStringLengthBoundariesAreEnforced(): void
    {
        self::assertSame(100, mb_strlen((new CreateCategoryCommand(str_repeat('x', 100)))->code));
        $this->assertInvalidArgument(
            static fn (): object => new CreateCategoryCommand(str_repeat('x', 101)),
            'Category code must reject more than 100 characters.',
        );

        $languageCode = (new CreateCategoryContentCommand(1, str_repeat('x', 16), 'Name', null))->languageCode;
        self::assertNotNull($languageCode);
        self::assertSame(16, mb_strlen($languageCode));
        $this->assertInvalidArgument(
            static fn (): object => new CreateCategoryContentCommand(1, str_repeat('x', 17), 'Name', null),
            'Language code must reject more than 16 characters.',
        );

        self::assertSame(
            255,
            mb_strlen((new CreateCategoryContentCommand(1, 'en-US', str_repeat('x', 255), null))->name),
        );
        $this->assertInvalidArgument(
            static fn (): object => new CreateCategoryContentCommand(1, 'en-US', str_repeat('x', 256), null),
            'Created content name must reject more than 255 characters.',
        );
        self::assertSame(
            255,
            mb_strlen((new UpdateCategoryContentCommand(1, str_repeat('x', 255), null))->name),
        );
        $this->assertInvalidArgument(
            static fn (): object => new UpdateCategoryContentCommand(1, str_repeat('x', 256), null),
            'Updated content name must reject more than 255 characters.',
        );
        self::assertSame(
            255,
            mb_strlen((new UpdateCategoryContentNameCommand(1, str_repeat('x', 255)))->name),
        );
        $this->assertInvalidArgument(
            static fn (): object => new UpdateCategoryContentNameCommand(1, str_repeat('x', 256)),
            'Inline updated content name must reject more than 255 characters.',
        );
    }

    public function testStatusInputsAreCategoryStatusEnums(): void
    {
        $createStatus = (new ReflectionMethod(CreateCategoryCommand::class, '__construct'))->getParameters()[2]->getType();
        $updateStatus = (new ReflectionMethod(UpdateCategoryStatusCommand::class, '__construct'))->getParameters()[1]->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $createStatus);
        self::assertInstanceOf(ReflectionNamedType::class, $updateStatus);
        self::assertSame(CategoryStatusEnum::class, $createStatus->getName());
        self::assertSame(CategoryStatusEnum::class, $updateStatus->getName());
        self::assertSame(CategoryStatusEnum::INACTIVE, (new CreateCategoryCommand('code', null, CategoryStatusEnum::INACTIVE))->status);
        self::assertSame(CategoryStatusEnum::ACTIVE, (new UpdateCategoryStatusCommand(1, CategoryStatusEnum::ACTIVE))->status);

        $this->assertTypeError(
            static fn (): object => (new ReflectionClass(CreateCategoryCommand::class))->newInstanceArgs(['code', null, 'active']),
            'CreateCategory.status must reject raw strings.',
        );
        $this->assertTypeError(
            static fn (): object => (new ReflectionClass(UpdateCategoryStatusCommand::class))->newInstanceArgs([1, 'active']),
            'UpdateCategoryStatus.status must reject raw strings.',
        );
    }

    public function testMoveRejectsDirectSelfReferenceAfterIdentityNormalization(): void
    {
        $this->assertInvalidArgument(
            static fn (): object => new MoveCategoryCommand('7', 7),
            'MoveCategory must reject a canonical string/int self-parent reference.',
        );
        $this->assertInvalidArgument(
            static fn (): object => new MoveCategoryCommand(7, '7'),
            'MoveCategory must reject an int/canonical string self-parent reference.',
        );
    }

    public function testDisplayOrderMustBeGreaterThanZero(): void
    {
        self::assertSame(1, (new UpdateCategoryDisplayOrderCommand(1, 1))->displayOrder);

        foreach ([0, -1] as $displayOrder) {
            $this->assertInvalidArgument(
                static fn (): object => new UpdateCategoryDisplayOrderCommand(1, $displayOrder),
                sprintf('Display order %d must be rejected.', $displayOrder),
            );
        }
    }

    public function testImageAssignmentCreateUsesExactNullableScopeAndNeverAcceptsDisplayOrder(): void
    {
        $command = new CreateCategoryImageAssignmentCommand(
            42,
            900,
            new CategoryImageAssignmentScopeDTO('en-US', 'web'),
        );

        self::assertSame(42, $command->categoryId);
        self::assertSame(900, $command->mediaAssetId);
        self::assertSame('en-US', $command->scope->languageCode);
        self::assertSame('web', $command->scope->platform);
        self::assertNull($command->scope->roleId);
        self::assertSame('en-US', $command->languageCode);
        self::assertSame('web', $command->platform);
        self::assertSame(
            ['categoryId' => 42, 'mediaAssetId' => 900, 'languageCode' => 'en-US', 'platform' => 'web', 'roleId' => null],
            $command->jsonSerialize(),
        );
        self::assertSame(
            ['assignmentId', 'displayOrder'],
            array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                (new ReflectionMethod(UpdateCategoryImageAssignmentDisplayOrderCommand::class, '__construct'))->getParameters(),
            ),
        );
        self::assertSame(
            ['categoryId', 'mediaAssetId', 'scope'],
            array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                (new ReflectionMethod(CreateCategoryImageAssignmentCommand::class, '__construct'))->getParameters(),
            ),
        );
        self::assertFalse(property_exists(CreateCategoryImageAssignmentCommand::class, 'displayOrder'));
        self::assertFalse(property_exists(CreateCategoryImageAssignmentCommand::class, 'isDefault'));
    }

    public function testImageAssignmentDefaultCommandsOnlyAcceptTheAssignmentIdentity(): void
    {
        self::assertSame(
            ['assignmentId' => 42],
            (new SetCategoryImageAssignmentDefaultCommand('42'))->jsonSerialize(),
        );
        self::assertSame(
            ['assignmentId' => 42],
            (new ClearCategoryImageAssignmentDefaultCommand('42'))->jsonSerialize(),
        );

        self::assertSame(
            ['assignmentId'],
            array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                (new ReflectionMethod(SetCategoryImageAssignmentDefaultCommand::class, '__construct'))->getParameters(),
            ),
        );
        self::assertSame(
            ['assignmentId'],
            array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                (new ReflectionMethod(ClearCategoryImageAssignmentDefaultCommand::class, '__construct'))->getParameters(),
            ),
        );
    }

    public function testCreateCategoryHasNoDisplayOrderInput(): void
    {
        $parameters = (new ReflectionMethod(CreateCategoryCommand::class, '__construct'))->getParameters();

        self::assertSame(
            ['code', 'parentId', 'status'],
            array_map(static fn (\ReflectionParameter $parameter): string => $parameter->getName(), $parameters),
        );
        self::assertFalse(property_exists(CreateCategoryCommand::class, 'displayOrder'));
    }

    public function testCategoryCodeIsOnlyAcceptedDuringCreation(): void
    {
        $mutationCommands = [
            MoveCategoryCommand::class,
            RestoreCategoryCommand::class,
            RestoreCategoryContentCommand::class,
            SoftDeleteCategoryCommand::class,
            SoftDeleteCategoryContentCommand::class,
            UpdateCategoryDisplayOrderCommand::class,
            UpdateCategoryStatusCommand::class,
            UpdateCategoryContentCommand::class,
            CreateCategoryContentCommand::class,
            CreateCategoryImageAssignmentCommand::class,
            UpdateCategoryImageAssignmentDisplayOrderCommand::class,
            RestoreCategoryImageAssignmentCommand::class,
            SoftDeleteCategoryImageAssignmentCommand::class,
        ];

        foreach ($mutationCommands as $commandClass) {
            self::assertFalse(property_exists($commandClass, 'code'), $commandClass . ' must not mutate Category code.');
        }
        self::assertTrue(property_exists(CreateCategoryCommand::class, 'code'));
    }

    public function testContentUpdateCannotMutateCategoryOrLanguageIdentity(): void
    {
        $parameters = (new ReflectionMethod(UpdateCategoryContentCommand::class, '__construct'))->getParameters();
        $propertyNames = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(UpdateCategoryContentCommand::class))->getProperties(),
        );

        self::assertSame(
            ['contentId', 'name', 'description'],
            array_map(static fn (\ReflectionParameter $parameter): string => $parameter->getName(), $parameters),
        );
        self::assertNotContains('categoryId', $propertyNames);
        self::assertNotContains('languageCode', $propertyNames);
    }

    public function testInlineMutationCommandsHaveTypedNarrowPayloads(): void
    {
        self::assertSame(
            ['contentId', 'name'],
            array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                (new ReflectionMethod(UpdateCategoryContentNameCommand::class, '__construct'))->getParameters(),
            ),
        );
        self::assertSame(
            ['contentId', 'description'],
            array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                (new ReflectionMethod(UpdateCategoryContentDescriptionCommand::class, '__construct'))->getParameters(),
            ),
        );
        self::assertSame(
            ['fieldId', 'value'],
            array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                (new ReflectionMethod(UpdateCategoryContentFieldValueCommand::class, '__construct'))->getParameters(),
            ),
        );
        self::assertSame(
            ['contentId' => 7, 'name' => 'Updated'],
            (new UpdateCategoryContentNameCommand(7, 'Updated'))->jsonSerialize(),
        );
        self::assertSame(
            ['contentId' => 7, 'description' => null],
            (new UpdateCategoryContentDescriptionCommand(7, null))->jsonSerialize(),
        );
        self::assertSame(
            ['fieldId' => 17, 'value' => '{"enabled":true}'],
            (new UpdateCategoryContentFieldValueCommand(17, '{"enabled":true}'))->jsonSerialize(),
        );
    }

    public function testAllMutationIntentsRemainTypedCommands(): void
    {
        $expectedCommands = [
            'create' => CreateCategoryCommand::class,
            'move' => MoveCategoryCommand::class,
            'softDelete' => SoftDeleteCategoryCommand::class,
            'restore' => RestoreCategoryCommand::class,
            'updateStatus' => UpdateCategoryStatusCommand::class,
            'updateDisplayOrder' => UpdateCategoryDisplayOrderCommand::class,
        ];

        $serviceReflection = new ReflectionClass(CategoryServiceInterface::class);
        self::assertCount(6, $expectedCommands);

        foreach ($expectedCommands as $methodName => $expectedCommand) {
            $method = $serviceReflection->getMethod($methodName);
            $parameters = $method->getParameters();
            self::assertCount(1, $parameters, $methodName . ' must have one typed Command input.');

            $parameterType = $parameters[0]->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $parameterType);
            self::assertSame($expectedCommand, $parameterType->getName(), $methodName . ' must use its dedicated Command.');

            $commandReflection = new ReflectionClass($expectedCommand);
            self::assertTrue($commandReflection->isFinal());
            self::assertTrue($commandReflection->isReadOnly());
            self::assertTrue($commandReflection->implementsInterface(\JsonSerializable::class));
        }
    }

    /** @param callable(): object $factory */
    private function assertInvalidArgument(callable $factory, string $message): void
    {
        try {
            $factory();
        } catch (CategoryInvalidArgumentException) {
            self::addToAssertionCount(1);

            return;
        }

        self::fail($message);
    }

    /** @param callable(): object $factory */
    private function assertTypeError(callable $factory, string $message): void
    {
        try {
            $factory();
        } catch (TypeError) {
            self::addToAssertionCount(1);

            return;
        }

        self::fail($message);
    }
}
