<?php

namespace Stb2\SearchEngine;

class StopWordsCollection
{
    static $languages = [];
    static $all = [];

    public static function get($language)
    {
        $language = ucfirst(strtolower($language));

        if (!array_key_exists($language, self::$languages)) {
            self::$languages[$language] = [];

            $filePath = __DIR__ . '/I18n/' . $language . '/stopWords.php';

            if (is_file($filePath) && file_exists($filePath)) {
                self::$languages[$language] = array_map(
                    '\Stb2\SearchEngine\Term::normalize',
                    require $filePath
                );

                self::$all += self::$languages[$language];
            }
        }

        return self::$languages[$language];
    }

    public static function all()
    {
        return self::$all;
    }
}