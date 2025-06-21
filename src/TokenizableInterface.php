<?php

namespace Niiknow;

/**
 * Interface for tokenizable objects
 * 
 * This interface is used to define a contract for objects that can be tokenized.
 * Implementing classes should provide a method to convert themselves into an array of tokens.
 * 
 * @package Niiknow
 */
interface TokenizableInterface
{
    /**
     * Tokenize itself into an array of tokens
     *
     * @param mixed $tokenizerArgument Optional argument for the tokenizer
     * @return string[] An array of tokens
     */
    public function tokenize(mixed $tokenizerArgument = null): array;
}
