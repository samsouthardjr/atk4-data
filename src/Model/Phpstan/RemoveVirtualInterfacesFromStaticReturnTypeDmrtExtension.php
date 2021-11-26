<?php

declare(strict_types=1);

namespace Atk4\Data\Model\Phpstan;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\Dummy\ChangedTypeMethodReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ResolvedMethodReflection;
use PHPStan\Reflection\Type\CalledOnTypeUnresolvedMethodPrototypeReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Modify return types by reresolving static/$this type with virtual interfaces removed.
 */
class RemoveVirtualInterfacesFromStaticReturnTypeDmrtExtension implements DynamicMethodReturnTypeExtension, DynamicStaticMethodReturnTypeExtension
{
    protected string $className;
    protected string $virtualInterfaceName;

    public function __construct(string $className, string $virtualInterfaceName)
    {
        $this->className = (new \ReflectionClass($className))->getName();
        $this->virtualInterfaceName = (new \ReflectionClass($virtualInterfaceName))->getName();
    }

    public function getClass(): string
    {
        return $this->className;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection instanceof ResolvedMethodReflection; // TODO why not all?
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $this->isMethodSupported($methodReflection);
    }

    protected function unresolveMethodReflection(ResolvedMethodReflection $methodReflection): MethodReflection
    {
        $methodReflection = \Closure::bind(fn () => $methodReflection->reflection, null, ResolvedMethodReflection::class)();
        if (!$methodReflection instanceof ChangedTypeMethodReflection) {
            throw new \Exception('Unexpected method reflection class: ' . get_class($methodReflection));
        }

        $methodReflection = \Closure::bind(fn () => $methodReflection->reflection, null, ChangedTypeMethodReflection::class)();

        return $methodReflection;
    }

    protected function resolveMethodReflection(MethodReflection $methodReflection, Type $calledOnType): MethodReflection
    {
        $resolver = (new CalledOnTypeUnresolvedMethodPrototypeReflection(
            $methodReflection,
            $methodReflection->getDeclaringClass(),
            false,
            $calledOnType
        ));

        return $resolver->getTransformedMethod();
    }

    protected function removeVirtualInterfacesFromType(Type $type): Type
    {
        if ($type instanceof IntersectionType) {
            $types = [];
            foreach ($type->getTypes() as $t) {
                $t = $this->removeVirtualInterfacesFromType($t);
                if (!$t instanceof NeverType) {
                    $types[] = $t;
                }
            }

            return count($types) === 0 ? new NeverType() : TypeCombinator::intersect(...$types);
        }

        if ($type instanceof ObjectType && $type->isInstanceOf($this->virtualInterfaceName)->yes()) {
            return new NeverType();
        }

        return $type->traverse(\Closure::fromCallable([$this, 'removeVirtualInterfacesFromType']));
    }

    /**
     * @param MethodCall|StaticCall $methodCall
     */
    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        $methodCall,
        Scope $scope
    ): Type {
        // resolve static type and remove all virtual interfaces from it
        if ($methodCall instanceof StaticCall) {
            $classNameType = $scope->getType(new ClassConstFetch($methodCall->class, 'class'));
            $calledOnOrigType = new ObjectType($classNameType->getValue()); // @phpstan-ignore-line
        } else {
            $calledOnOrigType = $scope->getType($methodCall->var);
        }
        $calledOnType = $this->removeVirtualInterfacesFromType($calledOnOrigType);

        $methodReflection = $this->unresolveMethodReflection($methodReflection);
        $methodReflection = $this->resolveMethodReflection($methodReflection, $calledOnType);

        return ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $methodCall->getArgs(),
            $methodReflection->getVariants()
        )->getReturnType();
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): Type {
        return $this->getTypeFromMethodCall($methodReflection, $methodCall, $scope);
    }
}
