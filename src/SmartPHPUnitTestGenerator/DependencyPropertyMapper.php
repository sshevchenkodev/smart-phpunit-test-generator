<?php

declare(strict_types=1);

namespace SmartPHPUnitTestGenerator;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt;
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
     * Calculate mapping between dependency parameters and assigned to class properties
     *
     * @param \ReflectionClass $reflectionClass
     * @param Stmt[]           $ast
     *
     * @return array
     */
    public function map(\ReflectionClass $reflectionClass, array $ast): array
    {
        $paramNameToInjectedDependencyTypeMap = [];
        foreach ($reflectionClass->getConstructor()->getParameters() as $parameter) {
            // FIXME if param not object
            $paramNameToInjectedDependencyTypeMap[$parameter->getName()] = $parameter->getClass();
        }

        $constructor = $this->getConstructMethod($ast);

        /** @var Assign[] $assignExpressions */
        $assignExpressions = $this->nodeFinder->findInstanceOf($constructor, Assign::class);
        
        $paramToPropertyMap = [];
        foreach ($assignExpressions as $assignExpression) {
            $dependencyType = $paramNameToInjectedDependencyTypeMap[$assignExpression->expr->name];
            $paramToPropertyMap[$assignExpression->var->name->toString()] = $dependencyType;
        }

        return $paramToPropertyMap;
    }

    /**
     * @param array $ast
     *
     * @return ClassMethod
     */
    private function getConstructMethod(array $ast): ClassMethod
    {
        /** @var ClassMethod $constructor */
        $constructor = $this->nodeFinder->findFirst($ast, function (Node $node) {
            return $node instanceof ClassMethod && $node->name->toString() === '__construct';
        });

        if ($constructor === null) {
            throw new \RuntimeException('Construct method not found');
        }

        return $constructor;
    }
}
