<?php

namespace Stb2\SearchEngine\Utils\Stemming;

class FrStemming
{
    static $vowels = 'aeiouyâàëéêèïîôûù';
    static $consonants = 'bcdfghjklmnpqrstvwxzç';

    private $foundSuffixInStep1;

    public function stem($word)
    {
        if (strlen($word) <= 2) {
            return $word;
        }

        $word = preg_replace_callback(
            '/([' . self::$vowels . '])(u|i)([' . self::$vowels . '])/u',
            function ($match) {
                return $match[1] . strtoupper($match[2]) . $match[3];
            },
            $word
        );

        $word = preg_replace('/qu/', 'qU', $word);
        $word = preg_replace('/([' . self::$vowels . '])y/u', '$1Y', $word);
        $word = preg_replace('/y([' . self::$vowels . '])/u', 'Y$1', $word);

        // we keep an original copy of the word
        $originalWord = $word;

        // we start stemming
        $word = $this->step1($word);

        // we do step2a if one of these suffixes has been found
        // or either the original word hasn't been altered
        if ($originalWord === $word
            || in_array($this->foundSuffixInStep1, ['amment', 'emment', 'ment', 'ments'])
        ) {
            $wordBeforeStep2a = $word;
            $word = $this->step2a($word);

            // we do step2b if step2a did nothing
            if ($wordBeforeStep2a === $word) {
                $word = $this->step2b($word);
            }
        }

        // we do step3 if original word has been altered
        if ($word !== $originalWord) {
            $word = $this->step3($word);

        // otherwise we do step4
        } else {
            $word = $this->step4($word);
        }

        $word = $this->step5($word);
        $word = $this->step6($word);
        $word = str_replace(['I', 'U', 'Y'], ['i', 'u', 'y'], $word);

        return $word;
    }

