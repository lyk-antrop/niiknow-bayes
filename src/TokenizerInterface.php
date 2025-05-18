<?php

declare(strict_types=1);

namespace Niiknow;

interface TokenizerInterface
{
    /**
     * Tokenize the input into an array of tokens
     *
     * @return array<string> An array of tokens
     */
    public function tokenize(TokenizableInterface|string $input): array;
}
