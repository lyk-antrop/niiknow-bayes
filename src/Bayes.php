<?php

declare(strict_types=1);

namespace Niiknow;

use Niiknow\ProbabilityFormat;
use RuntimeException;

/**
 * Naive-Bayes Classifier
 * @see https://en.wikipedia.org/wiki/Naive_Bayes_classifier
 *
 * Originally from Niiknow
 * @see https://github.com/Niiknow/Bayes
 *
 * CHANGES:
 * - Separate Tokenizer from the classifier
 * - Tokenizers accept string or TokenizableInterface, "tokenizable" objects implement their own tokenization mechanism
 */
class Bayes implements ClassifierInterface
{
    /** @var string[] */
    private const STATE_KEYS = [
        'categories',
        'docCount',
        'totalDocuments',
        'vocabulary',
        'vocabularySize',
        'wordCount',
        'wordFrequencyCount',
    ];

    /**
     * Hashmap of category names
     *
     * @var array<string,bool>
     */
    private array $categories;

    /**
     * Hashmap of document counts by category
     *
     * @var array<string,int>
     */
    private array $docCount;

    /**
     * Trained document count
     */
    private int $totalDocuments;

    /**
     * Hashmap of known tokens
     *
     * @var array<string,bool>
     */
    private array $vocabulary;

    /**
     * Known token count
     */
    private int $vocabularySize;

    /**
     * Hashmap of word counts by category
     *
     * @var array<string,int>
     */
    private array $wordCount;

    /**
     * Word frequency table for each category
     *
     * @var array<string,array<string,int>>
     */
    private array $wordFrequencyCount;

    public function __construct(
        private readonly TokenizerInterface $tokenizer = new DefaultTokenizer()
    ) {
        $this->reset();
    }

    public function categorize(string|TokenizableInterface $input): ?string
    {
        $maxProbability = -INF;
        $chosenCategory = null;

        if ($this->totalDocuments > 0) {
            $probabilities = $this->probabilities($input) ?? [];

            // iterate thru our categories to find the one with max probability
            // for this text
            foreach ($probabilities as $category => $logProbability) {
                if ($logProbability > $maxProbability) {
                    $maxProbability = $logProbability;
                    $chosenCategory = $category;
                }
            }
        }

        return $chosenCategory;
    }

    public function learn(string|TokenizableInterface $input, string $category): self
    {
        // initialize category data structures if we've never seen this category
        $this->initializeCategory($category);

        // update our count of how many documents mapped to this category
        $this->docCount[$category]++;

        // update the total number of documents we have learned from
        $this->totalDocuments++;

        // normalize the text into a word array
        $tokens = $this->tokenizer->tokenize($input);

        // get a frequency count for each token in the text
        $frequencyTable = $this->frequencyTable($tokens);

        // Update vocabulary and word frequency count for this category
        foreach ($frequencyTable as $token => $frequencyInText) {
            // add this word to our vocabulary if not already existing
            if (!isset($this->vocabulary[$token])) {
                $this->vocabulary[$token] = true;
                $this->vocabularySize++;
            }

            // update the frequency information for this word in this category
            if (!isset($this->wordFrequencyCount[$category][$token])) {
                $this->wordFrequencyCount[$category][$token] = $frequencyInText;
            } else {
                $this->wordFrequencyCount[$category][$token] += $frequencyInText;
            }

            // update the count of all words we have seen mapped to this category
            $this->wordCount[$category] += $frequencyInText;
        }

        // Reorder the word frequency count for this category
        $wordFrequencyCount = $this->wordFrequencyCount[$category];
        arsort($wordFrequencyCount);
        $this->wordFrequencyCount[$category] = $wordFrequencyCount;

        return $this;
    }

    public function probabilities(string|TokenizableInterface $input, ProbabilityFormat $format = ProbabilityFormat::LOG): ?array
    {
        $probabilities = [];

        if ($this->totalDocuments > 0) {
            $tokens         = $this->tokenizer->tokenize($input);
            $frequencyTable = $this->frequencyTable($tokens);

            // for this text
            // iterate thru our categories to find the one with max probability
            foreach ($this->categories as $category => $value) {
                $categoryProbability = $this->docCount[$category] / $this->totalDocuments;
                $logProbability      = log($categoryProbability);
                foreach ($frequencyTable as $token => $frequencyInText) {
                    $token = strval($token); // Treat numeric tokens
                    $tokenProbability = $this->tokenProbability($token, $category);

                    // determine the log of the P( w | c ) for this word
                    $logProbability += $frequencyInText * log($tokenProbability);
                }

                $probabilities[$category] = $logProbability;
            }

            // If percentage format is requested, convert log probabilities
            if ($format === ProbabilityFormat::PERCENTAGE || $format === ProbabilityFormat::PROBABILITY) {
                // Find the maximum log probability (least negative value)
                $maxLogProb = max($probabilities);

                // Convert to regular probabilities with normalization
                $regularProbs = [];
                foreach ($probabilities as $category => $logProb) {
                    // Use exponential to convert from log space (and relative to max to prevent underflow)
                    $regularProbs[$category] = exp($logProb - $maxLogProb);
                }

                // Normalize to sum to 1 (percentage)
                $sum = array_sum($regularProbs);
                if ($sum > 0) {
                    foreach ($regularProbs as $category => $prob) {
                        $probability = $prob / $sum;
                        $probabilities[$category] = $format === ProbabilityFormat::PERCENTAGE
                            ? $probability * 100
                            : $probability;
                    }
                }
            }
        }

        return $probabilities;
    }