    public function step1($word)
    {
        $rv = self::getRv($word);
        $r1 = self::getR1($word);
        $r2 = self::getR2($word);

        if (preg_match('/(ances?|iqUes?|is(m|t)es?|ables?|eux)$/', $r2, $match)) {
            $this->foundSuffixInStep1 = $match[1];

            return substr($word, 0, strlen($word) - strlen($match[1]));
        }

        if (preg_match('/at(rices?|eurs?|ions?)$/', $r2, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            $word = substr($word, 0, strlen($word) - strlen($match[0]));
            $r2 = substr($r2, 0, strlen($r2) - strlen($match[0]));

            if (preg_match('/ic$/', $word)) {
                $word = substr($word, 0, strlen($word) - 2);

                if (!preg_match('/ic$/', $r2)) {
                    $word .= 'iqU';
                }
            }

            return $word;
        }

        if (preg_match('/logies?$/', $r2, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            return self::replaceAtTheEnd($word, $match[0], 'log');
        }

        if (preg_match('/u(s|t)ions?$/', $r2, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            return self::replaceAtTheEnd($word, $match[0], 'u');
        }

        if (preg_match('/ences?$/', $r2, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            return self::replaceAtTheEnd($word, $match[0], 'ent');
        }

        if (preg_match('/ements?$/', $rv, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            $word = self::deleteAtTheEnd($word, $match[0]);
            $rv = self::getRv($word);
            $r1 = self::getR1($word);
            $r2 = self::getR2($word);

            if (preg_match('/(at)?iv$/', $r2, $match)) {
                return self::deleteAtTheEnd($word, $match[0]);
            }

            if (preg_match('/eus$/', $r2)) {
                return self::deleteAtTheEnd($word, 'eus');
            }

            if (preg_match('/eus$/', $r1)) {
                return self::replaceAtTheEnd($word, 's', 'x');
            }

            if (preg_match('/(abl|iqU)$/', $r2, $match)) {
                return self::deleteAtTheEnd($word, $match[0]);
            }

            if (preg_match('/(i|I)èr$/u', $rv, $match)) {
                return self::replaceAtTheEnd($word, $match[0], 'i');
            }

            return $word;
        }

        if (preg_match('/ités?$/u', $r2, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            $word = self::deleteAtTheEnd($word, $match[0]);
            $r2 = self::getR2($word);

            if (preg_match('/abil$/', $word)) {
                if (preg_match('/abil$/', $r2)) {
                    return self::deleteAtTheEnd($word, 'abil');
                }

                return self::replaceAtTheEnd($word, 'abil', 'abl');
            }

            if (preg_match('/ic$/', $word)) {
                if (preg_match('/ic$/', $r2)) {
                    return self::deleteAtTheEnd($word, 'ic');
                }

                return self::replaceAtTheEnd($word, 'ic', 'iqU');
            }

            if (preg_match('/iv$/', $r2)) {
                return self::deleteAtTheEnd($word, 'iv');
            }

            return $word;
        }

        if (preg_match('/i(f|ve)s?$/', $r2, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            $word = self::deleteAtTheEnd($word, $match[0]);
            $r2 = self::getR2($word);

            if (preg_match('/at$/', $r2)) {
                if (preg_match('/icat$/', $word)) {
                    if (preg_match('/icat$/', $r2)) {
                        return self::deleteAtTheEnd($word, 'icat');
                    }

                    return self::replaceAtTheEnd($word, 'icat', 'iqU');
                }

                return self::deleteAtTheEnd($word, 'at');
            }
        }

        if (preg_match('/eaux$/', $word, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            return self::deleteAtTheEnd($word, 'x');
        }

        if (preg_match('/aux$/', $r1, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            return self::replaceAtTheEnd($word, 'aux', 'al');
        }

        if (preg_match('/euses?$/', $r2, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            return self::deleteAtTheEnd($word, $match[0]);
        }

        if (preg_match('/euses?$/', $r1, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            return self::replaceAtTheEnd($word, $match[0], 'eux');
        }

        if (preg_match('/issements?$/', $r1, $match)
            && preg_match('/[' . self::$consonants . ']issements?$/', $word)
        ) {
            $this->foundSuffixInStep1 = $match[0];

            return self::deleteAtTheEnd($word, $match[0]);
        }

        if (preg_match('/(a|e)mment$/', $rv, $match)) {
            $this->foundSuffixInStep1 = $match[0];

            return self::replaceAtTheEnd($word, $match[0], $match[1] . 'nt');
        }

        if (preg_match('/[' . self::$vowels . '](ments?)$/', $rv, $match)) {
            $this->foundSuffixInStep1 = $match[1];

            return self::deleteAtTheEnd($word, $match[1]);
        }

        return $word;
    }

    public function step2a($word)
    {
        $rv = self::getRv($word);

        $suffixes = [
            'îmes', 'ît', 'îtes', 'i', 'ie', 'ies', 'ir', 'ira', 'irai', 'iraIent',
            'irais', 'irait', 'iras', 'irent', 'irez', 'iriez', 'irions', 'irons',
            'iront', 'is', 'issaIent', 'issais', 'issait', 'issant', 'issante',
            'issantes', 'issants', 'isse', 'issent', 'isses', 'issez', 'issiez',
            'issions', 'issons', 'it'
        ];

        usort($suffixes, function ($a, $b) {
            return strlen($b) > strlen($a);
        });

        if (preg_match('/[' . self::$consonants . '](' . implode('|', $suffixes) . ')$/', $rv, $match)) {
            return self::deleteAtTheEnd($word, $match[1]);
        }

        return $word;
    }

    public function step2b($word)
    {
        $rv = self::getRv($word);
        $r2 = self::getR2($word);

        if (preg_match('/ions$/', $r2)) {
            return self::deleteAtTheEnd($word, 'ions');
        }

        foreach ([
            'é', 'ée', 'ées', 'és', 'èrent', 'er', 'era', 'erai', 'eraIent',
            'erais', 'erait', 'eras', 'erez', 'eriez', 'erions', 'erons', 'eront',
            'ez', 'iez'
        ] as $suffix) {
            if (preg_match('/' . $suffix  . '$/u', $rv, $match)) {
                return self::deleteAtTheEnd($word, $match[0]);
            }
        }

        foreach ([
            'âmes', 'ât', 'âtes', 'a', 'ai', 'aIent', 'ais', 'ait', 'ant', 'ante',
            'antes', 'ants', 'as', 'asse', 'assent', 'asses', 'assiez', 'assions'
        ] as $suffix) {
            if (preg_match('/' . $suffix . '$/u', $rv, $match)) {
                $word = self::deleteAtTheEnd($word, $match[0]);
                $rv = self::getRv($word);

                if (strlen($rv) > 0 && $rv[strlen($rv) - 1] === 'e') {
                    return self::deleteAtTheEnd($word, 'e');
                }

                return $word;
            }
        }

        return $word;
    }

    public function step3($word)
    {
        return preg_replace(['/Y$/', '/ç$/'], ['i', 'c'], $word);
    }

    public function step4($word)
    {
        if (preg_match('/[^aiouès]s/u', $word)) {
            $word = self::deleteAtTheEnd($word, 's');
        }

        $rv = self::getRv($word);
        $r2 = self::getR2($word);

        if (preg_match('/(s|t)ion$/', $rv) && preg_match('/ion$/', $r2)) {
            return self::deleteAtTheEnd($word, 'ion');
        }

        // je l'ai ajouté moi-même
        if (preg_match('/teur$/', $rv) && preg_match('/eur$/', $r2)) {
            return self::deleteAtTheEnd($word, 'eur');
        }

        if (preg_match('/[iI](ère|er)$/u', $rv, $match)) {
            return self::replaceAtTheEnd($word, $match[0], 'i');
        }

        if (preg_match('/e$/', $rv)) {
            return self::deleteAtTheEnd($word, 'e');
        }

        if (preg_match('/guë$/u', $word) && preg_match('/ë$/', $rv)) {
            return self::deleteAtTheEnd($word, 'ë');
        }

        return $word;
    }

    public function step5($word)
    {
        return preg_replace_callback('/([eo]nn|e(t|l){2}|eill)$/', function ($match) {
            return substr($match[0], 0, strlen($match[0]) - 1);
        }, $word);
    }

    public function step6($word)
    {
        return preg_replace('/[éè]([' . self::$consonants . ']+)$/u', 'e$1', $word);
    }

    public static function getBeforeRvRegexp()
    {
        return '^(par|tap|col|[' . self::$vowels . ']{2}[a-z]|[' . self::$vowels . ']?[' . self::$consonants . ']+[' . self::$vowels . '])';
    }

    public static function isInRv($word, $substring)
    {
        return preg_match('/' . self::getBeforeRvRegexp() . '.*' . $substring . '.*$/u', $word);
    }

    public static function getRv($word)
    {
        if (preg_match('/' . self::getBeforeRvRegexp() . '(.*)$/u', $word, $match)) {
            return $match[2];
        }

        return '';
    }

    public static function getR1($word)
    {
        if (preg_match('/^.*[' . self::$vowels . '][' . self::$consonants . '](.*)$/Uu', $word, $match)) {
            return $match[1];
        }

        return '';
    }

    public static function getR2($word)
    {
        $r1 = self::getR1($word);

        if (strlen($r1) <= 2) {
            return '';
        }

        if (preg_match('/^.*[' . self::$vowels . '][' . self::$consonants . '](.*)$/Uu', $r1, $match)) {
            return $match[1];
        }

        return '';
    }

    public static function replaceAtTheEnd($word, $substring, $replace = '')
    {
        return substr($word, 0, strlen($word) - strlen($substring)) . $replace;
    }

    public static function deleteAtTheEnd($word, $substring)
    {
        return self::replaceAtTheEnd($word, $substring, '');
    }
}