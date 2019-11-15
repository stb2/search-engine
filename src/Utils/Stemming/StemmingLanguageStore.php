<?php

namespace Stb2\SearchEngine\Utils\Stemming;

class StemmingLanguageStore
{
    private static $store = [
        'en' => '\Stb2\SearchEngine\Utils\Stemming\EnStemming',
        'fr' => '\Stb2\SearchEngine\Utils\Stemming\FrStemming'
    ];

    public static function add($language, $class)
    {
        self::$store[$language] = $class;
    }

    public static function has($language)
    {
        return isset(self::$store[$language]);
    }

    public static function remove($language)
    {
        unset(self::$store[$language]);
    }

    public static function get($language)
    {
        return self::$store[$language];
    }
}