    public function fromJson(array|string $json): self
    {
        $result = $json;

        // deserialize from json
        if (is_string($json)) {
            $result = json_decode($json, true);
        }

        $this->reset();

        // deserialize from json
        foreach (self::STATE_KEYS as $k) {
            if (isset($result[$k])) {
                $this->{$k} = $result[$k];
            }
        }

        return $this;
    }

    public function toJson(): string
    {
        $result = [];

        // serialize to json
        foreach (self::STATE_KEYS as $k) {
            $result[$k] = $this->{$k};
        }

        if (($result = json_encode($result)) === false) {
            throw new RuntimeException('Failed to serialize to JSON: ' . json_last_error_msg());
        }

        return $result;
    }

    public function reset(): self
    {
        $this->categories         = [];
        $this->docCount           = [];
        $this->totalDocuments     = 0;
        $this->vocabulary         = [];
        $this->vocabularySize     = 0;
        $this->wordCount          = [];
        $this->wordFrequencyCount = [];

        return $this;
    }

    public function prune(int $minFrequency): self
    {
        // Not implemented yet

        /*
        // First, prune word frequency counts by category
        foreach ($this->wordFrequencyCount as $category => $wordFrequencyCount) {
            $this->wordFrequencyCount[$category] = array_filter(
                $wordFrequencyCount,
                fn($count) => $count >= $minFrequency
            );
            $this->wordCount[$category] = array_sum($this->wordFrequencyCount[$category]);
        }
        */

        // Now rebuild vocabulary to contain only tokens that appear in at least one category
        $this->pruneVocabulary();

        return $this;
    }

    /**
     * Rebuild the vocabulary to contain only unique tokens across all categories
     * and update the vocabulary size
     */
    private function pruneVocabulary(): void
    {
        $newVocabulary = [];
        $vocabularySize = 0;

        foreach ($this->wordFrequencyCount as $category => $wordFrequencyCount) {
            foreach (array_keys($wordFrequencyCount) as $token) {
                if (!isset($newVocabulary[$token])) {
                    $newVocabulary[$token] = true;
                    $vocabularySize++;
                }
            }
        }

        $this->vocabulary = $newVocabulary;
        $this->vocabularySize = $vocabularySize;
    }

    /**
     * Get the word frequency count for a specific category
     *
     * @deprecated Used in tests, not a part of the interface
     *
     * @return array<string,int>|null
     */
    public function getWordFrequencyCount(string $category): ?array
    {
        if (isset($this->wordFrequencyCount[$category])) {
            return $this->wordFrequencyCount[$category];
        }

        return null;
    }

    /**
     * @param string[] $tokens
     * @return array<string,int>
     */
    private function frequencyTable(array $tokens): array
    {
        $frequencyTable = [];
        foreach ($tokens as $token) {
            isset($frequencyTable[$token]) ? $frequencyTable[$token]++ : $frequencyTable[$token] = 1;
        }

        return $frequencyTable;
    }

    private function initializeCategory(string $categoryName): self
    {
        if (!isset($this->categories[$categoryName])) {
            $this->docCount[$categoryName]           = 0;
            $this->wordCount[$categoryName]          = 0;
            $this->wordFrequencyCount[$categoryName] = [];
            $this->categories[$categoryName]         = true;
        }

        return $this;
    }

    /**
     * The probability that a token belongs to a category
     */
    private function tokenProbability(string $token, string $category): float
    {
        // how many times this word has occurred in documents mapped to this category
        $wordFrequencyCount = 0;
        if (isset($this->wordFrequencyCount[$category][$token])) {
            $wordFrequencyCount = $this->wordFrequencyCount[$category][$token];
        }

        // what is the count of all words that have ever been mapped to this category
        $wordCount = $this->wordCount[$category];

        // use laplace Add-1 Smoothing equation

        return ($wordFrequencyCount + 1) / ($wordCount + $this->vocabularySize);
    }
}
