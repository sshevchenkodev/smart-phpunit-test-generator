<?php

declare(strict_types=1);

namespace SmartPHPUnitGenerator;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
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
        $dependencyCollection = [];
        // Get all public methods of target class
        $methods = $this->getPublicMethods($ast);

        /** @var ClassMethod $method */
        foreach ($methods as $method) {
            if ($method->name->toString() === '__construct') {
                continue; // Do not need to calculate dependency calls for constructor @todo need to configurate it via param
            }

            $dependencyForCurrentMethod = [];
            $collingMethods = $this->nodeFinder->find($method->stmts, function (Node $node) {
                return $node instanceof MethodCall &&
                    ($node->var->var->name === 'this' || $node->var->name === 'this');
            });

            /** @var MethodCall $collingMethod */
            foreach ($collingMethods as $collingMethod) {
                if (!method_exists($collingMethod->var->name, 'toString')) {
                    $dependenciesCalledByClassMethod = $this->getCalledDependencyByClassMethod(
                        $collingMethod->name->name,
                        $ast,
                        $paramToPropertyMap
                    );

                    // todo marge
//                    $dependencyForCurrentMethod = $dependencyForCurrentMethod += $dependenciesCalledByClassMethod;
                    continue;
                }

                //TODO marge

                $dependency = $collingMethod->var->name->toString();
                $dependencyMethodCall = $collingMethod->name->toString();

                $dependencyForCurrentMethod[$dependency]['type'] = $paramToPropertyMap[$dependency];
                $dependencyForCurrentMethod[$dependency]['methods'][$dependencyMethodCall]['args'][] = []; //@todo parse args fot $this->with(...)
                $dependencyForCurrentMethod[$dependency]['methods'][$dependencyMethodCall]['callCount'] +=1;
            }

            //TODO merge with $dependencyCalledByClassMethod
            $dependencyCollection[$method->name->toString()] = $dependencyForCurrentMethod;

            dd($dependencyCollection);
        }

        return $dependencyCollection;
    }

    /**
     * @param ClassMethod[] $methods
     *
     * @return array
     */
    private function getCalledDependency(array $methods): array
    {

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
        $collingMethods = $this->nodeFinder->find($calledMethod->stmts, function (Node $node) {
            return $node instanceof MethodCall && ($node->var->var->name === 'this' || $node->var->name === 'this');
        });

        /** @var MethodCall $collingMethod */
        foreach ($collingMethods as $collingMethod) {
            if (!method_exists($collingMethod->var->name, 'toString')) {
                dd(
                    "11111111",
                    $this->getCalledDependencyByClassMethod($collingMethod->name->name, $ast, $paramToPropertyMap)
                );
            }

            $dependency = $collingMethod->var->name->toString();
            $dependencyMethodCall = $collingMethod->name->toString();

            $dependencyForCurrentMethod[$dependency]['type'] = $paramToPropertyMap[$dependency];
            $dependencyForCurrentMethod[$dependency]['methods'][$dependencyMethodCall]['args'][] = []; //@todo parse args fot $this->with(...)
            $dependencyForCurrentMethod[$dependency]['methods'][$dependencyMethodCall]['callCount'] +=1;
        }

        return $dependencyForCurrentMethod;
    }

    /**
     * @param array $dependencyForCurrentMethod
     * @param array $dependenciesCalledByClassMethod
     *
     * @return array
     */
    private function margeDependency(array $dependencyForCurrentMethod, array $dependenciesCalledByClassMethod): array
    {
        // FIXME
    }
}
