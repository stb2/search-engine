<?php

namespace Stb2\SearchEngine;

use Stb2\SearchEngine\Utils\Stemming;

class Term
{
    private $original;
    private $normalized;
    private $isValid;
    private $language;

    static $termsWithoutAccents = [];
    static $normalizedTerms = [];
    static $validatedTerms = [];
    static $stemmedTerms = [];

    public function __construct(string $term, string $language = 'en')
    {
        $this->language = $language;
        $this->original = $term;
        $this->normalized = self::removeAccents(self::normalize($term));
        $this->stem = self::removeAccents(self::stem(self::normalize($term), $this->language));
        $this->isValid = self::isValid($this->normalized);
    }

    public function getInverseDocumentFrequency(DocumentCollection $collection): float
    {
        $documentFrequency = $this->getDocumentFrequency($collection);

        if ($documentFrequency === 0) {
            return 0;
        }

        return log(count($collection) / $documentFrequency);
    }

    public function getDocumentFrequency(DocumentCollection $collection): int
    {
        return array_reduce(
            $collection->getDocuments(),
            function (int $count, Document $document) {
                return $count + ($document->hasTerm($this) ? 1 : 0);
            },
            0
        );
    }

    public function getOriginal()
    {
        return $this->original;
    }

    public function getNormalized()
    {
        return $this->normalized;
    }

    public function getStem()
    {
        return $this->stem;
    }

    public function __get($varname)
    {
        if (in_array($varname, ['original', 'normalized', 'isValid', 'stem'])) {
            $getter = 'get' . ucfirst($varname);

            if (method_exists($this, $getter)) {
                return $this->{$getter}();
            }

            return $this->$varname;
        }

        return null;
    }

    public static function hasBeenNormalized(string $term): bool
    {
        return array_key_exists($term, self::$normalizedTerms);
    }

    public static function normalize(string $term): string
    {
        if (!self::hasBeenNormalized($term)) {
            $normalized = preg_replace(['/^.(\'|’|´)/u', '/[^[:alpha:]]$/u'], '', $term);
            $normalized = str_replace(['’', '´'], '\'', $normalized);
            $normalized = preg_replace('/(^[^[:alpha:]0-9-]+|[^[:alpha:]0-9-]+$)/u', '', $normalized);
            $normalized = mb_strtolower($normalized);

            self::$normalizedTerms[$term] = $normalized;
        }

        return self::$normalizedTerms[$term];
    }

    public static function hasBeenStemmed(string $term): bool
    {
        return array_key_exists($term, self::$stemmedTerms);
    }

    public static function stem(string $term, $language = 'en'): string
    {
        if (!self::hasBeenStemmed($term)) {
            $language = strtolower($language);

            if (Stemming\StemmingLanguageStore::has($language)) {
                $stemming = Stemming\StemmingLanguageStore::get($language);

                self::$stemmedTerms[$term] = (new $stemming)->stem($term);
            }
        }

        return self::$stemmedTerms[$term];
    }

    public static function accentsHaveBeenRemoved(string $term): bool
    {
        return array_key_exists($term, self::$termsWithoutAccents);
    }

    public static function removeAccents(string $term): string
    {
        if (!self::accentsHaveBeenRemoved($term)) {
            self::$termsWithoutAccents[$term] = str_replace(
                [
                    'á','à','â','ä','ã', 'å', 'À','Â','Ä','Ã', 'Å',
                    'ç','Ç',
                    'é','è','ê','ë','É','È','Ê','Ë',
                    'í','ì','î','ï','Ì','Î','Ï',
                    'ó','ò','ô','ö','õ','Ò','Ô','Ö','Õ',
                    'ú','ù','û','ü','Ù','Û','Ü',
                    'ÿ','Ÿ', 'ý', 'Ý',
                    'ñ','Ñ',
                    'œ','Œ', 'æ', 'Æ'
                ],
                [
                    'a','a','a','a','a','a','a','a','a', 'a', 'a',
                    'c','c',
                    'e','e','e','e','e','e','e','e',
                    'i','i','i','i','i','i','i',
                    'o','o','o','o','o','o','o','o','o',
                    'u','u','u','u','u','u','u',
                    'y','y', 'y', 'y',
                    'n','n',
                    'oe','oe', 'ae', 'ae'
                ],
                $term
            );
        }

        return self::$termsWithoutAccents[$term];
    }

    public static function hasBeenValidated(string $term): bool
    {
        return array_key_exists($term, self::$validatedTerms);
    }

    public static function isValid(string $term): bool
    {
        if (!self::hasBeenValidated($term)) {
            self::$validatedTerms[$term] = strlen($term) > 2
                && !preg_match('/^((https?:\/\/)?(w{3}\.))/i', $term)
                && preg_match('/^[\p{L}]/ui', $term);
        }

        return self::$validatedTerms[$term];
    }
}