<?php

declare(strict_types=1);

namespace SmartPHPUnitGenerator;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

class DependencyFetcher
{
    /** @var NodeFinder */
    private $nodeFinder;

    /**
     * DependencyFetcher constructor.
     *
     * @param NodeFinder $nodeFinder
     */
    public function __construct(NodeFinder $nodeFinder)
    {
        $this->nodeFinder = $nodeFinder;
    }

    /**
     * @param Stmt[]           $ast
     * @param array            $paramToPropertyMap
     *
     * @return array
     *
     * @FIXME update doc
     *
     *   [
     *      public method of source class
     *      Example: UserService::updateUser()
     *      "updateUser" => [
     *
     *           property of source class with dependency
     *           $this->storageService->getUserByEmail(...)
     *           $this->storageService->create(...)
     *           "storageService" => [
     *               "type" => App\Service\StorageService
     *               "methods" => [
     *                  Key: Used methods of dependency
     *                  Value: call counter
     *
     *                  "getUserByEmail" => 2
     *                  "create" => 1
     *               ]
     *           ]
     *
     *          // The same structure
     *          "userApiProvider" => [
     *              "createUser" => 1
     *          ]
     *          "dispatcher" => [
     *              "dispatch" => 1
     *          ]
     *   ]
     */
    public function fetch(array $ast, array $paramToPropertyMap): array
    {
        // !!!! TODO REFACTOR !!!!

        // make this method recurcive and remove self::getCalledDependencyByClassMethod

        // $methods = $this->getPublicMethods($ast); move out of this method to SmartPHPUnitGenerator

        // move out of this method to SmartPHPUnitGenerator !!!!
//        if ($method->name->toString() === '__construct') {
//            // @todo need to configurate it via param
//            // Do not need to calculate dependency calls for constructor
//            continue;
//        }

        $dependencyCollection = [];
        // Get all public methods of target class
        $methods = $this->getPublicMethods($ast);

        /** @var ClassMethod $method */
        foreach ($methods as $method) {
            if ($method->name->toString() === '__construct') {
                // @todo need to configurate it via param
                // Do not need to calculate dependency calls for constructor
                continue;
            }

            $dependencyForCurrentMethod = [];
            $collingMethods = $this->getCalledDependency($method->stmts);

            /** @var MethodCall $collingMethod */
            foreach ($collingMethods as $collingMethod) {
                if ($collingMethod->var instanceof Variable) {
                    $dependenciesCalledByClassMethod = $this->getCalledDependencyByClassMethod(
                        $collingMethod->name->name,
                        $ast,
                        $paramToPropertyMap
                    );

                    $dependencyForCurrentMethod = array_merge_recursive(
                        $dependencyForCurrentMethod,
                        $dependenciesCalledByClassMethod
                    );
                    continue;
                }

                $dependency = $collingMethod->var->name->toString();
                $dependencyMethodCall = $collingMethod->name->toString();

                $dependencyForCurrentMethod[$dependency]['type'] = $paramToPropertyMap[$dependency];
                $dependencyForCurrentMethod[$dependency]['methods'][$dependencyMethodCall]['args'][] = []; //@todo parse args fot $this->with(...)
            }

            //TODO merge with $dependencyCalledByClassMethod
            $dependencyCollection[$method->name->toString()] = $dependencyForCurrentMethod;
        }

        return $dependencyCollection;
    }

    /**
     * @param string $method
     * @param $ast
     * @param array $paramToPropertyMap
     *
     * @return array
     */
    private function getCalledDependencyByClassMethod(string $method, $ast, array $paramToPropertyMap): array
    {
        $calledMethod = $this->getClassMethodNodeByName($ast, $method);

        $dependencyForCurrentMethod = [];
        $collingMethods = $this->getCalledDependency($calledMethod->stmts);

        /** @var MethodCall $collingMethod */
        foreach ($collingMethods as $collingMethod) {
            if ($collingMethod->var instanceof Variable) {
                //todo check it
                $dependenciesCalledByClassMethod = $this->getCalledDependencyByClassMethod(
                    $collingMethod->name->name,
                    $ast,
                    $paramToPropertyMap
                );

                $dependencyForCurrentMethod = array_merge_recursive(
                    $dependencyForCurrentMethod,
                    $dependenciesCalledByClassMethod
                );
                continue;
            }

            $dependency = $collingMethod->var->name->toString();
            $dependencyMethodCall = $collingMethod->name->toString();

            $dependencyForCurrentMethod[$dependency]['type'] = $paramToPropertyMap[$dependency];
            $dependencyForCurrentMethod[$dependency]['methods'][$dependencyMethodCall]['args'][] = []; //@todo parse args fot $this->with(...)
        }

        return $dependencyForCurrentMethod;
    }

    /**
     * @param array $ast
     *
     * @return array
     */
    private function getPublicMethods(array $ast): array
    {
        return $this->nodeFinder->find($ast, function (Node $node) {
            return $node instanceof ClassMethod && $node->isPublic();
        });
    }

    /**
     * @param ClassMethod[] $methods
     *
     * @return array
     */
    private function getCalledDependency(array $methods): array
    {
        return $this->nodeFinder->find($methods, function (Node $node) {
            if ($node instanceof MethodCall) {
                if ($node->var instanceof Variable && $node->var->name === 'this') {
                    return true;
                }

                if ($node->var instanceof PropertyFetch && $node->var->var->name === 'this') {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @param $ast
     * @param string $methodName
     *
     * @return ClassMethod
     *
     * @throws \RuntimeException @todo refactor to custom
     */
    private function getClassMethodNodeByName($ast, string $methodName): ClassMethod
    {
        /** @var ClassMethod|null $classMethod */
        $classMethod = $this->nodeFinder->findFirst($ast, function (Node $node) use ($methodName) {
            return $node instanceof ClassMethod && $node->name->name === $methodName;
        });

        if ($classMethod === null) {
            throw new \RuntimeException(sprintf('Class method %s not found', $methodName));
        }

        return $classMethod;
    }
}
