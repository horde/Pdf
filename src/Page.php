<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class Page
{
    private ResourceDictionary $resources;

    /** @var array<int, ContentStream> */
    private array $contentStreams = [];

    /** @var array<int, Annotation> */
    private array $annotations = [];

    private ?int $structParents = null;

    public function __construct(
        public readonly Rectangle $mediaBox,
    ) {
        $this->resources = new ResourceDictionary();
    }

    public function addContentStream(ContentStream $stream): void
    {
        $this->contentStreams[] = $stream;
        $this->resources->merge($stream->resources);
    }

    public function addAnnotation(Annotation $annotation): void
    {
        $this->annotations[] = $annotation;
    }

    public function resourceDictionary(): ResourceDictionary
    {
        return $this->resources;
    }

    /**
     * @return array<int, ContentStream>
     */
    public function contentStreams(): array
    {
        return $this->contentStreams;
    }

    /**
     * @return array<int, Annotation>
     */
    public function annotations(): array
    {
        return $this->annotations;
    }

    public function setStructParents(int $value): void
    {
        $this->structParents = $value;
    }

    public function structParents(): ?int
    {
        return $this->structParents;
    }
}
