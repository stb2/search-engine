<?php

namespace Stb2\SearchEngine;

use Stb2\SearchEngine\Utils\Stemming;
use Normalizer;

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
    static $mispelledTermsWithCharacterRemoval = [];

    public function __construct(string $term, string $language = 'en')
    {
        $this->bootLanguage($language);

        $term = Normalizer::normalize($term);

        $this->language = $language;
        $this->original = $term;

        // term without accented characters
        $this->normalized = $this->removeAccents($this->normalize($term));

        // stemming
        $this->stem = $this->removeAccents(
            $this->stem($this->normalize($term), $this->language)
        );

        // validation of the term:
        // more than 2 characters long
        // not a url
        // and starts by a letter
        $this->isValid = $this->isValid($this->normalized);
    }

    public function bootLanguage(string $language)
    {
        array_map(function ($varname) use ($language) {
            if (!array_key_exists($language, self::${$varname})) {
                self::${$varname}[$language] = [];
            }
        }, [
            'termsWithoutAccents',
            'normalizedTerms',
            'validatedTerms',
            'stemmedTerms'
        ]);
    }

    public function getInverseDocumentFrequency(
        DocumentCollection $collection
    ): float {
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

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function languageIs(string $alias): bool
    {
        return $this->language === $alias;
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
        if (in_array(
            $varname,
            ['original', 'normalized', 'isValid', 'stem', 'language']
        )) {
            $getter = 'get' . ucfirst($varname);

            if (method_exists($this, $getter)) {
                return $this->{$getter}();
            }

            return $this->$varname;
        }

        return null;
    }

    public function hasBeenNormalized(string $term): bool
    {
        return array_key_exists($term, self::$normalizedTerms[$this->language]);
    }

    public function normalize(string $term): string
    {
        if (!$this->hasBeenNormalized($term)) {
            $normalized = preg_replace(['/^.(\'|’|´|&#39;|`)/u', '/[^[:alpha:]]$/u'], '', $term);
            $normalized = str_replace(['’', '´', '`', '&#39;'], '\'', $normalized);
            $normalized = preg_replace('/(^[^[:alpha:]0-9-]+|[^[:alpha:]0-9-]+$)/u', '', $normalized);
            $normalized = mb_strtolower($normalized);

            self::$normalizedTerms[$this->language][$term] = $normalized;
        }

        return self::$normalizedTerms[$this->language][$term];
    }

    public function hasBeenStemmed(string $term): bool
    {
        return array_key_exists($term, self::$stemmedTerms[$this->language]);
    }

    public function stem(string $term): string
    {
        if (!$this->hasBeenStemmed($term)) {
            if (Stemming\StemmingLanguageStore::has($this->language)) {
                $stemming = Stemming\StemmingLanguageStore::get($this->language);

                self::$stemmedTerms[$this->language][$term] = (new $stemming)->stem($term);
            }
        }

        return self::$stemmedTerms[$this->language][$term];
    }

    public function accentsHaveBeenRemoved(string $term): bool
    {
        return array_key_exists($term, self::$termsWithoutAccents[$this->language]);
    }

    public function removeAccents(string $term): string
    {
        if (!$this->accentsHaveBeenRemoved($term)) {
            self::$termsWithoutAccents[$this->language][$term] = str_replace(
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

        return self::$termsWithoutAccents[$this->language][$term];
    }

    public function hasBeenValidated(string $term): bool
    {
        return array_key_exists($term, self::$validatedTerms[$this->language]);
    }

    public function isValid(string $term): bool
    {
        if (!$this->hasBeenValidated($term)) {
            self::$validatedTerms[$this->language][$term] = strlen($term) > 2
                && !preg_match('/^((https?:\/\/)?(w{3}\.))/i', $term)
                && preg_match('/^[\p{L}]/ui', $term);
        }

        return self::$validatedTerms[$this->language][$term];
    }

    public static function split(string $term, int $splitLength = 0): array
    {
        return $splitLength > 0
            ? array_reduce(
                range(0, mb_strlen($term, 'UTF-8') - 1),
                function ($chars, $offset) use ($term, $splitLength) {
                    $chars[] = mb_substr($term, $offset, $splitLength, 'UTF-8');

                    return $chars;
                },
                []
            )
            : preg_split("//u", $term, -1, PREG_SPLIT_NO_EMPTY);
    }

    public static function getMispelledTermsWithCharacterRemoval(
        string $term,
        int $distance = 1
    ): array {
        if ($distance < 1) {
            return [$term];
        }

        $length = mb_strlen($term);

        if ($distance === 1) {
            return array_map(function ($offset) use ($term) {
                return mb_substr($term, 0, $offset) . mb_substr($term, $offset + 1);
            }, range(0, $length - 1));
        }

        $keyOfCachedResult = $term . '#' . $distance;

        if (!array_key_exists(
            $keyOfCachedResult,
            self::$mispelledTermsWithCharacterRemoval
        )) {
            self::$mispelledTermsWithCharacterRemoval[$keyOfCachedResult]
                = array_reduce(
                    range(0, $length - $distance),
                    function ($combinations, $offset) use ($distance, $term) {
                        $base = mb_substr($term, 0, $offset);

                        return array_reduce(
                            self::getMispelledTermsWithCharacterRemoval(
                                mb_substr($term, $offset + 1),
                                $distance - 1
                            ),
                            function ($combinations, $subcombination) use ($base) {
                                $combinations[] = $base . $subcombination;

                                return $combinations;
                            },
                            $combinations
                        );
                    },
                    []
                );
        }

        return self::$mispelledTermsWithCharacterRemoval[$keyOfCachedResult];
    }
}