<?php

declare(strict_types=1);

namespace SmartPHPUnitGenerator;

use PhpParser\ParserFactory;

class SmartPHPUnitTestGenerator
{
    /** @var DependencyPropertyMapper */
    private $dependencyPropertyMapper;

    /** @var DependencyFetcher */
    private $dependencyFetcher;

    /** @var UnitTestClassRenderer */
    private $unitTestClassRenderer;

    /** @var ParserFactory */
    private $parserFactory;

    /**
     * SmartPHPUnitTestGenerator constructor.
     *
     * @param DependencyPropertyMapper $dependencyPropertyMapper
     * @param DependencyFetcher $dependencyFetcher
     * @param UnitTestClassRenderer $unitTestClassRenderer
     * @param ParserFactory $parserFactory
     */
    public function __construct(
        DependencyPropertyMapper $dependencyPropertyMapper,
        DependencyFetcher $dependencyFetcher,
        UnitTestClassRenderer $unitTestClassRenderer,
        ParserFactory $parserFactory
    ) {
        $this->dependencyPropertyMapper = $dependencyPropertyMapper;
        $this->dependencyFetcher = $dependencyFetcher;
        $this->unitTestClassRenderer = $unitTestClassRenderer;
        $this->parserFactory = $parserFactory;
    }

    /**
     * @param string $class
     *
     * @param string $testDirPath
     */
    public function generate(string $class, string $testDirPath): void
    {
        $reflector = new \ReflectionClass($class);
        $code = $this->getSourceCode($reflector->getFileName());

        $ast = $this->parserFactory->create(ParserFactory::PREFER_PHP7)->parse($code);
        $paramToPropertyMap = $this->dependencyPropertyMapper->map($reflector, $ast);
        $dependencyCollection = $this->dependencyFetcher->fetch($ast, $paramToPropertyMap);
        $resultCode = $this->unitTestClassRenderer->render($reflector, $dependencyCollection, $paramToPropertyMap);

        file_put_contents(
            $testDirPath . '/' . $reflector->getShortName() . 'Test.php',
            $resultCode
        );

        echo $resultCode . PHP_EOL . PHP_EOL;
        echo $reflector->getShortName() . 'Test.php' . ' was successfuly generated!' . PHP_EOL;
    }

    /**
     * @param string $file
     *
     * @return string
     */
    private function getSourceCode(string $fileName): string
    {
        $file = new \SplFileObject($fileName, 'rb');
        if (!$file->isReadable()) {
            throw new \RuntimeException(sprintf('Can not read file %s', $fileName));
        }

        return $file->fread($file->getSize());
    }
}
