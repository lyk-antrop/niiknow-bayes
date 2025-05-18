<?php

namespace Niiknow;

interface ClassifierInterface
{
    /**
     * Identify the category of the provided text parameter
     */
    public function categorize(string $text): ?string;

    /**
     * Teach the classifier
     */
    public function learn(string $text, string $category): self;

    /**
     * Extract the probabilities for each known category
     *
     * @return array<string,float>|null
     */
    public function probabilities(string $text): ?array;

    /**
     * Load the classifier from JSON or array data
     *
     * @param  array<string,mixed>|string $json
     */
    public function fromJson(array|string $json): self;

    /**
     * Create JSON representation of the classifier
     */
    public function toJson(): string;

    public function reset(): self;
}
