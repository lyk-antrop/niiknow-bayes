<?php

declare(strict_types=1);

namespace Niiknow;

/**
 * A very basic tokenizer that splits the text into words
 */
class DefaultTokenizer extends AbstractTokenizer
{
    protected function tokenizeString(string $text, mixed $tokenizerArgument = null): array
    {
        $matches = [];
        preg_match_all('/[[:alpha:]]+/u', mb_strtolower($text), $matches);

        return $matches[0];
    }
}
