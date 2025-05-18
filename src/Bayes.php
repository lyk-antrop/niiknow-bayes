<?php

namespace Niiknow;

/**
 * TODO: Balik na GitHubu z toho udelej
 *
 * Naive-Bayes Classifier
 * @see https://en.wikipedia.org/wiki/Naive_Bayes_classifier
 *
 * Originally from Niiknow
 * @see https://github.com/Niiknow/Bayes
 *
 * This package was rewritten to accept already tokenized input. Also, tokenizer is separated from the class.
 *
 * TODO: Tokenizer interface
 * TODO: Accept array input
 * TODO: Separate tokenizer
 * TODO: Tests from Niiknow package
 */

/**
 * Naive-Bayes Classifier
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

    /**
     * @var ?callable
     */
    private $tokenizer = null;

    /**
     * @param array<string,mixed>|null $options
     */
    public function __construct(private ?array $options = null)
    {
        $this->reset();
    }

    public function categorize(string $text): ?string
    {
        $maxProbability = -INF;
        $chosenCategory = null;

        if ($this->totalDocuments > 0) {
            $probabilities = $this->probabilities($text);

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

    public function learn(string $text, string $category): self
    {
        // initialize category data structures if we've never seen this category
        $this->initializeCategory($category);

        // update our count of how many documents mapped to this category
        $this->docCount[$category]++;

        // update the total number of documents we have learned from
        $this->totalDocuments++;

        // normalize the text into a word array
        $tokens = ($this->tokenizer)($text);

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

        return $this;
    }

    public function probabilities(string $text): ?array
    {
        $probabilities = [];

        if ($this->totalDocuments > 0) {
            $tokens         = ($this->tokenizer)($text);
            $frequencyTable = $this->frequencyTable($tokens);

            // for this text
            // iterate thru our categories to find the one with max probability
            foreach ($this->categories as $category => $value) {
                $categoryProbability = $this->docCount[$category] / $this->totalDocuments;
                $logProbability      = log($categoryProbability);
                foreach ($frequencyTable as $token => $frequencyInText) {
                    $tokenProbability = $this->tokenProbability($token, $category);

                    // determine the log of the P( w | c ) for this word
                    $logProbability += $frequencyInText * log($tokenProbability);
                }

                $probabilities[$category] = $logProbability;
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

        return json_encode($result);
    }

    public function reset(): self
    {
        if (!$this->options) {
            $this->options = [];
        }

        // set default tokenizer
        $this->tokenizer = function ($text) {
            // convert everything to lowercase
            $text = mb_strtolower($text);

            // split the words
            preg_match_all('/[[:alpha:]]+/u', $text, $matches);

            // first match list of words

            return $matches[0];
        };

        if (isset($this->options['tokenizer'])) {
            $this->tokenizer = $this->options['tokenizer'];
        }

        $this->categories         = [];
        $this->docCount           = [];
        $this->totalDocuments     = 0;
        $this->vocabulary         = [];
        $this->vocabularySize     = 0;
        $this->wordCount          = [];
        $this->wordFrequencyCount = [];

        return $this;
    }

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
