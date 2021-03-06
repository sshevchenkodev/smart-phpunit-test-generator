<?php

declare(strict_types=1);

namespace SmartPHPUnitTestGenerator;

use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\DeclareDeclare;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;
use SmartPHPUnitTestGenerator\Util\ReturnTypeChecker;

class UnitTestClassRenderer
{
    /** @var BuilderFactory */
    private $builderFactory;

    /** @var Standard */
    private $codePrinter;

    /**
     * ClassRenderer constructor.
     *
     * @param BuilderFactory $builderFactory
     * @param Standard       $codePrinter
     */
    public function __construct(BuilderFactory $builderFactory, Standard $codePrinter)
    {
        $this->builderFactory = $builderFactory;
        $this->codePrinter = $codePrinter;
    }

    /**
     * @param \ReflectionClass $reflectionClass
     * @param array $dependencyCollection
     * @param array $paramToPropertyMap
     *
     * @return string
     */
    public function render(
        \ReflectionClass $reflectionClass,
        array $dependencyCollection,
        array $paramToPropertyMap
    ): string {
        $className = sprintf('%sTest', $reflectionClass->getShortName());
        // FIXME path
        $namespace = sprintf('Tests\Unit\%s', $reflectionClass->getNamespaceName());
        $classMethods = [];
        $useNodes = [];

        foreach ($dependencyCollection as $classMethod => $dependencies) {
            $methodName = sprintf('test%s', ucfirst($classMethod));
            $testMethod = $this->builderFactory->method($methodName);

            /**
             * Calculate not used dependencies just for create mock object and inject into a class
             *
             * Example:
             *
             *  $mockWithoutCallMethod = $this->createMock('SomeDependency'); //not used
             *  $mockWithCallMethod = $this->createMock('UserApiProviderInterface'); //not used
             *  $mockWithCallMethod->expects($this->once())->method('methodName')->willReturn('someResult');
             *
             *  $service = new Service($mockWithoutCallMethod, $mockWithCallMethod);
             */
            $nonUsedDependencies = array_diff_key($paramToPropertyMap, $dependencies);
            /** @var \ReflectionClass $type */
            foreach ($nonUsedDependencies as $nonUsedDependency => $type) {
                $mock = $this->builderFactory->var(sprintf('%sMock', $nonUsedDependency));
                $createMock = $this->prepareCreateMock($type->getShortName());

                $testMethod->addStmt(new Assign($mock, $createMock));

                if (!\array_key_exists($type->getName(), $useNodes)) {
                    $useNodes[$type->getName()] = $this->builderFactory->use($type->getName())->getNode();
                }
            }

            foreach ($dependencies as $dependencyClass => $attributes) {
                $mock = $this->builderFactory->var(sprintf('%sMock', $dependencyClass));
                $createMock = $this->prepareCreateMock($attributes['className']);

                $testMethod->addStmt(new Assign($mock, $createMock));

                foreach ($attributes['methods'] as $method => $params) {
                    if (count($params['args']) > 1) {
                        //TODO
                        continue;
                    }

                    $mockedMethod = $this->builderFactory->methodCall($mock, 'method', [$method]);
                    if (!$attributes['isReturnTypeVoid']) {
                        $willReturn = $this->builderFactory->methodCall($mockedMethod, 'willReturn', []);
                        $testMethod->addStmt($willReturn);
                    } else {
                        $testMethod->addStmt($mockedMethod);
                    }
                }

                if (!\array_key_exists($attributes['type'], $useNodes)) {
                    $useNodes[$attributes['type']] = $this->builderFactory->use($attributes['type'])->getNode();
                }
            }

            /**
             * Create class for test
             *
             * $service = new Service($mockWithoutCallMethod, $mockWithCallMethod);
             * $result = $service->someMethod($args);
             */
            $args = $this->prepareMockVars(array_keys($paramToPropertyMap));
            $varForTestClass = $this->builderFactory->var(lcfirst($reflectionClass->getShortName()));
            $classForTest = $this->builderFactory->new($reflectionClass->getShortName(), $args);
            $testMethod->addStmt(new Assign($varForTestClass, $classForTest));

            $callMethod = $this->builderFactory->methodCall($varForTestClass, $classMethod);
            if (!ReturnTypeChecker::isReturnTypeVoid($reflectionClass->getMethod($classMethod))) {
                $resultVar = $this->builderFactory->var('result');
                $testMethod->addStmt(new Assign($resultVar, $callMethod));
            } else {
                $testMethod->addStmt($callMethod);
            }

            $classMethods[] = $testMethod->getNode();
        }

        $result = [];
        $result[] = $this->prepareDeclareStrictTypes();
        $result[] = $this->builderFactory->namespace($namespace)->getNode();
        $result += $useNodes;
        $result[] = $this->builderFactory->use(TestCase::class)->getNode();
        $result[] = $this->builderFactory->class($className)->extend('TestCase')->addStmts($classMethods)->getNode();

        return $this->codePrinter->prettyPrintFile($result);
    }

    /**
     * @param array $vars
     *
     * @return Variable[]
     */
    private function prepareMockVars(array $vars): array
    {
        return array_map(function (string $varName): Variable {
            return $this->builderFactory->var(sprintf('%sMock', $varName));
        }, $vars);
    }

    /**
     * @param string $className
     *
     * @return MethodCall
     */
    private function prepareCreateMock(string $className): MethodCall
    {
        return $this->builderFactory->methodCall(
            $this->builderFactory->var('this'),
            'createMock',
            [$this->builderFactory->classConstFetch($className, 'class')]
        );
    }

    /**
     * Create declare(strict_types=1); statement
     *
     * @return Declare_
     */
    protected function prepareDeclareStrictTypes(): Declare_
    {
        return new Declare_([new DeclareDeclare('strict_types', $this->builderFactory->val(1))]);
    }
}
