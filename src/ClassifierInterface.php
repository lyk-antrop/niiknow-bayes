<?php

namespace Niiknow;

interface ClassifierInterface
{
    /**
     * Identify the category of the provided $input parameter
     */
    public function categorize(string|TokenizableInterface $input): ?string;

    /**
     * Teach the classifier
     */
    public function learn(string|TokenizableInterface $input, string $category): self;

    /**
     * Extract the probabilities for each known category
     *
     * @return array<string,float>|null
     */
    public function probabilities(string|TokenizableInterface $input, ProbabilityFormat $format = ProbabilityFormat::LOG): ?array;

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
