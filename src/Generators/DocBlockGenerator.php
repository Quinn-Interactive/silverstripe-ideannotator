<?php

namespace SilverLeague\IDEAnnotator\Generators;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Serializer;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlockFactory;
use SilverStripe\Control\Controller;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionException;

/**
 * Class DocBlockGenerator
 *
 * @package IDEAnnotator/Generators
 */
class DocBlockGenerator
{
    /**
     * The current class we are working with
     * @var string
     */
    protected $className = '';

    /**
     * @var ReflectionClass
     */
    protected $reflector;

    /**
     * @var AbstractTagGenerator
     */
    protected $tagGenerator;

    /**
     * @var DocBlockFactory
     */
    protected $docBlockFactory;

    /**
     * DocBlockGenerator constructor.
     *
     * @param $className
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function __construct($className)
    {
        $this->className = $className;
        $this->reflector = new ReflectionClass($className);
        $this->docBlockFactory = DocBlockFactory::createInstance();

        $generatorClass = $this->reflector->isSubclassOf(Controller::class)
            ? ControllerTagGenerator::class : OrmTagGenerator::class;

        $this->tagGenerator = new $generatorClass($className, $this->getExistingTags());
    }

    /**
     * @return Tag[]
     * @throws InvalidArgumentException
     */
    public function getExistingTags()
    {
        $docBlock = $this->getExistingDocBlock();
        $docBlock = $this->docBlockFactory->create($docBlock);

        return $docBlock->getTags();
    }

    /**
     * Not that in case there are multiple doblocks for a class,
     * the last one will be returned
     *
     * If we file old style generated docblocks we remove them
     *
     * @return bool|string
     */
    public function getExistingDocBlock()
    {
        return $this->reflector->getDocComment();
    }

    /**
     * @return DocBlock|string
     * @throws LogicException
     * @throws InvalidArgumentException
     */
    public function getGeneratedDocBlock()
    {
        $docBlock = $this->getExistingDocBlock();

        return $this->mergeGeneratedTagsIntoDocBlock($docBlock);
    }

    /**
     * @param string $existingDocBlock
     * @return string
     * @throws LogicException
     * @throws InvalidArgumentException
     */
    protected function mergeGeneratedTagsIntoDocBlock($existingDocBlock)
    {
        $docBlock = $this->docBlockFactory->create($existingDocBlock);

        $summary = $docBlock->getSummary();
        if (!$summary) {
            $summary = 'Class \\' . $this->className;
        }

        $docBlock = new DocBlock($summary, $docBlock->getDescription(), $this->getGeneratedTags());

        $serializer = new Serializer();
        $docBlock = $serializer->getDocComment($docBlock);

        return $docBlock;
    }

    /**
     * Remove all existing tags that are supported by this module.
     *
     * This will make sure that removed ORM properties and Extenions will not remain in the docblock,
     * while providing the option to manually add docblocks like @author etc.
     *
     * @param $docBlock
     * @return string
     */
    public function removeExistingSupportedTags($docBlock)
    {
        $replacements = [
            "/ \* @property ([\s\S]*?)\n/",
            "/ \* @method ([\s\S]*?)\n/",
            "/ \* @mixin ([\s\S]*?)\n/"
        ];

        return (string)preg_replace($replacements, '', $docBlock);
    }

    /**
     * @return Tag[]
     */
    public function getGeneratedTags()
    {
        return $this->tagGenerator->getTags();
    }
}
