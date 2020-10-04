<?php

declare(strict_types=1);

namespace SmartPHPUnitGenerator;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

class DependencyPropertyMapper
{
    /** @var NodeFinder */
    private $nodeFinder;

    /**
     * DependencyPropertyMapper constructor.
     *
     * @param NodeFinder $nodeFinder
     */
    public function __construct(NodeFinder $nodeFinder)
    {
        $this->nodeFinder = $nodeFinder;
    }

    /**
     * Calculate mapping between dependency parameters and assigned to class properties @todo check gramar!!!!
     *
     * @param \ReflectionClass $reflectionClass
     * @param Stmt[]           $ast
     *
     * @return array
     */
    public function map(\ReflectionClass $reflectionClass, array $ast): array
    {
        // "someServiceParam" => "App\Service\SomeService"
        $paramNameToInjectedDependencyTypeMap = [];
        foreach ($reflectionClass->getConstructor()->getParameters() as $parameter) {
            $paramNameToInjectedDependencyTypeMap[$parameter->getName()] = (string)$parameter->getType();
        }

        $constructor = $this->nodeFinder->findFirst($ast, function (Node $node) {
            return $node instanceof ClassMethod && $node->name->toString() === '__construct';
        });
        /** @var Assign[] $assignExpressions */
        $assignExpressions = $this->nodeFinder->findInstanceOf($constructor, Assign::class);
        
        $paramToPropertyMap = [];
        foreach ($assignExpressions as $assignExpression) {
            $dependencyType = $paramNameToInjectedDependencyTypeMap[$assignExpression->expr->name];
            $paramToPropertyMap[$assignExpression->var->name->toString()] = $dependencyType;
        }

        return $paramToPropertyMap;
    }
}
