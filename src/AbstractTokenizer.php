<?php

declare(strict_types=1);

namespace Niiknow;

abstract class AbstractTokenizer implements TokenizerInterface
{
    public final function tokenize(string|TokenizableInterface $input): array
    {
        if (is_string($input)) {
            return $this->tokenizeString($input);
        }

        return $this->tokenizeObject($input);
    }

    /** @return array<string> */
    abstract protected function tokenizeString(string $text): array;

    /** @return array<string> */
    protected final function tokenizeObject(TokenizableInterface $tokenizable): array
    {
        return $tokenizable->tokenize();
    }
}
