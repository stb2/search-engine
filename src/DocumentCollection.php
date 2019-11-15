<?php

namespace Stb2\SearchEngine;

class DocumentCollection implements \Countable
{
    private $documents = [];

    public function __construct(array $documents = [])
    {
        $this->setDocuments($documents);
    }

    public function setDocuments($documents)
    {
        $this->clearDocuments();

        array_map(function ($document) {
            $this->addDocument($document);
        }, $documents);
    }

    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function addDocument(Document $document): DocumentCollection
    {
        $this->documents[] = $document;

        return $this;
    }

    public function clearDocuments(): DocumentCollection
    {
        $this->documents = [];

        return $this;
    }

    public function count()
    {
        return count($this->documents);
    }
}