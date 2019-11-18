<?php

namespace Stb2\SearchEngine;

class Document
{
    private $text;
    private $language;
    private $terms = [];
    private $attributes = [];

    public function __construct(?string $text, string $language = 'en')
    {
        $this->language = $language;
        $this->setText($text);
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setText(?string $text): Document
    {
        $this->text = str_replace(
            ['&#39;'],
            ['\''],
            strip_tags(stripslashes(html_entity_decode($text)))
        );

        $this->text = preg_replace('/([^\s.])\.([^\s.])/', '$1. $2', $this->text);
        $this->text = preg_replace('/(\.{3,})([^\s.])/', '$1 $2', $this->text);

        if (!empty($this->text)) {
            $this->setTermsFromWords(self::extractWords($this->text));
        }

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setTerms(array $terms): Document
    {
        $this->terms = [];

        array_map(function ($term) {
            $this->addTerm($term);
        }, $terms);

        return $this;
    }

    public function addTerm(Term $term): Document
    {
        if ($term->isValid) {
            if (!$this->hasTerm($term)) {
                $this->terms[$term->stem] = [
                    'term' => $term,
                    'count' => 0
                ];
            }

            $this->terms[$term->stem]['count']++;
        }

        return $this;
    }

    public function setTermsFromWords(array $words): Document
    {
        $this->setTerms(array_map(function ($word) {
            return new Term($word, $this->language);
        }, $words));

        return $this;
    }

    public static function createWithWords(array $words): Document
    {
        $instance = new self;
        $instance->setTermsFromWords($words);

        return $instance;
    }

    public function hasTerm(Term $term): bool
    {
        return array_key_exists($term->stem, $this->terms);
    }

    public function getTerms(): array
    {
        return $this->terms;
    }

    public function getTermFrequencyOf(Term $term): int
    {
        if (!$this->hasTerm($term)) {
            return 0;
        }

        return $this->terms[$term->stem]['count'];
    }

    public static function extractWords(string $text)
    {
        return preg_split(
            '/(\s+)|(' . chr(194) . chr(160) . ')|(\s+.[\'’])|(,)/',
            $text
        );
    }

    /**
     * Get the value of attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set the value of attributes
     *
     * @return  self
     */
    public function setAttributes(array $attributes): Document
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function addAttribute(string $key, $value): Document
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function getAttribute(string $key)
    {
        return $this->attributes[$key];
    }
}

