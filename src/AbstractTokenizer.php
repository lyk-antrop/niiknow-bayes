<?php

declare(strict_types=1);

namespace Niiknow;

abstract class AbstractTokenizer implements TokenizerInterface
{
    public function tokenize(string|TokenizableInterface $input, mixed $tokenizerArgument = null): array
    {
        if (is_string($input)) {
            return $this->tokenizeString($input, $tokenizerArgument);
        }

        return $this->tokenizeObject($input, $tokenizerArgument);
    }

    /** @return array<string> */
    abstract protected function tokenizeString(string $text, mixed $tokenizerArgument = null): array;

    /** @return array<string> */
    protected final function tokenizeObject(TokenizableInterface $tokenizable, mixed $tokenizerArgument = null): array
    {
        return $tokenizable->tokenize($tokenizerArgument);
    }
}
