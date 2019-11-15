<?php

namespace Stb2\SearchEngine;

class Search
{
    public static function tfidf(
        Term $term,
        DocumentCollection $collection,
        array $stopWords = []
    ): DocumentCollection {
        $stopWords = array_unique(
            array_map(
                '\Stb2\SearchEngine\Term::removeAccents',
                array_map(
                    '\Stb2\SearchEngine\Term::normalize',
                    $stopWords
                )
            )
        );

        $documents = array_map(function ($document) use ($term, $collection, $stopWords) {
            $tf = 0;
            $idf = 0;

            if (!in_array($term->normalized, $stopWords)) {
                $tf = $document->getTermFrequencyOf($term);
                $idf = $term->getInverseDocumentFrequency($collection);
            }

            return ((clone $document)->addAttribute('tfidf', $tf * $idf));
        }, $collection->getDocuments());

        $documents = array_filter($documents, function ($doc) {
            return $doc->getAttribute('tfidf') > 0;
        });

        usort($documents, function ($a, $b) {
            return $b->getAttribute('tfidf') > $a->getAttribute('tfidf');
        });

        return new DocumentCollection($documents);
    }
}